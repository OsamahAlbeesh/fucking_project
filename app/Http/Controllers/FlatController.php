<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Flat;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use function PHPUnit\Framework\isEmpty;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage as FacadesStorage;

class FlatController extends Controller
{
    public function getCurrentUserFlats()
    {
        $user = Auth::user();

        $flats = Flat::get();

        if ($user && $user->role == 'tenant') {
            $favoriteIds = $user->favorites()->pluck('flats.id')->toArray();
            $flats->each(function ($flat) use ($favoriteIds) {
                $flat->is_favorite = in_array($flat->id, $favoriteIds);
            });
        } else {
            $flats->each(function ($flat) {
                $flat->is_favorite = false;
            });
        }

        return response()->json($flats, 200);
    }

    public function getFlatDetails($id){
        $flat = Flat::with(['reviews', 'owner'])
              ->whereDoesntHave('bookings', function($query) {
              $query->where('status', 'Sold');
              })
        ->get();
        //  متوسط التقييم
        $averageRating = $flat->reviews()->avg('rating');

        return response()->json([
            'message'=>'flat details : ',
            'id' => $flat->id,
            'title' => $flat->title,
            'price' => $flat->price,
            'details' => $flat->details,
            'city_id' => $flat->city_id,
            'governorate_id' => $flat->governorate_id,
            'flat_image' => $flat->flat_image,
            'owner_name' => $flat->owner->first_name,
            'owner_id' => $flat->user_id,
            'average_rating' => round($averageRating, 2), // التقييم النهائي
            'reviews_count' => $flat->reviews()->count(), // عدد التقييمات
        ]);
    }



    public function getAllFlats()
    {
        $flats = Flat::with('reviews')->get();

        $flatsData = $flats->map(function ($flat) {
            return [
                'id' => $flat->id,
                'title' => $flat->title,
                'price' => $flat->price,
                'details' => $flat->details,
                'city_id' => $flat->city_id,
                'governorate_id' => $flat->governorate_id,
                'flat_image' => $flat->flat_image,
                'owner_id' => $flat->user_id,
                'average_rating' => round($flat->reviews()->avg('rating'), 2),
                'reviews_count' => $flat->reviews()->count(),
            ];
        });

        return response()->json($flatsData);
    }


    public function search(Request $request)
    {
        $price_min=$request->input('price_min');
        $price_max=$request->input('price_max');
        $flat= Flat::query();
        if(filled($price_min)&&filled($price_max))
        {
                $flat->where('price', '>=', $price_min)
                    ->where('price', '<=', $price_max);
        }

        if(filled($request->input('city_id')))
        {
            $flat->where('city_id', (int) $request->input('city_id'));
        }

        if(filled($request->input('governorate_id')))
        {
            $flat->where('governorate_id', (int) $request->input('governorate_id'));
        }


        $flat = $flat->get();
        if ($flat->isEmpty()){
            return response()->json(["message" => "not found"],404);
        }
        return response()->json($flat,200);

    }


    public function getFlatRating($flatId)
    {
        $averageRating = DB::table('flat_reviews')
            ->where('flat_id', $flatId)
            ->avg('rating'); // يحسب المتوسط مباشرة

        return response()->json([
            'flat_id' => $flatId,
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
