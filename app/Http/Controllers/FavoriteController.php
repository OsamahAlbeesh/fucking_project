<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{

    public function toggleFavoriteAlt(Property $property)
    {
        $user = Auth::user();

        // Toggle using syncWithoutDetaching and detach
        $favorited = $user->favorites()->toggle($property->id);

        // $favorited returns ['attached' => [], 'detached' => []]
        $wasAdded = !empty($favorited['attached']);

        return response()->json([
            'message' => $wasAdded ? 'Property added to favorites' : 'Property removed from favorites',
            'is_favorited' => $wasAdded,
        ], 200);
    }

    public function getMyFavoritesSimple()
    {
        $user = Auth::user();

        $favorites = $user->favorites()
            ->select('properties.*', 'favorites.created_at as favorited_at')
            ->orderBy('favorites.created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Favorite property retrieved successfully',
            'count' => $favorites->count(),
            'data' => $favorites
        ], 200);
    }
}

