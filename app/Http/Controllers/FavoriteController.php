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
        $favorites = Auth::user()->favorites()->with('approvedTeachingQualifications')->withCount(['likedBy', 'swaps'])->get();
        $likedIds  = Auth::user()->likes->pluck('id')->toArray();

        return view('favorites', compact('favorites', 'likedIds'));
    }
}
