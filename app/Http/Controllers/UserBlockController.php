<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserBlock;

class UserBlockController extends Controller
{
    public function store(User $user)
    {
        abort_if($user->is(auth()->user()), 422, 'You cannot block your own account.');
        UserBlock::firstOrCreate(['blocker_id' => auth()->id(), 'blocked_id' => $user->id]);

        return response()->json(['message' => 'Account blocked from direct messages.']);
    }

    public function destroy(User $user)
    {
        UserBlock::where('blocker_id', auth()->id())->where('blocked_id', $user->id)->delete();

        return response()->json(['message' => 'Account unblocked.']);
    }
}
