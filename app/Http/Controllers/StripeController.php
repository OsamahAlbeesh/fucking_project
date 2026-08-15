<?php

namespace App\Http\Controllers;

use App\Models\Flat;
use App\Models\FlatUser;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\Webhook;

class StripeController extends Controller
{
    /**
     * إنشاء جلسة دفع Stripe وإعادة رابط الدفع للمستأجر
     */
    public function createCheckoutSession(Request $request)
    {

        $request->validate([
            'flat_user_id' => 'required|exists:flat_user,id',
        ]);

        $booking = FlatUser::with('flat')->findOrFail($request->flat_user_id);
        $user = auth()->user();

        if ($booking->status !== 'Awaiting_Payment') {
            return response()->json(['message' => 'لا يمكنك الدفع لهذا العقار حتى يوافق المالك على طلب حجزك أولاً'], 403);
}
        if ($booking->user_id !== $user->id) {
            return response()->json(['message' => 'غير مصرح لك بالدفع لهذا الطلب'], 403);
        }

        if ($user->verified_status !== 'approved') {
            return response()->json(['message' => 'حسابك لم يتم الموافقة عليه بعد من الإدارة'], 403);
        }

        $rawPrice = $booking->type === 'buy'
            ? $booking->flat->getRawOriginal('price')
            : $booking->flat->getRawOriginal('rent_price');

        if (!$rawPrice) {
            return response()->json(['message' => 'سعر العقار غير محدد في النظام'], 400);
        }

        Stripe::setApiKey(config('services.stripe.secret'));

        try {
            // 5. منع التكرار: فحص إذا كان هناك معاملة معلقة مسبقاً لنفس هذا الحجز عبر Stripe
            $transaction = Transaction::where('flat_user_id', $booking->id)
                                      ->where('payment_method', 'stripe')
                                      ->whereIn('status', ['pending', 'Pending'])
                                      ->first();

            // إذا لم تكن هناك معاملة معلقة سابقة، ننشئ واحدة جديدة لأول مرة

            // 6. إنشاء الـ Session داخل سيرفرات سترايب
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => 'حجز الشقة رقم: ' . $booking->flat_id,
                            'description' => $booking->flat->details,
                        ],
                        'unit_amount' => $rawPrice*100, // نمرر السعر الخام كاملاً لـ Stripe بالسنتات مباشرة
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                // توجيه لصفحة نجاح تجريبية لمنع استدعاء المحفظة
                'success_url' => 'http://localhost:3000/payment-success?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => 'http://localhost:3000/payment-failed',
                'metadata' => [
                    'flat_user_id' => $booking->id,
                    'user_id' => $user->id,
                    'flat_id' => $booking->flat_id,
                    'type' => $booking->type,
                ],
            ]);

            if (!$transaction) {
                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'flat_id' => $booking->flat_id,
                    'flat_user_id' => $booking->id,
                    'payment_method' => 'stripe',
                    'amount' => $rawPrice / 100, // نقسم على 100 لتعويض الـ Mutator
                    'commission' => ($rawPrice * 0.025), // عمولة المنصة 2.5%
                    'type' => $booking->type === 'buy' ? 'purchase' : 'rental',
                    'status' => 'pending',
                ]);
            }

            // 7. تحديث المعاملة بـ stripe_session_id فوراً لضمان عدم بقائها NULL
            $transaction->update([
                'stripe_session_id' => $session->id
            ]);

            return response()->json([
                'success' => true,
                'checkout_url' => $session->url
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['message' => 'فشل إنشاء جلسة الدفع: ' . $e->getMessage()], 500);
        }
    }

    /**
     * الـ Webhook المسؤول عن استقبال تأكيدات الدفع التلقائية من سيرفرات Stripe
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = env('STRIPE_WEBHOOK_SECRET');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (\UnexpectedValueException $e) {
            return response()->json(['error' => 'Payload غير صالحة'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response()->json(['error' => 'توقيع الـ Webhook غير صالح'], 400);
        }

        // إذا تمت عملية الدفع بنجاح في سيرفرات Stripe
        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $flatUserId = $session->metadata->flat_user_id;

            DB::beginTransaction();
            try {
                // البحث عن المعاملة المالية باستخدام معرف الجلسة
                $transaction = Transaction::where('stripe_session_id', $session->id)->first();

                if ($transaction) {
                    // قراءة الحالة الخام لتجنب تأثير الـ Accessor
                    $currentStatus = strtolower($transaction->getRawOriginal('status'));

                    if ($currentStatus !== 'completed') {
                        // 1. تحديث حالة المعاملة المالية إلى مكتملة
                        $transaction->update([
                            'status' => 'completed',
                            'stripe_payment_id' => $session->payment_intent,
                            'payment_details' => json_encode([
                                'customer_details' => $session->customer_details,
                                'payment_status' => $session->payment_status
                            ])
                        ]);

                        // 2. تحديث حالة الطلب في جدول flat_user
                        $booking = FlatUser::find($flatUserId);
                        if ($booking) {
                            $newStatus = $booking->type === 'buy' ? 'Sold' : 'Accepted';
                            $booking->update(['status' => $newStatus]);

                            // 3. إضافة الصافي المالي لحساب المالك (Landlord Balance)
                            $flat = Flat::find($booking->flat_id);
                            if ($flat) {
                                $landlord = User::find($flat->user_id);
                                if ($landlord) {
                                    // جلب القيم الخام وحساب الصافي للمالك
                                    $rawAmount = $transaction->getRawOriginal('amount');
                                    $rawCommission = $transaction->getRawOriginal('commission');
                                    $netAmount = $rawAmount - $rawCommission;

                                    // التقسيم على 100 للتحويل من سنتات إلى دولار للمحفظة
                                    $landlord->increment('balance', $netAmount);
                                }

                                // تحديث حالات الشقق بناءً على نوع العملية
                                if ($booking->type === 'buy') {
                                    FlatUser::where('flat_id', $flat->id)
                                        ->where('id', '!=', $booking->id)
                                        ->whereIn('status', ['Pending', 'pending'])
                                        ->update(['status' => 'Rejected']);

                                    $flat->update(['status' => 'sold']);
                                } elseif ($booking->type === 'rent') {
                                    $flat->update(['status' => 'rented']);
                                }
                            }
                        }
                    }
                }

                DB::commit();
                return response()->json(['success' => true, 'message' => 'تمت المعالجة المالية وتحديث الجداول بنجاح'], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('خطأ في معالجة Stripe Webhook: ' . $e->getMessage());
                return response()->json(['error' => 'خطأ داخلي أثناء تحديث البيانات'], 500);
            }
        }

        return response()->json(['status' => 'ignored'], 200);
    }
}
