<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
            ->get()
            ->map(function ($conv) use ($userId) {
                $pivot = $conv->users->firstWhere('id', $userId)->pivot;
                $lastReadAt = $pivot->last_read_at;

                $unreadCount = $conv->messages()
                    ->where('sender_id', '!=', $userId)
                    ->when($lastReadAt, fn ($q) => $q->where('created_at', '>', $lastReadAt))
                    ->count();

                $otherUsers = $conv->users->where('id', '!=', $userId)->values();
                $firstOther = $otherUsers->first();

                $name = $conv->is_group
                    ? ($conv->name ?: $otherUsers->pluck('first_name')->implode(', '))
                    : trim(($firstOther->first_name ?? '') . ' ' . ($firstOther->last_name ?? ''));

                $initials = $conv->is_group
                    ? strtoupper(substr($name, 0, 2))
                    : strtoupper(substr($firstOther->first_name ?? '', 0, 1) . substr($firstOther->last_name ?? '', 0, 1));

                $last = $conv->latestMessage;
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
                ];
            })
            ->sortByDesc('last_message_at')
            ->values();

        return response()->json(['conversations' => $conversations]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
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

        $query = $conversation->messages()->with(['sender', 'repliedTo.sender'])->orderByDesc('id');

        if ($beforeId) {
            $query->where('id', '<', $beforeId);
        }

        $messages = $query->limit($perPage)->get()->reverse()->values();

        $hasMore = $conversation->messages()
            ->where('id', '<', $messages->first()?->id ?? 0)
            ->exists();

        if (!$beforeId) {
            $conversation->users()->updateExistingPivot($authId, ['last_read_at' => now()]);
        }

        return response()->json([
            'is_group' => $conversation->is_group,
            'has_more' => $hasMore,
            'members' => $conversation->users->map(fn ($u) => [
                'id' => $u->id,
                'name' => trim($u->first_name . ' ' . $u->last_name),
            ]),
            'messages' => $messages->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->is_unsent ? null : $m->body,
                'attachment_url' => (!$m->is_unsent && $m->attachment_path) ? Storage::url($m->attachment_path) : null,
                'attachment_name' => $m->is_unsent ? null : $m->attachment_name,
                'attachment_type' => $m->is_unsent ? null : $m->attachment_type,
                'is_mine' => $m->sender_id === $authId,
                'sender_name' => $m->sender->first_name,
                'created_at' => $m->created_at->format('M d, g:i A'),
                'is_edited' => (bool) $m->edited_at,
                'is_unsent' => (bool) $m->is_unsent,
                'reply_to' => ($m->reply_to_id && $m->repliedTo && !$m->repliedTo->is_unsent) ? [
                    'sender_name' => $m->repliedTo->sender->first_name,
                    'body' => strlen($m->repliedTo->body) > 50 ? substr($m->repliedTo->body, 0, 50) . '...' : ($m->repliedTo->body ?: 'Attachment'),
                ] : null,
            ]),
        ]);
    }

    public function storeMessage(Request $request, Conversation $conversation)
    {
        $authId = auth()->id();
        abort_unless($conversation->users->contains('id', $authId), 403);

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'max:10240'],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
        ]);

        if (empty($validated['body']) && !$request->hasFile('attachment')) {
            return response()->json(['message' => 'Message cannot be empty.'], 422);
        }

        $data = [
            'conversation_id' => $conversation->id,
            'sender_id' => $authId,
            'reply_to_id' => $validated['reply_to_id'] ?? null,
            'body' => $validated['body'] ?? null,
        ];

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $data['attachment_path'] = $file->store('message_attachments', 'public');
            $data['attachment_name'] = $file->getClientOriginalName();
            $data['attachment_type'] = $file->getClientMimeType();
        }

        $message = Message::create($data);
        $message->load('repliedTo.sender');
        $conversation->touch();
        $conversation->users()->updateExistingPivot($authId, ['last_read_at' => now()]);

        return response()->json([
            'message' => [
                'id' => $message->id,
                'body' => $message->body,
                'attachment_url' => $message->attachment_path ? Storage::url($message->attachment_path) : null,
                'attachment_name' => $message->attachment_name,
                'attachment_type' => $message->attachment_type,
                'is_mine' => true,
                'sender_name' => auth()->user()->first_name,
                'created_at' => $message->created_at->format('M d, g:i A'),
                'is_edited' => false,
                'is_unsent' => false,
                'reply_to' => ($message->reply_to_id && $message->repliedTo && !$message->repliedTo->is_unsent) ? [
                    'sender_name' => $message->repliedTo->sender->first_name,
                    'body' => strlen($message->repliedTo->body) > 50 ? substr($message->repliedTo->body, 0, 50) . '...' : ($message->repliedTo->body ?: 'Attachment'),
                ] : null,
            ],
        ]);
    }

    public function editMessage(Request $request, Message $message)
    {
        abort_unless($message->sender_id === auth()->id(), 403);
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

    public function deleteMessage(Message $message)
    {
        abort_unless($message->sender_id === auth()->id(), 403);

        if ($message->attachment_path) {
            Storage::disk('public')->delete($message->attachment_path);
        }

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

        // Get all conversations with unread messages
        $unreadConversations = Conversation::whereHas('users', fn ($q) => $q->where('users.id', $userId))
            ->with(['users', 'latestMessage'])
            ->get()
            ->filter(function ($conv) use ($userId) {
                $pivot = $conv->users->firstWhere('id', $userId)->pivot;
                $lastReadAt = $pivot->last_read_at;

                return $conv->messages()
                    ->where('sender_id', '!=', $userId)
                    ->when($lastReadAt, fn ($q) => $q->where('created_at', '>', $lastReadAt))
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
            
            $last = $conv->latestMessage;
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
}