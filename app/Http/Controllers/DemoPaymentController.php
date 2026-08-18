<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DemoPaymentController extends Controller
{
    /**
     * يحاكي دفع عربون الحجز من محفظة العميل إلى محفظة المالك.
     * لا يستقبل المبلغ من العميل؛ فهو يحسب على الخادم بنسبة 5% من السعر.
     */
    public function payReservationDeposit(Request $request)
    {
        $validated = $request->validate([
            'property_user_id' => ['required', 'integer', 'exists:property_user,id'],
            'reference_number' => ['required', 'string', 'min:8', 'max:30', 'regex:/^[A-Za-z0-9-]+$/'],
        ]);

        $customer = auth()->user();

        if ($customer->verified_status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Your Account has not yet been Approved',
            ], 403);
        }

        try {
            return DB::transaction(function () use ($validated, $customer) {
                $booking = DB::table('property_user')
                    ->where('id', $validated['property_user_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$booking || (int) $booking->user_id !== (int) $customer->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'غير مصرح لك بالدفع لهذا الطلب.',
                    ], 403);
                }

                if ($booking->status !== 'Awaiting_Payment') {
                    return response()->json([
                        'success' => false,
                        'message' => 'هذا الحجز ليس بانتظار دفع العربون.',
                    ], 409);
                }

                $property = DB::table('properties')
                    ->where('id', $booking->property_id)
                    ->lockForUpdate()
                    ->first();

                if (!$property || (int) $property->user_id === (int) $customer->id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'لا يمكن دفع عربون لعقار تملكه أنت.',
                    ], 403);
                }

                if ($property->status !== 'available') {
                    return response()->json([
                        'success' => false,
                        'message' => 'هذا العقار لم يعد متاحاً للحجز.',
                    ], 409);
                }

                $price = $booking->type === 'buy' ? $property->price : $property->rent_price;

                if (!is_numeric($price) || (int) $price <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'سعر العقار غير محدد بشكل صالح في النظام.',
                    ], 422);
                }

                // جميع القيم أعداد صحيحة في أصغر وحدة يعتمدها المشروع.
                $depositAmount = max(1, intdiv((int) $price, 20));

                if (DB::table('transactions')->where('reference_number', $validated['reference_number'])->exists()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'رقم المرجع مستخدم مسبقاً. يرجى توليد رقم جديد.',
                    ], 409);
                }

                $alreadyPaid = DB::table('transactions')
                    ->where('property_user_id', $booking->id)
                    ->where('payment_method', 'demo')
                    ->where('demo_status', 'paid_simulated')
                    ->exists();

                if ($alreadyPaid) {
                    return response()->json([
                        'success' => false,
                        'message' => 'تم دفع عربون هذا الحجز مسبقاً.',
                    ], 409);
                }

                $lockedCustomer = DB::table('users')->where('id', $customer->id)->lockForUpdate()->first();
                $landlord = DB::table('users')->where('id', $property->user_id)->lockForUpdate()->first();

                if (!$landlord || $landlord->role !== 'landlord') {
                    return response()->json([
                        'success' => false,
                        'message' => 'مالك العقار غير صالح لاستلام العربون.',
                    ], 422);
                }

                if ((int) $lockedCustomer->balance < $depositAmount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'رصيدك غير كافٍ لدفع عربون الحجز.',
                        'data' => [
                            'required_deposit' => $depositAmount,
                            'current_balance' => (int) $lockedCustomer->balance,
                        ],
                    ], 422);
                }

                $now = now();
                $transactionId = DB::table('transactions')->insertGetId([
                    'user_id' => $customer->id,
                    'property_id' => $property->id,
                    'property_user_id' => $booking->id,
                    'type' => 'reservation_deposit',
                    'amount' => $depositAmount,
                    'commission' => 0,
                    'payment_method' => 'demo',
                    'reference_number' => $validated['reference_number'],
                    'demo_status' => 'paid_simulated',
                    // يبقى الحقل القديم متوافقاً مع بنية المشروع.
                    'status' => 'completed',
                    'paid_at' => $now,
                    'payment_details' => json_encode([
                        'provider' => 'demo',
                        'payment_type' => 'reservation_deposit',
                        'currency' => 'project_balance_unit',
                        'note' => 'Simulated internal wallet transfer only',
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('users')->where('id', $customer->id)->decrement('balance', $depositAmount);
                DB::table('users')->where('id', $landlord->id)->increment('balance', $depositAmount);

                // Accepted هنا تعني أن العربون حُجز بنجاح ضمن الحالات الموجودة سابقاً.
                DB::table('property_user')->where('id', $booking->id)->update([
                    'status' => 'Accepted',
                    'updated_at' => $now,
                ]);

                $this->recordReservationEvent($booking->id, $customer->id, 'demo_deposit_paid', 'Awaiting_Payment', 'Accepted', [
                    'transaction_id' => $transactionId,
                    'amount' => $depositAmount,
                    'reference_number' => $validated['reference_number'],
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'تمت محاكاة دفع عربون الحجز ونقل الرصيد إلى المالك بنجاح.',
                    'data' => [
                        'transaction_id' => $transactionId,
                        'property_user_id' => $booking->id,
                        'amount' => $depositAmount,
                        'reference_number' => $validated['reference_number'],
                        'payment_status' => 'paid_simulated',
                        'reservation_status' => 'Accepted',
                    ],
                ], 200);
            });
        } catch (\Throwable $exception) {
            Log::error('Demo payment failed: '.$exception->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'تعذر تنفيذ محاكاة الدفع حالياً.',
            ], 500);
        }
    }

    /** يطلب العميل استرداداً؛ لا يُعاد الرصيد تلقائياً. */
    public function requestRefund(Request $request, $transactionId)
    {
        $customer = auth()->user();

        $transaction = DB::table('transactions')
            ->where('id', $transactionId)
            ->where('user_id', $customer->id)
            ->where('payment_method', 'demo')
            ->first();

        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'دفعة تجريبية غير موجودة أو لا تخصك.'], 404);
        }

        if ($transaction->demo_status !== 'paid_simulated') {
            return response()->json(['success' => false, 'message' => 'لا يمكن طلب استرداد لهذه الدفعة بحالتها الحالية.'], 409);
        }

        DB::table('transactions')->where('id', $transaction->id)->update([
            'demo_status' => 'refund_requested',
            'refund_requested_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recordReservationEvent($transaction->property_user_id, $customer->id, 'refund_requested', 'Accepted', 'Accepted', [
            'transaction_id' => $transaction->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال طلب الاسترداد للإدارة. لن يُعاد الرصيد قبل موافقة المدير.',
        ], 200);
    }

    /** يفتح العميل نزاعاً ويجمّد حالة الدفعة التجريبية. */
    public function openDispute(Request $request, $bookingId)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $customer = auth()->user();

        return DB::transaction(function () use ($validated, $customer, $bookingId) {
            $booking = DB::table('property_user')
                ->where('id', $bookingId)
                ->where('user_id', $customer->id)
                ->lockForUpdate()
                ->first();

            if (!$booking) {
                return response()->json(['success' => false, 'message' => 'الحجز غير موجود أو لا يخصك.'], 404);
            }

            if ($booking->status !== 'Accepted') {
                return response()->json(['success' => false, 'message' => 'يمكن فتح نزاع فقط لحجز دُفع عربونه.'], 409);
            }

            if (DB::table('disputes')->where('property_user_id', $booking->id)->whereIn('status', ['open', 'under_review'])->exists()) {
                return response()->json(['success' => false, 'message' => 'يوجد نزاع مفتوح لهذا الحجز بالفعل.'], 409);
            }

            $now = now();
            $disputeId = DB::table('disputes')->insertGetId([
                'property_user_id' => $booking->id,
                'opened_by' => $customer->id,
                'reason' => $validated['reason'],
                'status' => 'open',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('transactions')
                ->where('property_user_id', $booking->id)
                ->where('payment_method', 'demo')
                ->where('demo_status', 'paid_simulated')
                ->update(['demo_status' => 'frozen', 'updated_at' => $now]);

            $this->recordReservationEvent($booking->id, $customer->id, 'dispute_opened', 'Accepted', 'Accepted', [
                'dispute_id' => $disputeId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم فتح النزاع وتجميد الدفعة التجريبية بانتظار الإدارة.',
                'data' => ['dispute_id' => $disputeId],
            ], 201);
        });
    }

    private function recordReservationEvent($bookingId, $actorId, $eventType, $fromStatus = null, $toStatus = null, array $metadata = []): void
    {
        DB::table('reservation_events')->insert([
            'property_user_id' => $bookingId,
            'actor_id' => $actorId,
            'event_type' => $eventType,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'metadata' => empty($metadata) ? null : json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }
}
