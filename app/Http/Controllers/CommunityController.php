<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Community;
use App\Models\Post;
use App\Models\Comment;

class CommunityController extends Controller
{
    public function index()
    {
        $communities = Community::with(['user', 'members'])->latest()->get();
        return view('community', compact('communities'));
    }

    public function store (Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|in:Coding,Foreign Language,Graphic Designing',
            'member_limit' => 'required|integer|min:3|max:25',
        ]);

        $validated['user_id'] = auth()->id();
        $community = Community::create($validated);
        $community->members()->syncWithoutDetaching([auth()->id()]);

        return response()->json([
            'message' => 'Community created successfully!',
            'community' => $community,
        ], 201);
    }

    public function join(Community $community)
    {
        if ($community->user_id === auth()->id()) {
            return redirect()->route('community.show', $community);
        }

        if ($community->members()->where('user_id', auth()->id())->exists()) {
            return redirect()->route('community.show', $community);
        }

        $memberCount = $community->members()->count();

        if (!$community->members()->where('user_id', $community->user_id)->exists()) {
            $memberCount++;
        }

        if ($memberCount >= $community->member_limit) {
            return back()->with('error', 'This community has reached its member limit.');
        }

        $community->members()->attach(auth()->id());

        return redirect()
            ->route('community.show', $community)
            ->with('success', 'You joined the community.');
    }
    //Load the community details page
    public function show(Community $community)
    {
        // We load the creator, the posts (newest first), the post authors, and comments
        $community->load(['user', 'posts' => function($query) {
            $query->latest(); 
        }, 'posts.user', 'posts.comments']);

        return view('community-show', compact('community'));
    }

    // Handle the AJAX request to create a new post
    public function storePost(Request $request, $id)
    {
        $community = Community::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['community_id'] = $community->id;

        $post = Post::create($validated);

        return response()->json([
            'message' => 'Post created successfully!',
            'post' => $post
        ]);
    }   

    // Handle community deletion
    public function destroy(Community $community)
    {
        if ($community->user_id !== auth()->id() && auth()->user()->role !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized to delete this community.',
            ], 403);
        }

        $community->delete();

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
        
        $post->delete();
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
}
