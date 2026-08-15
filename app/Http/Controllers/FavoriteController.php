<?php

namespace App\Http\Controllers;

use App\Models\Flat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{

    public function toggleFavoriteAlt(Flat $flat)
    {
        $user = Auth::user();

        // Toggle using syncWithoutDetaching and detach
        $favorited = $user->favorites()->toggle($flat->id);

        // $favorited returns ['attached' => [], 'detached' => []]
        $wasAdded = !empty($favorited['attached']);

        return response()->json([
            'message' => $wasAdded ? 'Flat added to favorites' : 'Flat removed from favorites',
            'is_favorited' => $wasAdded,
        ], 200);
    }

    public function getMyFavoritesSimple()
    {
        $user = Auth::user();

        $favorites = $user->favorites()
            ->select('flats.*', 'favorites.created_at as favorited_at')
            ->orderBy('favorites.created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Favorite flats retrieved successfully',
            'count' => $favorites->count(),
            'data' => $favorites
        ], 200);
    }
}

