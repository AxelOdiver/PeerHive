<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\ChatPresence;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MessageController extends Controller
{
    public function index()
    {
        $users = User::where('id', '!=', auth()->id())->orderBy('first_name')->get();
        return view('messages', compact('users'));
    }

    public function conversations()
    {
        $userId = auth()->id();

        $conversations = Conversation::whereHas('users', fn ($q) => $q->where('users.id', $userId))
            ->with(['users', 'latestMessage.sender'])
            ->get();
        $presence = ChatPresence::forUsers($conversations->flatMap(fn ($conv) => $conv->users));
        $conversations = $conversations->filter(fn ($conv) => $conv->visibleTo($userId))->map(function ($conv) use ($userId, $presence) {
                $pivot = $conv->users->firstWhere('id', $userId)->pivot;

                $unreadCount = $conv->messages()->visibleTo($userId)
                    ->where('sender_id', '!=', $userId)
                    ->where('id', '>', max($pivot->last_read_message_id, $pivot->cleared_through_message_id))
                    ->count();

                $otherUsers = $conv->users->where('id', '!=', $userId)->values();
                $firstOther = $otherUsers->first();

                $name = $conv->is_group
                    ? ($conv->name ?: $otherUsers->pluck('first_name')->implode(', '))
                    : trim(($firstOther->first_name ?? '') . ' ' . ($firstOther->last_name ?? ''));

                $initials = $conv->is_group
                    ? strtoupper(substr($name, 0, 2))
                    : strtoupper(substr($firstOther->first_name ?? '', 0, 1) . substr($firstOther->last_name ?? '', 0, 1));

                $last = $conv->messages()->visibleTo($userId)->where('id', '>', $conv->clearedThrough($userId))->latest('id')->first();
                if ($last && $last->id <= $pivot->cleared_through_message_id) $last = null;
                $lastPreview = $last?->is_unsent
                    ? 'Message unsent'
                    : ($last?->body ?? ($last?->attachment_name ? '📎 ' . $last->attachment_name : null));

                return [
                    'id' => $conv->id,
                    'is_group' => $conv->is_group,
                    'name' => $name ?: 'Conversation',
                    'initials' => $initials,
                    'profile_picture' => (!$conv->is_group) ? $firstOther?->profile_picture : null,
                    'last_message' => $lastPreview,
                    'last_message_at' => $last?->created_at?->timestamp ?? $conv->created_at->timestamp,
                    'unread_count' => $unreadCount,
                    'is_muted' => $conv->isMutedFor($userId),
                    'presence' => !$conv->is_group && $firstOther ? $presence[$firstOther->id] : null,
                ];
            })
            ->sortByDesc('last_message_at')
            ->values();

        return response()->json(['conversations' => $conversations]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required_unless:is_group,1,true', 'nullable', 'integer', 'exists:users,id'],
            'is_group' => ['nullable', 'in:0,1,true,false'],
            'name' => ['nullable', 'string', 'max:255'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $authId = auth()->id();
        $isGroup = filter_var($request->input('is_group'), FILTER_VALIDATE_BOOLEAN);

        if ($isGroup) {
            $memberIds = collect($validated['member_ids'] ?? [])->push($authId)->unique()->values();

            if ($memberIds->count() < 3) {
                return response()->json(['message' => 'Select at least 2 other members for a group.'], 422);
            }

            $groupName = $validated['name'] ?? null;

            if (empty($groupName)) {
                $memberNames = User::whereIn('id', $memberIds)
                    ->where('id', '!=', $authId)
                    ->pluck('first_name');

                $groupName = $memberNames->implode(', ');
            }

            $conversation = Conversation::create([
                'is_group' => true,
                'name' => $groupName,
                'created_by' => $authId,
            ]);

            $conversation->users()->attach($memberIds, ['last_read_at' => now()]);

            return response()->json(['conversation_id' => $conversation->id]);
        }

        $otherId = (int) $validated['user_id'];

        if ($otherId === $authId) {
            return response()->json(['message' => 'You cannot message yourself.'], 422);
        }

        $existing = Conversation::where('is_group', false)
            ->whereHas('users', fn ($q) => $q->where('users.id', $authId))
            ->whereHas('users', fn ($q) => $q->where('users.id', $otherId))
            ->get()
            ->first(fn ($c) => $c->users->count() === 2);

        if ($existing) {
            $existing->users()->updateExistingPivot($authId, ['is_hidden' => false]);
            return response()->json(['conversation_id' => $existing->id]);
        }

        $conversation = Conversation::create(['is_group' => false, 'created_by' => $authId]);
        $conversation->users()->attach([$authId, $otherId], ['last_read_at' => now()]);

        return response()->json(['conversation_id' => $conversation->id]);
    }

    public function fetch(Request $request, Conversation $conversation)
    {
        $authId = auth()->id();
        abort_unless($conversation->users->contains('id', $authId), 403);

        $perPage = 30;
        $beforeId = $request->query('before_id');

        $request->validate(['q' => ['nullable', 'string', 'max:200'], 'before_id' => ['nullable', 'integer', 'min:1'], 'around_id' => ['nullable', 'integer', 'min:1'], 'after_id' => ['nullable', 'integer', 'min:1']]);
        $search = trim((string) $request->query('q'));
        $clearedThrough = $conversation->clearedThrough($authId);
        $visibleMessages = $conversation->messages()->visibleTo($authId)->where('id', '>', $clearedThrough);
        $query = (clone $visibleMessages)->with(['sender', 'repliedTo' => fn ($q) => $q->visibleTo($authId)->with('sender'), 'reactions'])->orderByDesc('id');
        if ($search !== '') {
            $query->where('is_unsent', false)->where(function ($q) use ($search) {
                $q->where('body', 'like', '%'.$search.'%')->orWhere('attachment_name', 'like', '%'.$search.'%');
            });
        }

        $totalResults = $search !== '' ? (clone $query)->count() : null;
        $aroundId = $request->integer('around_id');
        $afterId = $request->integer('after_id');
        if ($aroundId) {
            abort_unless((clone $visibleMessages)->whereKey($aroundId)->exists(), 404);
            $startId = (clone $visibleMessages)->where('id', '<=', $aroundId)->orderByDesc('id')->limit(16)->pluck('id')->last();
            $query->where('id', '>=', $startId)->reorder('id');
        } elseif ($afterId) {
            $query->where('id', '>', $afterId)->reorder('id');
        } elseif ($beforeId) {
            $query->where('id', '<', $beforeId);
        }

        $page = $query->limit($perPage + 1)->get();
        $hasMore = $page->count() > $perPage;
        $messages = $page->take($perPage)->sortBy('id')->values();
        if ($aroundId || $afterId) {
            $hasMore = (clone $visibleMessages)->where('id', '<', $messages->first()?->id ?? 0)->exists();
        }
        $hasNewer = $messages->isNotEmpty() && (clone $visibleMessages)->where('id', '>', $messages->last()->id)->exists();

        // Reads are acknowledged explicitly by the focused chat after messages are displayed.

        return response()->json([
            'is_group' => $conversation->is_group,
            'has_more' => $hasMore,
            'has_newer' => $hasNewer,
            'total_results' => $totalResults,
            'is_muted' => $conversation->isMutedFor($authId),
            'members' => $conversation->users->map(fn ($u) => [
                'id' => $u->id,
                'name' => trim($u->first_name . ' ' . $u->last_name),
            ]),
            'messages' => $messages->map(fn ($m) => [
                'id' => $m->id,
                'client_message_id' => $m->sender_id === $authId ? $m->client_message_id : null,
                'body' => $m->is_unsent ? null : $m->body,
                'attachment_url' => (!$m->is_unsent && $m->attachment_path) ? Storage::url($m->attachment_path) : null,
                'attachment_name' => $m->is_unsent ? null : $m->attachment_name,
                'attachment_type' => $m->is_unsent ? null : $m->attachment_type,
                'is_mine' => $m->sender_id === $authId,
                'sender_name' => $m->sender->first_name,
                'created_at' => $m->created_at->format('M d, g:i A'),
                'is_edited' => (bool) $m->edited_at,
                'is_unsent' => (bool) $m->is_unsent,
                'reactions' => $m->is_unsent ? [] : $m->reactions->groupBy('emoji')->map(fn ($items, $emoji) => [
                    'emoji' => $emoji, 'count' => $items->count(), 'is_mine' => $items->contains('user_id', $authId),
                ])->values(),
                'reply_to' => ($m->reply_to_id > $clearedThrough && $m->repliedTo && !$m->repliedTo->is_unsent) ? [
                    'sender_name' => $m->repliedTo->sender->first_name,
                    'body' => mb_strimwidth($m->repliedTo->body ?: 'Attachment', 0, 80, '…'),
                ] : null,
            ]),
        ]);
    }

    public function storeMessage(Request $request, Conversation $conversation)
    {
        $authId = auth()->id();
        abort_unless($conversation->users->contains('id', $authId), 403);

        $validated = $request->validate([
            'client_message_id' => ['nullable', 'uuid'],
            'body' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:10240'],
            'reply_to_id' => ['nullable', 'integer', Rule::exists('messages', 'id')->where('conversation_id', $conversation->id)->where('is_unsent', 0)->where(fn ($q) => $q->where('id', '>', $conversation->clearedThrough($authId)))],
        ]);

        if (trim((string) ($validated['body'] ?? '')) === '' && !$request->hasFile('attachment')) {
            return response()->json(['message' => 'Message cannot be empty.'], 422);
        }

        $file = $request->file('attachment');
        $fingerprint = hash('sha256', json_encode([
            $conversation->id, $validated['body'] ?? null, $validated['reply_to_id'] ?? null,
            $file ? hash_file('sha256', $file->getRealPath()) : null, $file?->getClientOriginalName(),
        ]));
        $message = DB::transaction(function () use ($conversation, $authId, $validated, $file, $fingerprint) {
            User::whereKey($authId)->lockForUpdate()->firstOrFail();
            Conversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $key = $validated['client_message_id'] ?? null;
            if ($key) {
                $existing = Message::where('sender_id', $authId)->where('client_message_id', $key)->first();
                if ($existing) {
                    abort_unless(hash_equals($existing->request_hash, $fingerprint), 409, 'This send reference was already used for a different message.');
                    return $existing;
                }
            }
            $data = [
                'conversation_id' => $conversation->id, 'sender_id' => $authId,
                'reply_to_id' => $validated['reply_to_id'] ?? null, 'body' => $validated['body'] ?? null,
                'client_message_id' => $key, 'request_hash' => $fingerprint,
            ];
            if ($file) {
                $data['attachment_path'] = $file->store('message_attachments', 'public');
                $data['attachment_name'] = $file->getClientOriginalName();
                $data['attachment_type'] = $file->getClientMimeType();
            }
            $created = Message::create($data);
            $conversation->users()->updateExistingPivot($authId, ['is_hidden' => false]);
            $conversation->touch();
            return $created;
        });
        $message->load(['repliedTo' => fn ($q) => $q->visibleTo($authId)->with('sender')]);
        Cache::forget('typing:'.$conversation->id.':'.$authId);

        return response()->json([
            'message' => [
                'id' => $message->id,
                'client_message_id' => $message->client_message_id,
                'body' => $message->body,
                'attachment_url' => $message->attachment_path ? Storage::url($message->attachment_path) : null,
                'attachment_name' => $message->attachment_name,
                'attachment_type' => $message->attachment_type,
                'is_mine' => true,
                'sender_name' => auth()->user()->first_name,
                'created_at' => $message->created_at->format('M d, g:i A'),
                'is_edited' => (bool) $message->edited_at,
                'is_unsent' => (bool) $message->is_unsent,
                'reply_to' => ($message->reply_to_id && $message->repliedTo && !$message->repliedTo->is_unsent) ? [
                    'sender_name' => $message->repliedTo->sender->first_name,
                    'body' => mb_strimwidth($message->repliedTo->body ?: 'Attachment', 0, 80, '…'),
                ] : null,
            ],
        ]);
    }

    public function editMessage(Request $request, Message $message)
    {
        abort_unless($message->sender_id === auth()->id(), 403);
        abort_unless($message->conversation->users()->where('users.id', auth()->id())->exists(), 403);
        abort_if($message->is_unsent, 422, 'Cannot edit an unsent message.');

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message->update([
            'body' => $validated['body'],
            'edited_at' => now(),
        ]);

        return response()->json(['message' => 'Message updated.']);
    }

    public function deleteMessageForMe(Message $message)
    {
        abort_unless($message->conversation->users()->where('users.id', auth()->id())->exists(), 403);
        DB::table('message_deletions')->insertOrIgnore(['message_id' => $message->id, 'user_id' => auth()->id()]);
        return response()->json(['message' => 'Message deleted for you.']);
    }

    public function deleteMessage(Message $message)
    {
        abort_unless($message->sender_id === auth()->id(), 403);
        abort_unless($message->conversation->users()->where('users.id', auth()->id())->exists(), 403);

        if ($message->attachment_path) {
            Storage::disk('public')->delete($message->attachment_path);
        }

        $message->reactions()->delete();
        $message->update([
            'body' => null,
            'attachment_path' => null,
            'attachment_name' => null,
            'attachment_type' => null,
            'is_unsent' => true,
        ]);

        return response()->json(['message' => 'Message unsent.']);
    }

    public function addMember(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->is_group, 422);
        abort_unless($conversation->users->contains('id', auth()->id()), 403);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        if ($conversation->users->contains('id', $validated['user_id'])) {
            return response()->json(['message' => 'User is already in this group.'], 422);
        }

        $conversation->users()->attach($validated['user_id'], ['last_read_at' => null]);

        return response()->json(['message' => 'Member added.']);
    }

    public function removeMember(Conversation $conversation, User $user)
    {
        abort_unless($conversation->is_group, 422);
        abort_unless($conversation->users->contains('id', auth()->id()), 403);

        $conversation->users()->detach($user->id);

        return response()->json(['message' => 'Member removed.']);
    }

    public function unreadCount()
    {
        $userId = auth()->id();

        // Muted conversations retain unread counts in the list, but do not alert.
        $unreadConversations = Conversation::whereHas('users', fn ($q) => $q->where('users.id', $userId)->where(fn ($mute) => $mute->where('conversation_user.is_muted', false)->orWhere('conversation_user.muted_until', '<=', now())))
            ->with(['users', 'latestMessage'])
            ->get()
            ->filter(function ($conv) use ($userId) {
                $pivot = $conv->users->firstWhere('id', $userId)->pivot;
                if (!$conv->visibleTo($userId)) return false;

                return $conv->messages()->visibleTo($userId)
                    ->where('sender_id', '!=', $userId)
                    ->where('id', '>', max($pivot->last_read_message_id, $pivot->cleared_through_message_id))
                    ->exists();
            });

        $count = $unreadConversations->count();

        // Map them into brief notification summaries
        $notifications = $unreadConversations->map(function ($conv) use ($userId) {
            $otherUsers = $conv->users->where('id', '!=', $userId)->values();
            $firstOther = $otherUsers->first();
            
            $name = $conv->is_group 
                ? ($conv->name ?: 'Group') 
                : trim(($firstOther->first_name ?? '') . ' ' . ($firstOther->last_name ?? ''));
            
            $last = $conv->messages()->visibleTo($userId)->where('id', '>', $conv->clearedThrough($userId))->latest('id')->first();
            $text = $last?->is_unsent ? 'Message unsent' : ($last?->body ?? 'Attachment');
            
            return [
                'name' => $name,
                'text' => strlen($text) > 40 ? substr($text, 0, 40) . '...' : $text,
                'time' => $last?->created_at->diffForHumans() ?? '',
            ];
        })->values();

        return response()->json([
            'count' => $count,
            'notifications' => $notifications
        ]);
    }


    public function react(Request $request, Message $message)
    {
        abort_unless($message->conversation->users()->where('users.id', auth()->id())->exists(), 403);
        $validated = $request->validate([
            'emoji' => ['required', Rule::in(['👍', '❤️', '😂', '🎉', '😮', '😢'])],
        ]);
        DB::transaction(function () use ($message, $validated) {
            $locked = Message::whereKey($message->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->is_unsent, 422, 'Cannot react to an unsent message.');
            $reaction = $locked->reactions()->where('user_id', auth()->id())->where('emoji', $validated['emoji'])->first();
            if ($reaction) {
                $reaction->delete();
            } else {
                $locked->reactions()->create(['user_id' => auth()->id(), 'emoji' => $validated['emoji']]);
            }
        });
        return response()->json(['reactions' => $message->reactions()->get()->groupBy('emoji')->map(fn ($items, $emoji) => [
            'emoji' => $emoji, 'count' => $items->count(), 'is_mine' => $items->contains('user_id', auth()->id()),
        ])->values()]);
    }

    public function info(Conversation $conversation)
    {
        abort_unless($conversation->users->contains('id', auth()->id()), 403);
        $pivot = $conversation->users->firstWhere('id', auth()->id())->pivot;
        return response()->json([
            'is_group' => $conversation->is_group,
            'is_muted' => $conversation->isMutedFor(auth()->id()),
            'muted_until' => $pivot->muted_until ? \Illuminate\Support\Carbon::parse($pivot->muted_until)->toIso8601String() : null,
            'members' => $conversation->users->map(fn ($user) => [
                'id' => $user->id,
                'name' => trim($user->first_name.' '.$user->last_name),
                'profile_picture' => $user->profile_picture ? Storage::url($user->profile_picture) : null,
                'profile_url' => route('users.profile', $user),
            ]),
        ]);
    }

    public function mute(Request $request, Conversation $conversation)
    {
        abort_unless($conversation->users()->where('users.id', auth()->id())->exists(), 403);
        $validated = $request->validate([
            'is_muted' => ['required', 'boolean'],
            'duration' => ['nullable', Rule::in(['15', '60', '480', '1440', 'forever'])],
        ]);
        $duration = $validated['duration'] ?? 'forever';
        $until = $validated['is_muted'] && $duration !== 'forever' ? now()->addMinutes((int) $duration) : null;
        $conversation->users()->updateExistingPivot(auth()->id(), [
            'is_muted' => $validated['is_muted'], 'muted_until' => $until,
        ]);
        return response()->json(['is_muted' => (bool) $validated['is_muted'], 'muted_until' => $until]);
    }

    public function leave(Conversation $conversation)
    {
        abort_unless($conversation->users()->where('users.id', auth()->id())->exists(), 403);
        abort_unless($conversation->is_group, 422);
        $conversation->users()->detach(auth()->id());
        return response()->json(['message' => 'You left the group.']);
    }

    public function destroyConversation(Conversation $conversation)
    {
        abort_unless($conversation->users->contains('id', auth()->id()), 403);
        DB::transaction(function () use ($conversation) {
            Conversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $lastId = $conversation->messages()->max('id') ?? 0;
            $conversation->users()->updateExistingPivot(auth()->id(), [
                'cleared_through_message_id' => $lastId, 'is_hidden' => true,
            ]);
        });
        Cache::forget('typing:'.$conversation->id.':'.auth()->id());
        return response()->json(['message' => 'Conversation deleted for you. Other members keep their messages.']);
    }
}
