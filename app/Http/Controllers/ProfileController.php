<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;

class ProfileController extends Controller
{
    // Own profile edit page
    public function index()
    {
        return view('profile.edit');
    }

    // Returns own profile data as JSON (used by profile edit page via AJAX)
    public function show()
    {
        return response()->json([
            'user' => $this->editableProfile(Auth::user()),
        ]);
    }

    // Public profile page for viewing another user
    public function showUser(User $user)
    {
        $user->load(['approvedTeachingQualifications', 'schedules', 'posts' => function ($q) {
            $q->latest()->take(3);
        }]);

        $currentUser = Auth::user();

        $likeCount   = $user->likedBy()->count();
        $swapCount   = $user->swaps()->count();
        $commentCount = $user->comments()->count();
        $hasLiked    = $currentUser->likes()->where('liked_user_id', $user->id)->exists();
        $hasFavorited = $currentUser->favorites()->where('item_id', $user->id)->exists();
        $learningBadges = \App\Support\LearningHub::badges($user);

        return view('profile.show', compact(
            'user',          // keep as $user for route model binding
            'likeCount',
            'swapCount',
            'commentCount',
            'hasLiked',
            'hasFavorited',
            'learningBadges',
        ) + ['profileUser' => $user]);
    }

    public function update(UpdateUserRequest $request)
    {
        $user = Auth::user();

        if ($request->hasFile('profile_picture')) {
            $path = $request->file('profile_picture')->store('profile_pictures', 'public');
            if (!$path) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'profile_picture' => 'Your picture could not be saved. Please try again.',
                ]);
            }
            $user->profile_picture = $path;
        }

        $user->first_name  = $request->input('first_name');
        if ($request->exists('username')) {
            $user->username = $request->validated('username');
        }
        $user->middle_name = $request->input('middle_name');
        $user->last_name   = $request->input('last_name');
        $user->email       = $request->input('email');
        $user->description = $request->input('description');

        foreach (['learning_interests', 'course', 'expertise', 'school', 'school_location', 'show_course', 'show_expertise', 'show_school', 'show_school_location'] as $field) {
            if ($request->exists($field)) {
                $user->{$field} = $request->validated($field);
            }
        }

        if ($request->filled('password')) {
            $user->password = bcrypt($request->input('password'));
        }

        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user'    => $this->editableProfile($user),
        ]);
    }

    private function editableProfile(User $user): array
    {
        return array_merge($user->toArray(), collect([
            'learning_interests', 'course', 'expertise', 'school', 'school_location',
            'show_course', 'show_expertise', 'show_school', 'show_school_location',
        ])->mapWithKeys(fn ($field) => [$field => $user->{$field}])->all());
    }

    public function removePicture()
    {
        $user = auth()->user();

        // Check if the user actually has a picture to remove
        if ($user->profile_picture) {
            
            // Delete the physical file from the storage folder
            \Illuminate\Support\Facades\Storage::disk('public')->delete($user->profile_picture);
            
            // Clear the column in the database
            $user->update([
                'profile_picture' => null
            ]);

            return back()->with('success', 'Profile picture removed successfully.');
        }

        return back()->with('error', 'No profile picture found to remove.');
    }
    
}
