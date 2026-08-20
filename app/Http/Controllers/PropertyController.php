<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Property;
use http\Env\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use function PHPUnit\Framework\isEmpty;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage as FacadesStorage;

class PropertyController extends Controller
{
    public function getCurrentUserProperties()
    {
        $user = Auth::user();

        $properties = Property::get();

        if ($user && $user->role == 'customer') {
            $favoriteIds = $user->favorites()->pluck('properties.id')->toArray();
            $properties->each(function ($property) use ($favoriteIds) {
                $property->is_favorite = in_array($property->id, $favoriteIds);
            });
        } else {
            $properties->each(function ($property) {
                $property->is_favorite = false;
            });
        }

        return response()->json($properties, 200);
    }

    public function getPropertyDetails($id){
        $property = Property::with(['reviews', 'owner'])
              ->whereDoesntHave('bookings', function($query) {
              $query->where('status', 'Sold');
              })
            ->find($id);
        if (!$property){
            return response()->json([
                'message'=>'property details not found' ],404);
        }
        $averageRating = $property->reviews()->avg('rating');

        return response()->json([
            'message'=>'property details : ',
            'id' => $property->id,
            'title' => $property->title,
            'price' => $property->price,
            'details' => $property->details,
            'city_id' => $property->city_id,
            'governorate_id' => $property->governorate_id,
            'property_image' => $property->property_image,
            'owner_name' => $property->owner->first_name,
            'owner_id' => $property->user_id,
            'average_rating' => round($averageRating, 2), // التقييم النهائي
            'reviews_count' => $property->reviews()->count(), // عدد التقييمات
        ]);
    }



    public function getAllProperties()
    {
        $properties = Property::with('reviews')->get();

        $propertiesData = $properties->map(function ($property) {
            return [
                'id' => $property->id,
                'title' => $property->title,
                'price' => $property->price,
                'details' => $property->details,
                'city_id' => $property->city_id,
                'governorate_id' => $property->governorate_id,
                'property_image' => $property->property_image,
                'owner_id' => $property->user_id,
                'average_rating' => round($property->reviews()->avg('rating'), 2),
                'reviews_count' => $property->reviews()->count(),
            ];
        });

        return response()->json($propertiesData);
    }


    public function search(Request $request)
    {
        $price_min=$request->input('price_min');
        $price_max=$request->input('price_max');
        $property= Property::query();
        if(filled($price_min)&&filled($price_max))
        {
            $property->where('price', '>=', $price_min)
                    ->where('price', '<=', $price_max);
        }

        if(filled($request->input('city_id')))
        {
            $property->where('city_id', (int) $request->input('city_id'));
        }

        if(filled($request->input('governorate_id')))
        {
            $property->where('governorate_id', (int) $request->input('governorate_id'));
        }


        $property = $property->get();
        if ($property->isEmpty()){
            return response()->json(["message" => "not found"],404);
        }
        return response()->json($property,200);

    }


    public function getPropertyRating($propertyId)
    {
        $averageRating = DB::table('property_reviews')
            ->where('property_id', $propertyId)
            ->avg('rating'); // يحسب المتوسط مباشرة

        return response()->json([
            'property_id' => $propertyId,
            'average_rating' => round($averageRating, 2) // تقريب الرقم
        ]);
    }

    // public function toggle(Flat $flat)
    // {
    //     $user = Auth::user();
    //     $user->favorites()->toggle($flat->id);
    //     return back()->with('status', 'تم تحديث قائمة المفضلة');
    // }

}
