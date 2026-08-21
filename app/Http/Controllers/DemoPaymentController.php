<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DemoPaymentController extends Controller
{
    /**
     * يتمم العميل وثيقة عربون مولدة تلقائياً عند موافقة المالك.
     * لا يستقبل المبلغ أو الرقم المرجعي من العميل.
     */
    public function payReservationDeposit(Request $request)
    {
        $validated = $request->validate([
            'property_user_id' => ['required', 'integer', 'exists:property_user,id'],
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
                    return response()->json(['success' => false, 'message' => 'غير مصرح لك بالدفع لهذا الطلب.'], 403);
                }

                if ($booking->status !== 'Awaiting_Payment') {
                    return response()->json(['success' => false, 'message' => 'هذا الحجز ليس بانتظار دفع العربون.'], 409);
                }

                $property = DB::table('properties')->where('id', $booking->property_id)->lockForUpdate()->first();
                if (!$property || (int) $property->user_id === (int) $customer->id) {
                    return response()->json(['success' => false, 'message' => 'لا يمكن دفع عربون لعقار تملكه أنت.'], 403);
                }

                if ($property->status !== 'available') {
                    return response()->json(['success' => false, 'message' => 'هذا العقار لم يعد متاحاً للحجز.'], 409);
                }

                $transaction = DB::table('transactions')
                    ->where('property_user_id', $booking->id)
                    ->where('user_id', $customer->id)
                    ->where('payment_method', 'demo')
                    ->where('demo_status', 'pending')
                    ->lockForUpdate()
                    ->first();

                if (!$transaction) {
                    return response()->json([
                        'success' => false,
                        'message' => 'لا توجد وثيقة دفع معلقة لهذا الحجز. راجع المالك أو الإدارة.',
                    ], 409);
                }

                $depositAmount = (int) $transaction->amount;
                if ($depositAmount <= 0 || !$transaction->reference_number) {
                    return response()->json(['success' => false, 'message' => 'وثيقة الدفع المعلقة غير صالحة.'], 422);
                }

                $lockedCustomer = DB::table('users')->where('id', $customer->id)->lockForUpdate()->first();
                $landlord = DB::table('users')->where('id', $property->user_id)->lockForUpdate()->first();

                if (!$landlord || $landlord->role !== 'landlord') {
                    return response()->json(['success' => false, 'message' => 'مالك العقار غير صالح لاستلام العربون.'], 422);
                }

                if ((int) $lockedCustomer->balance < $depositAmount) {
                    return response()->json([
                        'success' => false,
                        'message' => 'رصيدك غير كافٍ لدفع عربون الحجز.',
                        'data' => [
                            'required_deposit' => $depositAmount,
                            'current_balance' => (int) $lockedCustomer->balance,
                            'reference_number' => $transaction->reference_number,
                        ],
                    ], 422);
                }

                $now = now();
                DB::table('transactions')->where('id', $transaction->id)->update([
                    'demo_status' => 'paid_simulated',
                    'status' => 'completed',
                    'paid_at' => $now,
                    'updated_at' => $now,
                ]);
                DB::table('users')->where('id', $customer->id)->decrement('balance', $depositAmount);
                DB::table('users')->where('id', $landlord->id)->increment('balance', $depositAmount);
                DB::table('property_user')->where('id', $booking->id)->update([
                    'status' => 'Sold',
                    'updated_at' => $now,
                ]);

                $propertyStatus = $booking->type === 'buy'
                    ? 'sold'
                    : 'rented';

                DB::table('properties')
                    ->where('id', $booking->property_id)
                    ->update([
                        'status' => $propertyStatus,
                        'updated_at' => now(),
                    ]);

                $this->recordReservationEvent($booking->id, $customer->id, 'demo_deposit_paid', 'Awaiting_Payment', 'Accepted', [
                    'transaction_id' => $transaction->id,
                    'amount' => $depositAmount,
                    'reference_number' => $transaction->reference_number,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'تمت محاكاة دفع عربون الحجز ونقل الرصيد إلى المالك بنجاح.',
                    'data' => [
                        'transaction_id' => $transaction->id,
                        'property_user_id' => $booking->id,
                        'amount' => $depositAmount,
                        'reference_number' => $transaction->reference_number,
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

    /**
     * يعرض وثيقة الدفعة للعميل. ولتوافق الحجوزات التي قُبلت قبل الإصلاح،
     * ينشئ الوثيقة الناقصة مرة واحدة عندما تكون الحالة Awaiting_Payment.
     */
    public function getPaymentDocument(Request $request, $bookingId)
    {
        $customer = auth()->user();

        return DB::transaction(function () use ($bookingId, $customer) {
            $booking = DB::table('property_user')
                ->where('id', $bookingId)
                ->where('user_id', $customer->id)
                ->lockForUpdate()
                ->first();

            if (!$booking) {
                return response()->json(['success' => false, 'message' => 'الحجز غير موجود أو لا يخصك.'], 404);
            }

            $payment = DB::table('transactions')
                ->where('property_user_id', $booking->id)
                ->where('user_id', $customer->id)
                ->where('payment_method', 'demo')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (!$payment && $booking->status === 'Awaiting_Payment') {
                $property = DB::table('properties')->where('id', $booking->property_id)->first();
                $price = $property && $booking->type === 'buy' ? $property->price : ($property->rent_price ?? null);

                if (!is_numeric($price) || (int) $price <= 0) {
                    return response()->json(['success' => false, 'message' => 'لا يمكن إنشاء الوثيقة لأن سعر العقار غير صالح.'], 422);
                }

                $now = now();
                $paymentId = DB::table('transactions')->insertGetId([
                    'user_id' => $customer->id,
                    'property_id' => $booking->property_id,
                    'property_user_id' => $booking->id,
                    'type' => 'reservation_deposit',
                    'amount' => max(1, intdiv((int) $price, 20)),
                    'commission' => 0,
                    'payment_method' => 'demo',
                    'reference_number' => $this->generateDemoReferenceNumber(),
                    'status' => 'pending',
                    'demo_status' => 'pending',
                    'payment_details' => json_encode([
                        'provider' => 'demo',
                        'payment_type' => 'reservation_deposit',
                        'note' => 'Backfilled for a reservation awaiting payment before document creation was added',
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $payment = DB::table('transactions')->where('id', $paymentId)->first();
                $this->recordReservationEvent($booking->id, null, 'payment_document_backfilled', 'Awaiting_Payment', 'Awaiting_Payment', [
                    'transaction_id' => $paymentId,
                ]);
            }

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'لا توجد وثيقة دفع لهذا الحجز؛ لا يمكن إنشاؤها قبل موافقة المالك.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم جلب وثيقة الدفع التجريبية.',
                'data' => [
                    'transaction_id' => $payment->id,
                    'property_user_id' => (int) $payment->property_user_id,
                    'amount' => (int) $payment->amount,
                    'reference_number' => $payment->reference_number,
                    'payment_method' => $payment->payment_method,
                    'status' => $payment->status,
                    'demo_status' => $payment->demo_status,
                    'created_at' => $payment->created_at,
                ],
            ], 200);
        });
    }

    private function generateDemoReferenceNumber(): string
    {
        do {
            $referenceNumber = 'DEMO-'.strtoupper(Str::random(16));
        } while (DB::table('transactions')->where('reference_number', $referenceNumber)->exists());

        return $referenceNumber;
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
