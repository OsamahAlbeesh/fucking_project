<?php

namespace App\Http\Controllers;

use App\Models\Flat;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB as FacadesDB;
use Illuminate\Support\Facades\Storage;

class LandlordController extends Controller
{
    public function addFlat (Request $request){

        $request->validate([
            'price' => 'required_without:rent_price|numeric|min:0',
            'rent_price' => 'required_without:price|numeric|min:0',
            'location' => 'required:string',
            'details'=>'required|string',
            'city_id'=>'required|exists:cities,id',
            'governorate_id'=>'required|exists:governorates,id',
            'flat_image' => 'image|mimes:jpg,jpeg,png|max:2048'
        ]);

       $user_id = Auth::user()->id;
       if (Auth::user()->verified_status!='approved'){
        return response()->json([
            'message'=>'Your Accout has not yet been Approved'
            ]);
        }
    $flatPath='';
       if ($request->hasFile('flat_image'))
            $flatPath = '/storage/' . $request->file('flat_image')->store('photos','public');


        $flat = Flat::create([
            'price'=>$request->price,
            'details'=>$request->details,
            'city_id'=>$request->city_id,
            'governorate_id'=>$request->governorate_id,
            'flat_image'=>$flatPath,
            'user_id'=>$user_id
        ]);

        return response()->json([
            'message'=>'Adding Flat Successfully <3',
            'flat Information'=>$flat
        ], 200);

    }


      public function updateFlatDetails(Request $request, $id){

        $flat = Flat::find($id);
        if (Auth::user()->verified_status!='approved'){
        return response()->json([
            'message'=>'Your Accout has not yet been Approved'
            ]);
        }
        if (Auth::user()->id !== $flat->user_id) {
        abort(403, 'عذراً، لا تملك الصلاحية لتعديل هذه الشقة.');
        }
        if (!$flat) {
            return response()->json([
                'success' => false,
                'message' => 'Flat not found'
            ], 404);
        }


        $validated = $request->validate([
            'user_id' => 'exists:users,id',
            'governorate_id' => 'nullable|exists:governorates,id',
            'city_id' => 'nullable|exists:cities,id',
            'details' => 'nullable|string|min:10|max:1000',
            'price' => 'nullable|integer|min:10000',
            'rent_price' => 'nullable|integer|min:100',
            'flat_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);



        if ($request->hasFile('flat_image')) {
            if ($flat->flat_image && Storage::disk('public')->exists($flat->flat_image)) {
                Storage::disk('public')->delete($flat->flat_image);
            }

            $imagePath = $request->file('flat_image')->store('flat_images', 'public');
            $validated['flat_image'] = $imagePath;
        }

        $flat->user_id =$validated['user_id'] ?? $flat->user_id;
        $flat->governorate_id =$validated['governorate_id'] ?? $flat->governorate_id;
        $flat->city_id =$validated['city_id'] ?? $flat->city_id;
        $flat->details =$validated['details'] ?? $flat->details;
        $flat->price =$validated['price'] ?? $flat->price;
        $flat->rent_price =$validated['rent_price'] ?? $flat->rent_price;
        $flat->flat_image =$validated['flat_image'] ?? $flat->flat_image;

        $flat->save();

        return response()->json([
            'success' => true,
            'message' => 'Flat updated successfully <3',
            'falt information : ' =>$flat
        ]);
}

public function pendingReservations() {
    $landlord = auth()->user();

    $reservations = DB::table('flat_user')
        ->join('flats', 'flat_user.flat_id', '=', 'flats.id')
        ->join('users', 'flat_user.user_id', '=', 'users.id')
        ->where('flats.user_id', $landlord->id)
        ->where('flat_user.status', 'Pending')
        ->select(
            'flat_user.id',
            'flat_user.user_id as renter_id',
            'flat_user.start_date',
            'flat_user.end_date',
            'users.first_name as renter_first_name',
            'users.last_name as renter_last_name',
            'flats.id as flat_id',
            'flat_user.type as request_type'
        )
        ->get();

    return response()->json([
        'message'=>'Your Reservations Requests',
        $reservations,
        'landlord : '=>$landlord
        ]);
}



public function respondToReservation(Request $request){
    $request->validate([
        'id' => 'required|exists:flat_user,id',
        'status' => 'required|in:Accepted,Rejected',
    ]);

    $landlord = auth()->user();
    if ($landlord->verified_status != 'approved'){
        return response()->json([
            'message' => 'Your Account has not yet been Approved'
        ], 403);
    }

    $reservation = DB::table('flat_user')
        ->join('flats', 'flat_user.flat_id', '=', 'flats.id')
        ->where('flat_user.id', $request->id)
        ->where('flats.user_id', $landlord->id)
        ->select('flat_user.*')
        ->first();

    if (!$reservation) {
        return response()->json(['message' => 'هذا الحجز غير موجود أو لا يخصك'], 403);
    }

    if ($reservation->status !== 'Pending') {
        return response()->json(['message' => 'تم معالجة هذا الطلب مسبقاً، لا يمكن تعديله الآن'], 400);
    }

    //* Accepted

    if ($request->status == 'Accepted') {
        DB::table('flat_user')->where('id', $reservation->id)->update([
            'status' => 'Awaiting_Payment'
        ]);

    //! Notification Require

        return response()->json([
            'success' => true,
            'message' => 'تم قبول الطلب بنجاح، وتحويل حالته إلى (بانتظار الدفع)، وتم إشعار المستأجر لإتمام العملية.'
        ], 200);
    }

    //* Rejected

    DB::table('flat_user')
        ->where('id', $request->id)
        ->update(['status' => 'Rejected']);

    return response()->json([
        'success' => true,
        'message' => 'تم رفض طلب الحجز بنجاح، وتظل الشقة متاحة للآخرين.'
    ], 200);
}


public function getAllReservations()
{
    $landlord = auth()->user();
    if (Auth::user()->verified_status!='approved'){
        return response()->json([
            'message'=>'Your Accout has not yet been Approved'
            ]);
    }
    $reservations = DB::table('flat_user')
        ->join('flats', 'flat_user.flat_id', '=', 'flats.id')
        ->join('users', 'flat_user.user_id', '=', 'users.id')
        ->where('flats.user_id', $landlord->id)
        ->select(
            'flat_user.id as reservation_id',
            'flat_user.start_date',
            'flat_user.end_date',
            'flat_user.status',
            'users.first_name as renter_first_name',
            'users.last_name as renter_last_name',
            'flats.details',
            'flats.price',
            'flats.id as flat_id'
        )
        ->orderByDesc('flat_user.created_at')
        ->get();

    return response()->json([
        'here all your Reservations : '=>$reservations
    ]);
}


}
