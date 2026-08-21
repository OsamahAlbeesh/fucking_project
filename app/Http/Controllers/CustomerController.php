<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyReview;
use App\Models\PropertyUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB as FacadesDB;

class CustomerController extends Controller
{
    //* public function reserveProperty(Request $request){
    // $request->validate([
    //     'flat_id' => 'required|exists:flats,id',
    //     'start_date' => 'required|date',
    //     'end_date' => 'required|date|after:start_date',
    // ]);

    // $user = auth()->user();
    // $flat = Flat::findOrFail($request->flat_id);

    // if ($user->verified_status!='approved'){
    //     return response()->json([
    //         'message'=>'Your Accout has not yet been Approved'
    //         ],403);
    // }

    // $isSold = DB::table('flat_user')
    // ->where('flat_id',$request->flat_id)
    // ->where('type','buy')
    // ->where('status','Sold')
    // ->exists();

    // if ($isSold){
    //     return response()->json([
    //         'message'=>'لا يُمكنك حجز هذه الشقة لقد تمَّ بيعها بالفعل ...',
    //     ],410);
    // }

    // if ($user->balance < $flat->rent_price) {
    //     return response()->json([
    //         'message' => 'رصيدك الحالي (' . $user->balance . ') غير كافٍ لاستئجار هذه الشقة بسعر (' . $flat->rent_price . ')'
    //     ], 400);
    // }

    // $conflict = DB::table('flat_user')
    //     ->where('flat_id', $request->flat_id)
    //     ->where(function ($query) use ($request) {
    //         $query->whereBetween('start_date', [$request->start_date, $request->end_date])
    //               ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
    //               ->orWhere(function ($q) use ($request) {
    //                   $q->where('start_date', '<=', $request->start_date)
    //                     ->where('end_date', '>=', $request->end_date);
    //               });
    //     })
    //     ->where('status','Accepted')
    //     ->exists();

    // if ($conflict) {
    //     return response()->json(['message' => 'الشقة محجوزة في هذه الفترة'], 409);
    // }

    // $hasPendingOrder = DB::table('flat_user')
    //     ->where('flat_id', $request->flat_id)
    //     ->where('user_id', $user->id)
    //     ->where('status', 'Pending')
    //     ->where('type', 'rent')
    //     ->exists();

    // if ($hasPendingOrder) {
    //     return response()->json(['message' => 'لديك طلب استئجار قيد الانتظار لهذه الشقة بالفعل'], 409);
    // }

    // FlatUser::create([
    //     'user_id'    => $user->id,
    //     'flat_id'    => $request->flat_id,
    //     'start_date' => $request->start_date,
    //     'end_date'   => $request->end_date,
    //     'status'     => 'Pending',
    //     'type'       => 'rent',
    // ]);



    // return response()->json([
    //     'message' =>  'تم إرسال طلب الحجز بنجاح بانتظار مُوافقة صاحب الشقة لإتمام المُعامَلة الماليّة...'
    //     ], 201);
    // }

