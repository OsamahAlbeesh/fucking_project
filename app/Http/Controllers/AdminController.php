<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;


class AdminController extends Controller
{


//    public function dashboard(){
//    $pendingUsers = User::whereIn('role', ['landlord', 'customer'])
//                        ->where('verified_status', 'pending')
//                        ->get();
//
//    return view('admin.dashboard', compact('pendingUsers'));
//    }

    public function updateStatus(Request $request)
    {
        $user = User::findOrFail($request->user_id);
        $user->verified_status = $request->verified_status;
        $user->save();

        return redirect()->back()->with('message', 'تم تحديث حالة المستخدم بنجاح');
    }
    public function verifyUser(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'verified_status' => ['required', Rule::in(['approved', 'rejected'])],
        ]);

        $user = User::query()
            ->whereKey($validated['user_id'])
            ->whereIn('role', ['customer', 'landlord'])
            ->firstOrFail();

        if ($user->verified_status !== 'pending') {
            return response()->json([
                'message' => 'This user has already been reviewed.',
                'verified_status' => $user->verified_status,
            ], 409);
        }

        $user->update([
            'verified_status' => $validated['verified_status'],
        ]);

        return response()->json([
            'message' => 'User verification status updated successfully.',
            'user' => $user->only([
                'id',
                'first_name',
                'last_name',
                'phone',
                'role',
                'verified_status',
            ]),
        ], 200);
    }

    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->back()->with('message', 'تم حذف الحساب بنجاح');
    }

    /** المدير فقط يحدد حالة النزاع؛ لا توجد عملية تحويل هنا. */
    public function resolveDispute(Request $request, $disputeId)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['under_review', 'resolved_for_customer', 'resolved_for_landlord', 'closed'])],
            'admin_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return DB::transaction(function () use ($validated, $disputeId) {
            $dispute = DB::table('disputes')->where('id', $disputeId)->lockForUpdate()->first();

            if (!$dispute) {
                return response()->json(['success' => false, 'message' => 'النزاع غير موجود.'], 404);
            }

            if (in_array($dispute->status, ['resolved_for_customer', 'resolved_for_landlord', 'closed'], true)) {
                return response()->json(['success' => false, 'message' => 'تمت معالجة هذا النزاع مسبقاً.'], 409);
            }

            $now = now();
            $update = [
                'status' => $validated['status'],
                'admin_notes' => $validated['admin_notes'] ?? null,
                'updated_at' => $now,
            ];

            if (in_array($validated['status'], ['resolved_for_customer', 'resolved_for_landlord', 'closed'], true)) {
                $update['resolved_by'] = auth()->id();
                $update['resolved_at'] = $now;
            }

            DB::table('disputes')->where('id', $dispute->id)->update($update);

            if ($validated['status'] === 'resolved_for_landlord') {
                DB::table('transactions')
                    ->where('property_user_id', $dispute->property_user_id)
                    ->where('payment_method', 'demo')
                    ->where('demo_status', 'frozen')
                    ->update(['demo_status' => 'paid_simulated', 'updated_at' => $now]);
            }

            DB::table('reservation_events')->insert([
                'property_user_id' => $dispute->property_user_id,
                'actor_id' => auth()->id(),
                'event_type' => 'dispute_'.$validated['status'],
                'metadata' => json_encode(['dispute_id' => $dispute->id], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تحديث حالة النزاع بنجاح.',
                'data' => ['dispute_id' => $dispute->id, 'status' => $validated['status']],
            ], 200);
        });
    }

    /** يعيد عربوناً تجريبياً مع عكس حركة الرصيد بعد قرار الإدارة فقط. */
    public function refundDemoPayment(Request $request, $transactionId)
    {
        $validated = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        return DB::transaction(function () use ($validated, $transactionId) {
            $transaction = DB::table('transactions')->where('id', $transactionId)->lockForUpdate()->first();

            if (!$transaction || $transaction->payment_method !== 'demo') {
                return response()->json(['success' => false, 'message' => 'دفعة تجريبية غير موجودة.'], 404);
            }

            if (!in_array($transaction->demo_status, ['refund_requested', 'frozen'], true)) {
                return response()->json(['success' => false, 'message' => 'هذه الدفعة ليست بانتظار استرداد أو مجمّدة.'], 409);
            }

            $booking = DB::table('property_user')->where('id', $transaction->property_user_id)->lockForUpdate()->first();
            $property = $booking ? DB::table('properties')->where('id', $booking->property_id)->first() : null;
            $customer = DB::table('users')->where('id', $transaction->user_id)->lockForUpdate()->first();
            $landlord = $property ? DB::table('users')->where('id', $property->user_id)->lockForUpdate()->first() : null;

            if (!$booking || !$customer || !$landlord) {
                return response()->json(['success' => false, 'message' => 'تعذر استكمال بيانات الاسترداد.'], 422);
            }

            if ((int) $landlord->balance < (int) $transaction->amount) {
                return response()->json(['success' => false, 'message' => 'رصيد المالك لا يغطي قيمة العربون المطلوب استرداده.'], 422);
            }

            $now = now();
            DB::table('users')->where('id', $landlord->id)->decrement('balance', (int) $transaction->amount);
            DB::table('users')->where('id', $customer->id)->increment('balance', (int) $transaction->amount);
            DB::table('transactions')->where('id', $transaction->id)->update([
                'status' => 'rejected',
                'demo_status' => 'refunded_simulated',
                'refunded_at' => $now,
                'refunded_by' => auth()->id(),
                'admin_note' => $validated['admin_note'] ?? null,
                'updated_at' => $now,
            ]);
            DB::table('property_user')->where('id', $booking->id)->update([
                'status' => 'Rejected',
                'updated_at' => $now,
            ]);
            DB::table('reservation_events')->insert([
                'property_user_id' => $booking->id,
                'actor_id' => auth()->id(),
                'event_type' => 'demo_deposit_refunded',
                'from_status' => 'Accepted',
                'to_status' => 'Rejected',
                'metadata' => json_encode(['transaction_id' => $transaction->id, 'amount' => (int) $transaction->amount], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم تنفيذ الاسترداد التجريبي وعكس الرصيد بين الطرفين.',
            ], 200);
        });
    }

    /** يكمل المدير المعاملة الأكاديمية بعد أن يكون العربون قد دفع بالفعل. */
    public function completeReservation(Request $request, $bookingId)
    {
        return DB::transaction(function () use ($bookingId) {
            $booking = DB::table('property_user')->where('id', $bookingId)->lockForUpdate()->first();

            if (!$booking) {
                return response()->json(['success' => false, 'message' => 'الحجز غير موجود.'], 404);
            }

            if ($booking->status !== 'Accepted') {
                return response()->json(['success' => false, 'message' => 'لا يمكن إكمال حجز لا يحمل حالة عربون مدفوع.'], 409);
            }

            if (DB::table('disputes')->where('property_user_id', $booking->id)->whereIn('status', ['open', 'under_review'])->exists()) {
                return response()->json(['success' => false, 'message' => 'لا يمكن الإكمال بينما يوجد نزاع مفتوح.'], 409);
            }

            $payment = DB::table('transactions')
                ->where('property_user_id', $booking->id)
                ->where('payment_method', 'demo')
                ->where('demo_status', 'paid_simulated')
                ->exists();

            if (!$payment) {
                return response()->json(['success' => false, 'message' => 'لا توجد دفعة تجريبية صالحة لإكمال الحجز.'], 409);
            }

            $property = DB::table('properties')->where('id', $booking->property_id)->lockForUpdate()->first();
            $now = now();

            if ($booking->type === 'buy') {
                DB::table('property_user')
                    ->where('property_id', $booking->property_id)
                    ->where('id', '!=', $booking->id)
                    ->whereIn('status', ['Pending', 'Awaiting_Payment'])
                    ->update(['status' => 'Rejected', 'updated_at' => $now]);
                DB::table('properties')->where('id', $property->id)->update(['status' => 'sold', 'updated_at' => $now]);
                $finalStatus = 'Sold';
            } else {
                DB::table('properties')->where('id', $property->id)->update(['status' => 'rented', 'updated_at' => $now]);
                $finalStatus = 'Accepted';
            }

            DB::table('property_user')->where('id', $booking->id)->update(['status' => $finalStatus, 'updated_at' => $now]);
            DB::table('reservation_events')->insert([
                'property_user_id' => $booking->id,
                'actor_id' => auth()->id(),
                'event_type' => 'reservation_completed_by_admin',
                'from_status' => 'Accepted',
                'to_status' => $finalStatus,
                'metadata' => json_encode(['property_status' => $booking->type === 'buy' ? 'sold' : 'rented'], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'تم إكمال الحجز أكاديمياً بقرار الإدارة.',
                'data' => ['property_user_id' => $booking->id, 'status' => $finalStatus],
            ], 200);
        });
    }

}
