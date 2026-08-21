<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Community;
use App\Models\CommunityInvite;
use App\Models\User;

class CommunityInviteController extends Controller
{
    //Send an Invite
    public function store(Request $request, Community $community)
    {
        // Security: Only the creator (or an admin) can send invites
        if (auth()->id() !== $community->user_id) {
            return back()->with('error', 'Only the creator can invite members.');
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        // Check if they are already a member
        if ($community->members()->where('user_id', $request->user_id)->exists()) {
            return back()->with('error', 'This user is already a member.');
        }

        // Create the pending invite (the unique constraint in the DB prevents spamming)
        CommunityInvite::firstOrCreate([
            'community_id' => $community->id,
            'user_id' => $request->user_id,
        ], ['status' => 'pending']);

        return back()->with('success', 'Invitation sent successfully!');
    }

    public function sendInvite(\Illuminate\Http\Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'community_id' => 'required|exists:communities,id'
        ], [
            'email.exists' => 'We could not find a student with that email address.'
        ]);

        $invitedUser = \App\Models\User::where('email', $request->email)->first();

        if ($invitedUser->id === auth()->id()) {
            return back()->with('error', 'You cannot invite yourself.');
        }

        $existingInvite = \App\Models\CommunityInvite::where('user_id', $invitedUser->id)
            ->where('community_id', $request->community_id)
            ->first();

        if ($existingInvite) {
            return back()->with('error', 'This student has already been invited.');
        }

        \App\Models\CommunityInvite::create([
            'user_id' => $invitedUser->id,
            'community_id' => $request->community_id,
            'status' => 'pending'
        ]);

        return back()->with('success', 'Invitation sent successfully!');
    }

    // Accept an Invite
    public function accept(CommunityInvite $invite)
    {
        // Security: Only the invited user can accept this
        if (auth()->id() !== $invite->user_id) {
            return abort(403);
        }

        // Add them to the community
        $invite->community->members()->syncWithoutDetaching([auth()->id()]);
        
        // Update the invite status so it disappears from their pending list
        $invite->update(['status' => 'accepted']);

        return redirect()->route('community.show', $invite->community)
                         ->with('success', 'You have joined the community!');
    }

    // Decline an Invite
    public function decline(CommunityInvite $invite)
    {
        // Security: Only the invited user can decline this
        if (auth()->id() !== $invite->user_id) {
            return abort(403);
        }

        // Update the status to declined so it disappears from the bell
        $invite->update(['status' => 'declined']);

        return back()->with('success', 'Invitation declined.');
    }
}