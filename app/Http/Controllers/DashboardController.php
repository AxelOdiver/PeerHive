<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $currentUser = Auth::user();

        $topstudents = User::where('id', '!=', $currentUser->id)
            ->withCount(['likedBy', 'swaps'])
            ->orderByDesc('liked_by_count')
            ->take(3)
            ->get();

        $excludeIds = $topstudents->pluck('id');

        $students = User::where('id', '!=', $currentUser->id)
            ->whereNotIn('id', $excludeIds)
            ->withCount(['likedBy', 'swaps'])
            ->take(12)
            ->get();

        $favoritedIds = $currentUser->favorites->pluck('id')->toArray();

        // IDs the current user has liked
        $likedIds = $currentUser->likes->pluck('id')->toArray();

        return view('dashboard', compact('topstudents', 'students', 'favoritedIds', 'likedIds'));
    }
}
