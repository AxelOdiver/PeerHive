<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavoriteController extends Controller
{
    public function toggleFavorite(Request $request)
    {
        $user = Auth::user();
        $itemId = $request->item_id;

        $status = $user->favorites()->toggle($itemId);

        return response()->json([
            'status' => 'success',
            'action' => count($status['attached']) > 0 ? 'swapped' : 'removed'
        ]);
    }

    public function index()
    {
        // Use exactly the same teaching rating aggregates as Dashboard > Top Students.
        $favorites = Auth::user()->favorites()
            ->with('approvedTeachingQualifications')
            ->withCount(['likedBy', 'swaps', 'comments', 'teachingRatings'])
            ->withAvg('teachingRatings', 'stars')
            ->get();
        $favoritedIds = $favorites->pluck('id')->all();
        $likedIds = Auth::user()->likes->pluck('id')->all();

        return view('favorites', compact('favorites', 'favoritedIds', 'likedIds'));
    }
}
