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

    public function searchUsers(Request $request)
    {
        $search = $request->get('q');
        
        // Search first_name or last_name
        $users = User::where(function($query) use ($search) {
                $query->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%");
            })
            ->where('id', '!=', auth()->id())
            ->select('id', 'first_name', 'last_name', 'email', 'profile_picture') 
            ->take(5)
            ->get();

        $formattedUsers = $users->map(function($user) {
            return [
                'id' => $user->id,
                'name' => $user->first_name . ' ' . $user->last_name,
                'email' => $user->email,
                'profile_picture' => $user->profile_picture,
                'initials' => substr($user->first_name, 0, 1)
            ];
        });

        return response()->json($formattedUsers);
    }

    public function sendInvite(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'community_id' => 'required|exists:communities,id'
        ], [
            'user_id.required' => 'Please select a student from the search results.'
        ]);

        $invitedUser = User::findOrFail($request->user_id);

        if ($invitedUser->id === auth()->id()) {
            return back()->with('error', 'You cannot invite yourself.');
        }

        // 1. Verify TRUE membership by checking the pivot table
        $community = Community::findOrFail($request->community_id);
        $isAlreadyMember = $community->members()->where('user_id', $invitedUser->id)->exists();

        // 2. Look up their historical invite record
        $existingInvite = CommunityInvite::where('user_id', $invitedUser->id)
            ->where('community_id', $request->community_id)
            ->first();

        if ($existingInvite) {
            // Block if they already have an active invite waiting
            if ($existingInvite->status === 'pending') {
                return back()->with('error', 'This student already has a pending invite waiting for a response.');
            }
            
            // Block ONLY if the invite says accepted AND they are actually in the group
            if ($existingInvite->status === 'accepted' && $isAlreadyMember) {
                return back()->with('error', 'This student is already a member of the community.');
            }

            // RECYCLE THE ROW: If they declined previously, OR if they were removed by the creator
            if ($existingInvite->status === 'declined' || ($existingInvite->status === 'accepted' && !$isAlreadyMember)) {
                $existingInvite->update([
                    'status' => 'pending'
                ]);
                return back()->with('success', 'Invitation resent successfully!');
            }
        }

        // 3. If they have never been invited before, create a brand new row
        CommunityInvite::create([
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