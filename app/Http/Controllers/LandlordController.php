<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB as FacadesDB;
use Illuminate\Support\Facades\Storage;

class LandlordController extends Controller
{
    public function addProperty (Request $request){

        $request->validate([
            'price' => 'required_without:rent_price|numeric|min:0',
            'rent_price' => 'required_without:price|numeric|min:0',
            'location' => 'required:string',
            'details'=>'required|string',
            'city_id'=>'required|exists:cities,id',
            'category'=>'required|in:flat,villa,land,shop,office',
            'governorate_id'=>'required|exists:governorates,id',
            'property_image' => 'image|mimes:jpg,jpeg,png|max:2048'
        ]);

       $user_id = Auth::user()->id;
       if (Auth::user()->verified_status!='approved'){
        return response()->json([
            'message'=>'Your Accout has not yet been Approved'
            ]);
        }
    $propertyPath='';
       if ($request->hasFile('property_image'))
            $propertyPath = '/storage/' . $request->file('property_image')->store('photos','public');


        $property = Property::create([
            'price'=>$request->price,
            'rent_price'=>$request->rent_price,
            'details'=>$request->details,
            'city_id'=>$request->city_id,
            'governorate_id'=>$request->governorate_id,
            'property_image'=>$propertyPath,
            'user_id'=>$user_id,
            'location'=>$request->location,
            'category'=>$request->category
        ]);

        return response()->json([
            'message'=>'Adding Property Successfully <3',
            'property Information'=>$property
        ], 200);

    }


      public function updatePropertyDetails(Request $request, $id){

          $property = Property::find($id);
        if (Auth::user()->verified_status!='approved'){
        return response()->json([
            'message'=>'Your Accout has not yet been Approved'
            ]);
        }
        if (Auth::user()->id !== $property->user_id) {
        abort(403, 'عذراً، لا تملك الصلاحية لتعديل هذه الشقة.');
        }
        if (!$property) {
            return response()->json([
                'success' => false,
                'message' => 'Property not found'
            ], 404);
        }


        $validated = $request->validate([
            'user_id' => 'exists:users,id',
            'governorate_id' => 'nullable|exists:governorates,id',
            'city_id' => 'nullable|exists:cities,id',
            'location' => 'required:string',

            'details' => 'nullable|string|min:10|max:1000',
            'price' => 'nullable|integer|min:10000',
            'rent_price' => 'nullable|integer|min:100',
            'property_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'category'=>'nullable|in:flat,villa,land,shop,office',

        ]);



        if ($request->hasFile('property_image')) {
            if ($property->property_image && Storage::disk('public')->exists($property->property_image)) {
                Storage::disk('public')->delete($property->property_image);
            }

            $imagePath = $request->file('property_image')->store('property_images', 'public');
            $validated['property_image'] = $imagePath;
        }

        $property->user_id =$validated['user_id'] ?? $property->user_id;
        $property->governorate_id =$validated['governorate_id'] ?? $property->governorate_id;
        $property->city_id =$validated['city_id'] ?? $property->city_id;
        $property->details =$validated['details'] ?? $property->details;
        $property->price =$validated['price'] ?? $property->price;
        $property->location =$validated['location'] ?? $property->location;
        $property->rent_price =$validated['rent_price'] ?? $property->rent_price;
        $property->property_image =$validated['property_image'] ?? $property->property_image;
        $property->category =$validated['category'] ?? $property->category;

        $property->save();

        return response()->json([
            'success' => true,
            'message' => 'Property updated successfully <3',
            'property information : ' =>$property
        ]);
}

public function pendingReservations() {
    $landlord = auth()->user();

    $reservations = DB::table('property_user')
        ->join('properties', 'property_user.property_id', '=', 'properties.id')
        ->join('users', 'property_user.user_id', '=', 'users.id')
        ->where('properties.user_id', $landlord->id)
        ->where('property_user.status', 'Pending')
        ->select(
            'property_user.id',
            'property_user.user_id as renter_id',
            'property_user.start_date',
            'property_user.end_date',
            'users.first_name as renter_first_name',
            'users.last_name as renter_last_name',
            'properties.id as property_id',
            'property_user.type as request_type'
        )
        ->get();

    return response()->json([
        'message'=>'Your Reservations Requests',
        'data'=>$reservations,
        'landlord : '=>$landlord
        ]);
}



public function respondToReservation(Request $request){
    $request->validate([
        'id' => 'required|exists:property_user,id',
        'status' => 'required|in:Accepted,Rejected',
    ]);

    $landlord = auth()->user();
    if ($landlord->verified_status != 'approved'){
        return response()->json([
            'message' => 'Your Account has not yet been Approved'
        ], 403);
    }

    $reservation = DB::table('property_user')
        ->join('properties', 'property_user.property_id', '=', 'properties.id')
        ->where('property_user.id', $request->id)
        ->where('properties.user_id', $landlord->id)
        ->select('property_user.*')
        ->first();

    if (!$reservation) {
        return response()->json(['message' => 'هذا الحجز غير موجود أو لا يخصك'], 403);
    }

    if ($reservation->status !== 'Pending') {
        return response()->json(['message' => 'تم معالجة هذا الطلب مسبقاً، لا يمكن تعديله الآن'], 400);
    }

    //* Accepted

    if ($request->status == 'Accepted') {
        DB::table('property_user')->where('id', $reservation->id)->update([
            'status' => 'Awaiting_Payment'
        ]);

    //! Notification Require

        return response()->json([
            'success' => true,
            'message' => 'تم قبول الطلب بنجاح، وتحويل حالته إلى (بانتظار الدفع)، وتم إشعار المستأجر لإتمام العملية.'
        ], 200);
    }

    //* Rejected

    DB::table('property_user')
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
    $reservations = DB::table('property_user')
        ->join('properties', 'property_user.property_id', '=', 'properties.id')
        ->join('users', 'property_user.user_id', '=', 'users.id')
        ->where('properties.user_id', $landlord->id)
        ->select(
            'property_user.id as reservation_id',
            'property_user.start_date',
            'property_user.end_date',
            'property_user.status',
            'users.first_name as renter_first_name',
            'users.last_name as renter_last_name',
            'properties.details',
            'properties.price',
            'properties.id as property_id'
        )
        ->orderByDesc('property_user.created_at')
        ->get();

    return response()->json([
        'here all your Reservations : '=>$reservations
    ]);
}


}
