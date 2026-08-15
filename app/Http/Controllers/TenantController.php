<?php

namespace App\Http\Controllers;

use App\Models\Flat;
use App\Models\FlatReview;
use App\Models\FlatUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB as FacadesDB;

class TenantController extends Controller
{
    //* public function reserveFlat(Request $request){
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

    public function reserveFlat(Request $request){
    // 1. التحقق من البيانات القادمة من الفرونت إند
    $request->validate([
        'flat_id' => 'required|exists:flats,id',
        'start_date' => 'required|date',
        'end_date' => 'required|date|after:start_date',
        'type' => 'required|in:rent,buy' // تحديد هل الطلب إيجار أم شراء لشحن السعر المناسب في Stripe
    ]);

    $user = auth()->user();
    $flat = Flat::findOrFail($request->flat_id);

    // 2. التحقق من توثيق حساب المستأجر
    if ($user->verified_status != 'approved'){
        return response()->json([
            'message' => 'Your Account has not yet been Approved'
        ], 403);
    }

    // 3. التحقق من أن الشقة لم يتم بيعها مسبقاً لشخص آخر
    $isSold = DB::table('flat_user')
        ->where('flat_id', $request->flat_id)
        ->where('type', 'buy')
        ->where('status', 'Sold')
        ->exists();

    if ($isSold){
        return response()->json([
            'message' => 'لا يُمكنك حجز هذه الشقة لقد تمَّ بيعها بالفعل ...',
        ], 410);
    }

    /* ملاحظة: تم حذف شرط ($user->balance < $flat->rent_price)
       لأن الدفع أصبح خارجياً ومباشراً عبر بطاقات الائتمان (Stripe)
    */

    // 4. إنشاء طلب الحجز بحالة معلقة (Pending) بانتظار الدفع
    $booking = FlatUser::create([
        'user_id' => $user->id,
        'flat_id' => $flat->id,
        'start_date' => $request->start_date,
        'end_date' => $request->end_date,
        'type' => $request->type,
        'status' => 'Pending'
    ]);

    return response()->json([
        'success' => true,
        'message' => 'تم تسجيل طلب الحجز بنجاح، يرجى الانتقال لتوليد رابط الدفع لإتمام المعاملة المالية',
        'flat_user_id' => $booking->id // هذا المعرّف مهم جداً للفرونت إند ليستدعي به الـ Stripe Controller
    ], 201);
}

    public function buyFlat(Request $request) {
    $request->validate([
        'flat_id' => 'required|exists:flats,id',
    ]);

    $user = auth()->user();
    $flat = Flat::findOrFail($request->flat_id);

    if ($user->verified_status != 'approved') {
        return response()->json(['message' => 'Your Account has not yet been Approved'], 403);
    }

    $isSold = DB::table('flat_user')
        ->where('flat_id', $request->flat_id)
        ->where('type', 'buy')
        ->where('status', 'Sold')
        ->exists();

    if ($user->balance < $flat->price) {
        return response()->json([
            'message' => 'رصيدك الحالي (' . $user->balance . ') غير كافٍ لشراء هذه الشقة بسعر (' . $flat->price . ')'
        ], 400);
    }

    if ($isSold) {
        return response()->json(['message' => 'عذراً، هذه الشقة تم بيعها مسبقاً وليست متاحة للعرض'], 410);
    }

    $hasPendingOrder = DB::table('flat_user')
        ->where('flat_id', $request->flat_id)
        ->where('user_id', $user->id)
        ->where('status', 'Pending')
        ->where('type', 'buy')
        ->exists();

    if ($hasPendingOrder) {
        return response()->json(['message' => 'لديك طلب شراء قيد الانتظار لهذه الشقة بالفعل'], 409);
    }

   FlatUser::create([
        'user_id'    => $user->id,
        'flat_id'    => $request->flat_id,
        'start_date' => now(),
        'end_date'   => now(),
        'status'     => 'Pending',
        'type'       => 'buy',
    ]);

    return response()->json([
        'message' => 'تم إرسال طلب الشراء بنجاح، بانتظار موافقة المالك لإتمام المعاملة المالية'
    ], 201);
}

