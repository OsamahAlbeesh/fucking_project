<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Governorate;
use Illuminate\Http\Request;

class GovernorateCityController extends Controller
{
    public function getGovernorates()
    {
        try {
            $governorates = Governorate::select('id', 'name')->with('cities')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $governorates,
                'message' => 'Governorates retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve governorates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getCities(Request $request)
    {
        $request->validate([
            'governorate_id' => 'required|exists:governorates,id'
        ]);

        $cities = City::where('governorate_id', $request->governorate_id)
            ->get(['id', 'name']);

        return response()->json($cities);
    }
}
