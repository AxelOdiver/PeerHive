<?php

namespace App\Http\Controllers;

use App\Models\{Community, CommunityInvite, CommunityJoinRequest};
use App\Support\{BellNotifications, LearningHub};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommunityJoinRequestController extends Controller
{
    public function store(Request $request, Community $community)
    {
        $message = DB::transaction(function () use ($request, $community) {
            $community = Community::whereKey($community->id)->lockForUpdate()->firstOrFail();
            if (LearningHub::member($request->user(), $community)) return 'You are already a member.';
            if ($community->memberCount() >= $community->member_limit) return 'This community has reached its member limit.';
            if ($community->visibility === 'public') {
                $community->members()->syncWithoutDetaching([$request->user()->id]);
                $community->joinRequests()->where('user_id', $request->user()->id)->where('status', 'pending')->update(['status' => 'approved']);
                CommunityInvite::where('community_id', $community->id)->where('user_id', $request->user()->id)->where('status', 'pending')->update(['status' => 'accepted']);
                return 'You joined the community.';
            }
            $join = $community->joinRequests()->firstOrNew(['user_id' => $request->user()->id]);
            if ($join->exists && $join->status === 'pending') return 'Your join request is already awaiting approval.';
            $join->status = 'pending';
            $join->save();
            BellNotifications::record($community->user_id, 'community-request:'.Str::uuid(), 'community', $community->name,
                $request->user()->first_name.' requested to join your community.', route('community.show', $community).'#join-requests');
            return 'Join request sent. The community owner will review it.';
        });
        return redirect()->route('community.show', $community)->with('success', $message);
    }

    public function cancel(Request $request, Community $community)
    {
        DB::transaction(function () use ($request, $community) {
            Community::whereKey($community->id)->lockForUpdate()->firstOrFail();
            $community->joinRequests()->where('user_id', $request->user()->id)->where('status', 'pending')->update(['status' => 'cancelled']);
        });
        return back()->with('success', 'Join request cancelled.');
    }

    public function respond(Request $request, Community $community, CommunityJoinRequest $joinRequest)
    {
        abort_unless($request->user()->id === $community->user_id || $request->user()->role === 'admin', 403);
        abort_unless($joinRequest->community_id === $community->id, 404);
        $data = $request->validate(['decision' => 'required|in:approved,declined']);
        DB::transaction(function () use ($community, $joinRequest, $data) {
            $community = Community::whereKey($community->id)->lockForUpdate()->firstOrFail();
            $join = CommunityJoinRequest::whereKey($joinRequest->id)->lockForUpdate()->firstOrFail();
            abort_unless($join->status === 'pending', 409, 'This request has already been handled.');
            if ($data['decision'] === 'approved') {
                if (!$community->members()->where('users.id', $join->user_id)->exists()) {
                    abort_if($community->memberCount() >= $community->member_limit, 409, 'This community is full.');
                    $community->members()->syncWithoutDetaching([$join->user_id]);
                }
                CommunityInvite::where('community_id', $community->id)->where('user_id', $join->user_id)->where('status', 'pending')->update(['status' => 'accepted']);
            }
            $join->update(['status' => $data['decision']]);
            BellNotifications::record($join->user_id, 'community-decision:'.Str::uuid(), 'community', $community->name,
                $data['decision'] === 'approved' ? 'Your join request was approved.' : 'Your join request was declined.', route('community.show', $community));
        });
        return back()->with('success', $data['decision'] === 'approved' ? 'Member approved.' : 'Join request declined.');
    }
}