    public function updateReservation(Request $request) {
    $request->validate([
        'flat_id' => 'required|exists:flats,id',
        'start_date' => 'required|date',
        'end_date' => 'required|date|after:start_date',
    ]);

    $user = auth()->user();
    if ($user->verified_status!='approved'){
        return response()->json([
            'message'=>'Your Accout has not yet been Approved'
            ],403);
    }
    $existing = DB::table('flat_user')
        ->where('user_id', $user->id)
        ->where('flat_id', $request->flat_id)
        ->first();

    if (!$existing) {
        return response()->json(['message' => 'لا يوجد حجز سابق لهذه الشقة'], 404);
    }

    $conflict = DB::table('flat_user')
        ->where('flat_id', $request->flat_id)
        ->where('user_id', '!=', $user->id)
        ->where(function ($query) use ($request) {
            $query->whereBetween('start_date', [$request->start_date, $request->end_date])
                  ->orWhereBetween('end_date', [$request->start_date, $request->end_date])
                  ->orWhere(function ($q) use ($request) {
                      $q->where('start_date', '<=', $request->start_date)
                        ->where('end_date', '>=', $request->end_date);
                  });
        })
        ->where('status', 'Accepted')
        ->exists();

    if ($conflict) {
        return response()->json(['message' => 'الشقة محجوزة في هذه الفترة'], 409);
    }

    $user->bookings()->updateExistingPivot($request->flat_id, [
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
        'flat_id' => 'required|exists:flats,id',
    ]);

    $user = auth()->user();
    if ($user->verified_status!='approved'){
        return response()->json([
            'message'=>'Your Accout has not yet been Approved'
            ],403);
    }
    $existing = DB::table('flat_user')
        ->where('user_id', $user->id)
        ->where('flat_id', $request->flat_id)
        ->first();

    if (!$existing) {
        return response()->json(['message' => 'لا يوجد حجز لهذه الشقة'], 404);
    }

    $user->bookings()->detach($request->flat_id);

    return response()->json(['
        message' => 'تم إلغاء الحجز بنجاح'
    ], 200);
}


    public function rateFlat(Request $request){
        $request->validate([
            'flat_id' => 'required|exists:flats,id',
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string',
        ]);

        $user = auth()->user();
        if ($user->verified_status!='approved'){
            return response()->json([
                'message'=>'Your Accout has not yet been Approved'
                ],403);
        }
        $reservation = DB::table('flat_user')
            ->where('flat_id', $request->flat_id)
            ->where('user_id', $user->id)
            ->where('status', 'Accepted')
            ->first();

        if (!$reservation) {
            return response()->json([
                'message' => 'لا يمكنك تقييم شقة لم تستأجرها فعليًا'
            ], 403);
        }

        FlatReview::updateOrCreate(
            [
                'flat_id' => $request->flat_id,
                'user_id' => $user->id,
            ],
            [
                'rating' => $request->rating,
                'review' => $request->review,
            ]
        );
        return response()->json(['message' => 'تم تسجيل تقييمك الأخير بنجاح']);
    }

    public function getMyReservation(){
    $user = auth()->user();

    if ($user->verified_status !== 'approved') {
        return response()->json([
            'message' => 'Your Account has not yet been Approved'
        ], 403);
    }

    $bookings = $user->bookings()->with('flat')->get()->map(function ($flat) {
        return [
            'flat_id'=> $flat->id,
            'details'=> $flat->details,
            'status'=> $flat->pivot->status,
        ];
    });

    return response()->json([
        'message'  => 'Here are all your reservations',
        'bookings' => $bookings
    ], 200);
}

}
