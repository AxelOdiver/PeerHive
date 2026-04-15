<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Community;

class CommunityController extends Controller
{
    public function index()
    {
        $communities = Community::with('user')->latest()->get();
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

        return response()->json([
            'message' => 'Community created successfully!',
            'community' => $community,
        ], 201);
    }
    //Load the community details page
    public function show(Community $community)
    {
        $community->load('user');
        return view('community-show', compact('community'));
    }

    public function destroy(Community $community)
    {
        // Ensure only the creator can delete the community
        if ($community->user_id !== auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized to delete this community.',
            ], 403);
        }

        $community->delete();

        return response()->json([
            'message' => 'Community deleted successfully!',
        ]);
    }

    public function update(Request $request, Community $community)
    {
        // Authorization Only the creator can edit
    if (auth()->id() !== $community->user_id) {
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

    public function updateTags(Request $request, $id)
    {
        $community = Community::findOrFail($id);

        // Only the creator can edit tags
        if (auth()->id() !== $community->user_id) {
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
}
