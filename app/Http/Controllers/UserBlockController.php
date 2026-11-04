<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserBlock;

class UserBlockController extends Controller
{
    public function store(User $user)
    {
        abort_if($user->is(auth()->user()), 422, 'You cannot block your own account.');
        UserBlock::block(auth()->id(), $user->id);

        return response()->json(['message' => 'Account blocked. You will not receive their direct messages.']);
    }

    public function destroy(User $user)
    {
        UserBlock::unblock(auth()->id(), $user->id);

        return response()->json(['message' => 'Account unblocked.']);
    }
}
