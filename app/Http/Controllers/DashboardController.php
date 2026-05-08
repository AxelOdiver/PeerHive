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

    public function search(Request $request)
    {
        $terms = collect(preg_split('/\s+/', trim((string) $request->query('q'))))
            ->filter()
            ->values();

        if ($terms->isEmpty()) {
            return response()->json(['users' => []]);
        }

        $users = User::query()
            ->where('id', '!=', Auth::id())
            ->with(['schedules' => fn ($query) => $query
                ->select('user_id', 'day_index')
                ->orderBy('day_index')
            ])
            ->where(function ($query) use ($terms) {
                foreach ($terms as $term) {
                    $query->where(function ($query) use ($term) {
                        $like = "%{$term}%";

                        $query->where('first_name', 'like', $like)
                            ->orWhere('middle_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('email', 'like', $like);
                    });
                }
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(8)
            ->get();

        return response()->json([
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => trim("{$user->first_name} {$user->middle_name} {$user->last_name}"),
                'initials' => strtoupper(substr($user->first_name, 0, 1) . substr($user->last_name, 0, 1)),
                'availability' => $this->formatAvailabilityDays($user),
                'url' => route('users.profile', $user),
            ]),
        ]);
    }

    private function formatAvailabilityDays(User $user): string
    {
        $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $availableDays = $user->schedules
            ->pluck('day_index')
            ->unique()
            ->sort()
            ->map(fn ($dayIndex) => $days[$dayIndex] ?? null)
            ->filter()
            ->values();

        if ($availableDays->isEmpty()) {
            return 'No weekly availability';
        }

        return 'Available: ' . $availableDays->implode(', ');
    }
}
