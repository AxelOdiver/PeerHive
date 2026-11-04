<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Community;
use App\Models\CatalogEntry;
use Illuminate\Validation\Rule;
use App\Models\Post;
use App\Models\Comment;
use Illuminate\Support\Facades\Storage;

class CommunityController extends Controller
{
    public function index()
    {
        $communities = Community::with(['user', 'members', 'banner'])->latest()->get();
        $subjects = CatalogEntry::subjects();
        return view('community', compact('communities', 'subjects'));
    }

    public function store (Request $request)
    {
        // Accept older forms while saving the current catalog subject names.
        $request->merge([
            'subject' => match (trim((string) $request->input('subject'))) {
                'Coding' => 'Programming',
                'Foreign Language' => 'Languages',
                'Graphic Design', 'Graphic Designing' => 'Design',
                default => trim((string) $request->input('subject')),
            },
        ]);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => ['required', 'string', Rule::in(CatalogEntry::subjects()->pluck('name')->all())],
            'member_limit' => 'required|integer|min:3|max:25',
            'visibility' => 'required|in:public,private',
        ]);

        $validated['user_id'] = auth()->id();
        $community = Community::create($validated);
        $community->members()->syncWithoutDetaching([auth()->id()]);

        return response()->json([
            'message' => 'Community created successfully!',
            'community' => $community,
        ], 201);
    }

    public function show(Community $community)
    {
        $community->load(['user', 'banner'])->loadCount('members');
        $isMember = \App\Support\LearningHub::member(auth()->user(), $community);
        $isManager = auth()->id() === $community->user_id || auth()->user()->role === 'admin';
        $joinRequest = $community->visibility === 'private'
            ? $community->joinRequests()->where('user_id', auth()->id())->first() : null;
        $pendingInvite = \App\Models\CommunityInvite::where('community_id', $community->id)
            ->where('user_id', auth()->id())->where('status', 'pending')->first();
        $memberCount = $community->memberCount();
        if ($community->visibility === 'private' && !$isMember && !$isManager) {
            return view('community-preview', compact('community', 'joinRequest', 'pendingInvite', 'memberCount'));
        }

        $community->load(['members', 'posts' => fn ($query) => $query->latest(), 'posts.user', 'posts.comments.user']);
        $joinRequests = $isManager && $community->visibility === 'private'
            ? $community->joinRequests()->where('status', 'pending')->with('user')->oldest()->get() : collect();
        return view('community-show', compact('community', 'joinRequest', 'pendingInvite', 'memberCount', 'joinRequests'));
    }

    // Handle the AJAX request to create a new post
    public function storePost(Request $request, $id)
    {
        $community = Community::findOrFail($id);
        abort_unless(\App\Support\LearningHub::member($request->user(), $community), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required_without:media|nullable|string',
            'media' => 'nullable|file|mimetypes:image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm|max:20480',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['community_id'] = $community->id;
        $validated['body'] = $validated['body'] ?? '';
        unset($validated['media']);

        if ($request->hasFile('media')) {
            $validated['media_path'] = $request->file('media')->store('community-posts', 'local');
            abort_unless($validated['media_path'], 500, 'The attachment could not be saved. Please try again.');
            $validated['media_mime'] = $request->file('media')->getMimeType();
        }

        try {
            $post = Post::create($validated);
        } catch (\Throwable $exception) {
            if (!empty($validated['media_path'])) Storage::disk('local')->delete($validated['media_path']);
            throw $exception;
        }

        return response()->json([
            'message' => 'Post created successfully!',
            'post' => $post
        ]);
    }   

    public function postMedia(Request $request, Post $post)
    {
        $community = $post->community;
        abort_unless($community && ($community->visibility === 'public'
            || \App\Support\LearningHub::member($request->user(), $community)
            || $request->user()->role === 'admin'), 403);
        abort_unless($post->media_path && Storage::disk('local')->exists($post->media_path), 404);

        return response()->file(Storage::disk('local')->path($post->media_path), [
            'Content-Type' => $post->media_mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ])->setPrivate();
    }

    // Handle community deletion
    public function destroy(Community $community)
    {
        if ($community->user_id !== auth()->id() && auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized to delete this community.',
            ], 403);
        }

        $mediaPaths = $community->posts()->whereNotNull('media_path')->pluck('media_path')->all();
        $bannerPaths = $community->banners()->pluck('path')->all();
        $community->delete();
        Storage::disk('local')->delete($mediaPaths);
        Storage::disk('local')->delete($bannerPaths);

       return response()->json([
            'message' => 'Community deleted successfully.',
        ]);
    }

    public function update(Request $request, Community $community)
    {
        // Authorization Only the creator can edit
    if (auth()->id() !== $community->user_id && auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'You are not authorized to edit this community.'
            ], 403); 
        }

        // Validate the incoming text
        $validatedData = $request->validate([
            'description' => 'required|string|max:1000',
        ]);

        // Save to the database
        $community->update($validatedData);

        return response()->json([
            'message' => 'Description updated successfully!'
        ]);
    }

    // Update tags for a community
    public function updateTags(Request $request, $id)
    {
        $community = Community::findOrFail($id);

        // Only the creator can edit tags
        if (auth()->id() !== $community->user_id && auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized action.'], 403); 
        }

        $validated = $request->validate([
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        $community->update([
            'tags' => $validated['tags'] ?? []
        ]);

        return response()->json([
            'message' => 'Tags updated successfully!',
            'tags' => $community->tags
        ]);
    }

    // comments are stored here
    public function storeComment(Request $request, $postId)
    {
        $post = Post::with('community')->findOrFail($postId);
        abort_unless($post->community && \App\Support\LearningHub::member($request->user(), $post->community), 403);
        $request->validate(['body' => 'required|string|max:1000']);

        Comment::create([
            'post_id' => $postId,
            'user_id' => auth()->id(),
            'body' => $request->body
        ]);

        return back(); 
    }

    // delete a post
    public function destroyPost($id)
    {
        $post = Post::findOrFail($id);

        if (auth()->id() !== $post->user_id && auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }
        
        $mediaPath = $post->media_path;
        $post->delete();
        if ($mediaPath) Storage::disk('local')->delete($mediaPath);
        return response()->json(['message' => 'Post deleted successfully.']);    }
    

    // delete a comment
    public function destroyComment($id)
    {
        $comment = Comment::findOrFail($id);

        if (auth()->id() !== $comment->user_id) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $comment->delete();
        return response()->json(['message' => 'Comment deleted successfully.']);
    }

    public function removeMember($communityId, $userId)
    {
        $community = \App\Models\Community::findOrFail($communityId);

        // Security check: Only the creator or an admin can remove members
        if (auth()->id() !== $community->user_id && auth()->user()->role !== 'admin') {
            return response()->json(['message' => 'You do not have permission to remove members.'], 403);
        }

        // Prevent the creator from accidentally removing themselves
        if (auth()->id() == $userId) {
            return response()->json(['message' => 'You cannot remove yourself from your own community.'], 400);
        }

        // Instantly remove the student using your existing pivot table
        $community->members()->detach($userId);

        return response()->json(['message' => 'Member removed successfully.']);
    }

    public function leaveCommunity($communityId)
    {
        $community = \App\Models\Community::findOrFail($communityId);
        $userId = auth()->id();

        // Prevent the creator from leaving (they should delete the community instead)
        if ($community->user_id === $userId) {
            return response()->json(['message' => 'As the creator, you cannot leave the community. You must delete it instead.'], 400);
        }

        // Verify they are actually a member
        if (!$community->members()->where('user_id', $userId)->exists()) {
            return response()->json(['message' => 'You are not a member of this community.'], 400);
        }

        // Remove the user from the pivot table
        $community->members()->detach($userId);

        return response()->json(['message' => 'You have successfully left the community.']);
    }
}
