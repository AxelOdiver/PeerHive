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
        $catalogTopics = \App\Models\CatalogEntry::topics();
        $catalogPrograms = \App\Models\CatalogEntry::approved()->where('kind', 'program')->orderBy('name')->get();
        $catalogSubjects = \App\Models\CatalogEntry::approved()->where('kind', 'subject')->orderBy('name')->get();
        $catalogSuggestions = \App\Models\CatalogEntry::whereHas('requesters', fn ($q) => $q->where('users.id', auth()->id()))->with(['parent', 'mergedInto'])->latest()->get();
        $qualifications = \App\Models\UserSubjectQualification::where('user_id', auth()->id())->latest('id')->get();
        return view('profile.edit', compact('catalogTopics', 'catalogPrograms', 'catalogSubjects', 'catalogSuggestions', 'qualifications'));
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
        $user->loadAvg('teachingRatings', 'stars')->loadCount('teachingRatings');
        $learnerReviews = $user->teachingRatings()
            ->with('learner:id,first_name,last_name')
            ->latest('id')
            ->simplePaginate(5, ['id', 'teacher_id', 'learner_id', 'stars', 'comment', 'created_at', 'updated_at'], 'reviews_page')
            ->fragment('learner-reviews');

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
            'learnerReviews',
        ) + ['profileUser' => $user]);
    }

    public function reviews(Request $request, User $user)
    {
        $data = $request->validate([
            'stars' => 'nullable|integer|between:1,5',
            'sort' => 'nullable|in:newest,highest,lowest',
            'page' => 'nullable|integer|min:1|max:1000000',
        ]);
        $stars = $data['stars'] ?? null;
        $sort = $data['sort'] ?? 'newest';
        $counts = $user->teachingRatings()->selectRaw('stars, COUNT(*) as total')->groupBy('stars')->pluck('total', 'stars');
        $query = $user->teachingRatings()->with('learner:id,first_name,last_name');
        if ($stars !== null) $query->where('stars', $stars);
        if ($sort !== 'newest') $query->orderBy('stars', $sort === 'highest' ? 'desc' : 'asc');
        $learnerReviews = $query->latest('id')->paginate(5, ['id', 'teacher_id', 'learner_id', 'stars', 'comment', 'created_at', 'updated_at'])->withQueryString();
        $user->loadAvg('teachingRatings', 'stars')->loadCount('teachingRatings');
        return response()->json(['html' => view('profile.all-reviews', compact('user', 'counts', 'stars', 'sort', 'learnerReviews'))->render()]);
    }

    public function updateReview(Request $request, \App\Models\SessionRating $rating)
    {
        abort_unless($rating->learner_id === $request->user()->id, 403);
        $data = $request->validate(['stars' => 'required|integer|between:1,5', 'comment' => 'nullable|string|max:500', 'version' => 'required|string|size:64']);
        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $rating, $data) {
            $locked = \App\Models\SessionRating::whereKey($rating->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->canEditBy($request->user()), 403, 'Reviews can only be edited within 24 hours of posting.');
            abort_unless(hash_equals($locked->reviewVersion(), $data['version']), 409, 'This review changed. Reopen reviews before editing.');
            $comment = trim($data['comment'] ?? '');
            $locked->update(['stars' => $data['stars'], 'comment' => $comment === '' ? null : $comment]);
        });
        return response()->json(['message' => 'Review updated.']);
    }

    public function update(UpdateUserRequest $request)
    {
        $user = Auth::user();
        foreach (['first_name', 'middle_name', 'last_name'] as $field) {
            if (is_string($request->input($field))) $request->merge([$field => trim($request->input($field))]);
        }

        // v4.6.1: all account security changes use verified, dedicated endpoints.
        if (mb_strtolower(trim((string)$request->input('email', $user->email))) !== mb_strtolower((string)$user->email)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => 'Use Account > Change email to verify a new address before updating it.',
            ]);
        }
        if ($request->filled('password')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'password' => 'Use Account > Change password and confirm your current password.',
            ]);
        }
        $request->validate([
            'first_name' => ['required', 'string', 'max:255', 'regex:/\A[\p{L}\p{M}]+(?:[ \'-][\p{L}\p{M}]+)*\z/u'],
            'middle_name' => ['nullable', 'string', 'max:255', 'regex:/\A[\p{L}\p{M}]+(?:[ \'-][\p{L}\p{M}]+)*\z/u'],
            'last_name' => ['required', 'string', 'max:255', 'regex:/\A[\p{L}\p{M}]+(?:[ \'-][\p{L}\p{M}]+)*\z/u'],
        ], [
            'first_name.regex' => 'Use letters, spaces, apostrophes, or hyphens only.',
            'middle_name.regex' => 'Use letters, spaces, apostrophes, or hyphens only.',
            'last_name.regex' => 'Use letters, spaces, apostrophes, or hyphens only.',
        ]);

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
        $user->description = $request->input('description');

        foreach (['learning_interests', 'course', 'expertise', 'school', 'school_location', 'show_course', 'show_expertise', 'show_school', 'show_school_location'] as $field) {
            if ($request->exists($field)) {
                $user->{$field} = $request->validated($field);
            }
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($user, $request) {
            // Match catalog review's lock order: catalog entry before user.
            if ($request->boolean('course_unlisted') && $user->course) CatalogController::suggest('program', $user->course, $user->id);
            $user->save();
        });

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