    public function reserveProperty(Request $request){
        // 1. التحقق من البيانات القادمة من الفرونت إند
        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'type' => 'required|in:rent,buy' // تحديد هل الطلب إيجار أم شراء لحساب عربون الحجز المناسب
        ]);

        $user = auth()->user();
        $property = Property::findOrFail($request->property_id);
        if ((int) $property->user_id === (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكنك إنشاء حجز لعقار تملكه أنت.',
            ], 403);
        }
        // 2. التحقق من توثيق حساب المستأجر
        if ($user->verified_status != 'approved'){
            return response()->json([
                'message' => 'Your Account has not yet been Approved'
            ], 403);
        }

        // 3. تمنع أي عملية شراء قيد الدفع أو مدفوع عربونها أو مكتملة حجوزاتٍ متعارضة.
        $hasActivePurchase = DB::table('property_user')
            ->where('property_id', $request->property_id)
            ->where('type', 'buy')
            ->whereIn('status', [
                'Awaiting_Payment',
                'Accepted',
                'Sold',
            ])
            ->exists();

        if ($hasActivePurchase){
            return response()->json([
                'message' => 'لا يمكن حجز هذا العقار لأن طلب شرائه قيد الدفع أو الإتمام أو تم بيعه بالفعل.',
            ], 410);
        }

        /* لا نتحقق من كامل قيمة الإيجار هنا؛ الدفع التجريبي يتحقق من عربون الحجز فقط. */

        // 4. إنشاء طلب الحجز بحالة معلقة (Pending) بانتظار الدفع
        $booking = PropertyUser::create([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'type' => $request->type,
            'status' => 'Pending'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تسجيل طلب الحجز بنجاح، يرجى الانتقال لتوليد رابط الدفع لإتمام المعاملة المالية',
            'property_user_id' => $booking->id, // يستعمله العميل لاحقاً لاستدعاء الدفع التجريبي
            'user'=>$user
        ], 201);
    }

    public function buyProperty(Request $request) {
        $request->validate([
            'property_id' => 'required|exists:properties,id',
        ]);

        $user = auth()->user();
        $property = Property::findOrFail($request->property_id);
        if ((int) $property->user_id === (int) $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكنك إنشاء طلب شراء لعقار تملكه أنت.',
            ], 403);
        }
        if ($user->verified_status != 'approved') {
            return response()->json(['message' => 'Your Account has not yet been Approved'], 403);
        }

        $hasActivePurchase = DB::table('property_user')
            ->where('property_id', $request->property_id)
            ->where('type', 'buy')
            ->whereIn('status', [
                'Awaiting_Payment',
                'Accepted',
                'Sold',
            ])
            ->exists();

        // لا نتحقق من السعر الكامل هنا؛ الرصيد المطلوب هو عربون الحجز فقط ويُحسب عند الدفع التجريبي.

        if ($hasActivePurchase) {
            return response()->json(['message' => 'عذراً، هذا العقار غير متاح لأن طلب شرائه قيد الدفع أو الإتمام أو تم بيعه بالفعل.'], 410);
        }

        $hasPendingOrder = DB::table('property_user')
            ->where('property_id', $request->property_id)
            ->where('user_id', $user->id)
            ->where('status', 'Pending')
            ->where('type', 'buy')
            ->exists();

        if ($hasPendingOrder) {
            return response()->json(['message' => 'لديك طلب شراء قيد الانتظار لهذه الشقة بالفعل'], 409);
        }

        PropertyUser::create([
            'user_id'    => $user->id,
            'property_id'    => $request->property_id,
            'start_date' => now(),
            'end_date'   => now(),
            'status'     => 'Pending',
            'type'       => 'buy',
        ]);

        return response()->json([
            'message' => 'تم إرسال طلب الشراء بنجاح، بانتظار موافقة المالك لإتمام المعاملة المالية'
        ], 201);
    }

    public function updateReservation(Request $request, $property_id)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
        ]);

        $user = auth()->user();

        if ($user->verified_status != 'approved') {
            return response()->json([
                'message' => 'Your Account has not yet been Approved'
            ], 403);
        }

        $existing = DB::table('property_user')
            ->where('user_id', $user->id)
            ->where('property_id', $property_id)
            ->first();

        if (!$existing) {
            return response()->json([
                'message' => 'لا يوجد حجز سابق لهذه الشقة'
            ], 404);
        }

        $conflict = DB::table('property_user')
            ->where('property_id', $property_id)
            ->where('user_id', '!=', $user->id)
            ->where(function ($query) use ($request) {
                $query->whereBetween('start_date', [
                    $request->start_date,
                    $request->end_date
                ])
                    ->orWhereBetween('end_date', [
                        $request->start_date,
                        $request->end_date
                    ])
                    ->orWhere(function ($q) use ($request) {
                        $q->where('start_date', '<=', $request->start_date)
                            ->where('end_date', '>=', $request->end_date);
                    });
            })
            ->where('status', 'Accepted')
            ->exists();

        if ($conflict) {
            return response()->json([
                'message' => 'الشقة محجوزة في هذه الفترة'
            ], 409);
        }

        // ✅ هنا التعديل
        $booking = PropertyUser::where('user_id', $user->id)
            ->where('property_id', $property_id)
            ->first();

        if (!$booking) {
            return response()->json([
                'message' => 'لا يوجد حجز سابق لهذه الشقة'
            ], 404);
        }

        $booking->update([
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'status' => 'Pending',
        ]);

        return response()->json([
            'message' => 'تم تعديل الحجز بنجاح بانتظار موافقة صاحب الشقة على التعديل ...'
        ], 200);
    }

    public function cancelReservation(Request $request)
    {
        $request->validate([
            'property_user_id' => 'required|exists:property_user,id',
            'reason' => 'nullable|string|max:1000',
        ]);

        $user = auth()->user();

        if ($user->verified_status != 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Your Account has not yet been Approved'
            ], 403);
        }

        $existing = PropertyUser::where('id', $request->property_user_id)
            ->where('user_id', $user->id)
            ->first();

        if (!$existing) {
            return response()->json([
                'success' => false,
                'message' => 'لا يوجد هذا الحجز للمستخدم الحالي'
            ], 404);
        }

        if (!in_array($existing->status, ['Pending', 'Awaiting_Payment'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'لا يمكن إلغاء هذا الحجز بعد دفع العربون. استخدم طلب الاسترداد أو النزاع عند الحاجة.',
            ], 409);
        }

        // لا نحذف السجل؛ نستعمل الحالة القديمة Rejected كإلغاء متوافق مع المخطط الحالي.
        $fromStatus = $existing->status;
        $existing->update(['status' => 'Rejected']);

        DB::table('reservation_events')->insert([
            'property_user_id' => $existing->id,
            'actor_id' => $user->id,
            'event_type' => 'reservation_cancelled_by_customer',
            'from_status' => $fromStatus,
            'to_status' => 'Rejected',
            'metadata' => json_encode(['reason' => $request->reason], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إلغاء الحجز مع الاحتفاظ بسجله للتدقيق.'
        ], 200);
    }

    public function rateProperty(Request $request){
        $request->validate([
            'property_id' => 'required|exists:properties,id',
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string',
        ]);

        $user = auth()->user();
        if ($user->verified_status!='approved'){
            return response()->json([
                'message'=>'Your Account has not yet been Approved'
            ],403);
        }
        $reservation = DB::table('property_user')
            ->where('property_id', $request->property_id)
            ->where('user_id', $user->id)
            ->where('status', 'Accepted')
            ->first();

        if (!$reservation) {
            return response()->json([
                'message' => 'لا يمكنك تقييم شقة لم تستأجرها فعليًا'
            ], 403);
        }

        PropertyReview::updateOrCreate(
            [
                'property_id' => $request->property_id,
                'user_id' => $user->id,
            ],
            [
                'rating' => $request->rating,
                'review' => $request->review,
            ]
        );
        return response()->json(['message' => 'تم تسجيل تقييمك الأخير بنجاح']);
    }

    public function getMyReservation()
    {
        $user = auth()->user();

        if ($user->verified_status !== 'approved') {
            return response()->json([
                'message' => 'Your Account has not yet been Approved'
            ], 403);
        }

        $bookings = $user->bookings()
            ->with('property')
            ->get()
            ->map(function ($booking) {

                return [
                    'property_user_id' => $booking->id,
                    'property_id' => $booking->property_id,
                    'details' => $booking->property->details,
                    'status' => $booking->status,
                    'start_date' => $booking->start_date,
                    'end_date' => $booking->end_date,
                    'type' => $booking->type,
                ];
            });

        return response()->json([
            'message' => 'Here are all your reservations',
            'bookings' => $bookings
        ], 200);
    }
}
