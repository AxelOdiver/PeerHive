<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\User;
use App\Support\ChatPresence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MessagingActivityController extends Controller
{
    public function heartbeat(Request $request)
    {
        $id = $request->user()->id;
        Cache::put('presence:'.$id, now()->timestamp, 75);
        User::whereKey($id)->where(fn ($q) => $q->whereNull('last_seen_at')->orWhere('last_seen_at', '<=', now()->subSeconds(25)))
            ->update(['last_seen_at' => now()]);
        return response()->noContent();
    }

    public function typing(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->users()->where('users.id', auth()->id())->exists(), 403);
        $data = $request->validate(['is_typing' => ['required', 'boolean']]);
        $key = 'typing:'.$conversation->id.':'.auth()->id();
        if ($data['is_typing']) {
            Cache::put($key, now()->timestamp, 8);
        } else {
            Cache::forget($key);
        }
        return response()->noContent();
    }

    public function read(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->users()->where('users.id', auth()->id())->exists(), 403);
        $data = $request->validate(['message_id' => ['required', 'integer', Rule::exists('messages', 'id')->where('conversation_id', $conversation->id)]]);
        $message = $conversation->messages()->findOrFail($data['message_id']);
        // A delayed request from another tab must never move the marker backwards.
        DB::table('conversation_user')->where('conversation_id', $conversation->id)->where('user_id', auth()->id())
            ->where('last_read_message_id', '<', $message->id)
            ->update(['last_read_message_id' => $message->id, 'last_read_at' => $message->created_at, 'updated_at' => now()]);
        return response()->noContent();
    }

    public function state(Conversation $conversation)
    {
        abort_unless($conversation->users->contains('id', auth()->id()), 403);
        $others = $conversation->users->where('id', '!=', auth()->id())->values();
        $presence = ChatPresence::forUsers($others);
        $typing = Cache::many($others->map(fn ($user) => 'typing:'.$conversation->id.':'.$user->id)->all());
        $members = $others->map(function ($user) use ($conversation, $presence, $typing) {
            // Place each reader's marker on the last message from this sender they have read.
            $readId = $conversation->messages()->visibleTo(auth()->id())->where('sender_id', auth()->id())
                ->where('id', '<=', $user->pivot->last_read_message_id)->where('id', '>', $conversation->clearedThrough(auth()->id()))->max('id');
            return array_merge($presence[$user->id], [
                'id' => $user->id,
                'name' => trim($user->first_name.' '.$user->last_name),
                'last_read_message_id' => $readId,
                'is_typing' => (int) ($typing['typing:'.$conversation->id.':'.$user->id] ?? 0) > now()->subSeconds(8)->timestamp,
            ]);
        });
        return response()->json(['members' => $members]);
    }
}
