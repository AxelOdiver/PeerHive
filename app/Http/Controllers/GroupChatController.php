<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\User;
use App\Support\BellNotifications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class GroupChatController extends Controller
{
    private function memberRole(Conversation $conversation, int $userId): string
    {
        abort_unless($conversation->is_group && !$conversation->session_context, 404, 'Group chat not found.');
        $role = DB::table('conversation_user')->where('conversation_id', $conversation->id)
            ->where('user_id', $userId)->value('group_role');
        abort_unless($role, 403, 'You are not a member of this group.');
        return $role;
    }

    private function manager(Conversation $conversation): string
    {
        $role = $this->memberRole($conversation, auth()->id());
        abort_unless(in_array($role, ['owner', 'admin'], true), 403, 'Only an owner or admin can manage the group.');
        return $role;
    }

    private function owner(Conversation $conversation): void
    {
        abort_unless($this->memberRole($conversation, auth()->id()) === 'owner', 403, 'Only the owner can change group roles.');
    }

    public function rename(Request $request, Conversation $conversation)
    {
        $this->manager($conversation);
        $data = $request->validate(['name' => ['required', 'string', 'min:2', 'max:80']]);
        $name = trim($data['name']);
        DB::table('conversations')->where('id', $conversation->id)->update(['name' => $name, 'updated_at' => now()]);
        return response()->json(['message' => 'Group renamed.', 'name' => $name]);
    }

    public function uploadPhoto(Request $request, Conversation $conversation)
    {
        $this->manager($conversation);
        $data = $request->validate(['photo' => ['required', 'file', 'max:2048', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp']]);
        $file = $data['photo'];
        $bytes = file_get_contents($file->getRealPath());
        $mime = @getimagesizefromstring($bytes)['mime'] ?? null;
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 422, 'Please upload a valid JPEG, PNG, or WebP image.');
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
        $path = 'group_photos/'.bin2hex(random_bytes(16)).'.'.$extension;
        Storage::disk('local')->put($path, $bytes);
        try {
            $prior = DB::transaction(function () use ($conversation, $path) {
                $old = DB::table('conversations')->where('id', $conversation->id)->lockForUpdate()->value('group_photo_path');
                DB::table('conversations')->where('id', $conversation->id)->update(['group_photo_path' => $path, 'updated_at' => now()]);
                return $old;
            });
        } catch (\Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }
        if ($prior) Storage::disk('local')->delete($prior);
        return response()->json(['message' => 'Group photo updated.', 'photo_url' => route('messages.group.photo', $conversation)]);
    }

    public function photo(Conversation $conversation)
    {
        $this->memberRole($conversation, auth()->id());
        $path = DB::table('conversations')->where('id', $conversation->id)->value('group_photo_path');
        abort_unless($path && preg_match('#^group_photos/[a-f0-9]{32}\.(jpg|png|webp)$#D', $path), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        return response(Storage::disk('local')->get($path), 200, [
            'Content-Type' => ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$extension],
            'Cache-Control' => 'private, no-store', 'Content-Security-Policy' => "sandbox; default-src 'none'",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function removePhoto(Conversation $conversation)
    {
        $this->manager($conversation);
        $path = DB::table('conversations')->where('id', $conversation->id)->value('group_photo_path');
        DB::table('conversations')->where('id', $conversation->id)->update(['group_photo_path' => null]);
        if ($path) Storage::disk('local')->delete($path);
        return response()->json(['message' => 'Group photo removed.']);
    }

    public function changeRole(Request $request, Conversation $conversation, User $user)
    {
        $this->owner($conversation);
        $data = $request->validate(['role' => ['required', Rule::in(['admin', 'member', 'owner'])]]);
        abort_if($user->id === auth()->id(), 422, 'Choose another member to change roles.');
        DB::transaction(function () use ($conversation, $user, $data) {
            DB::table('conversations')->where('id', $conversation->id)->lockForUpdate()->first();
            abort_unless(DB::table('conversation_user')->where('conversation_id', $conversation->id)->where('user_id', auth()->id())->value('group_role') === 'owner', 403);
            $target = DB::table('conversation_user')->where('conversation_id', $conversation->id)->where('user_id', $user->id)->first();
            abort_unless($target, 404);
            abort_if($target->group_role === 'owner', 409, 'The group already has an owner.');
            if ($data['role'] === 'owner') {
                DB::table('conversation_user')->where('conversation_id', $conversation->id)->where('user_id', auth()->id())->update(['group_role' => 'admin']);
            }
            DB::table('conversation_user')->where('conversation_id', $conversation->id)->where('user_id', $user->id)->update(['group_role' => $data['role']]);
        });
        return response()->json(['message' => $data['role'] === 'owner' ? 'Ownership transferred.' : 'Member role updated.']);
    }

    public function invite(Request $request, Conversation $conversation)
    {
        $role = $this->memberRole($conversation, auth()->id());
        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);
        $target = (int) $data['user_id'];
        abort_if($target === auth()->id(), 422, 'You cannot invite yourself.');
        abort_if($conversation->users()->where('users.id', $target)->exists(), 409, 'This student is already a member.');
        $status = in_array($role, ['owner', 'admin'], true) ? 'invited' : 'approval_requested';
        DB::transaction(function () use ($conversation, $target, $status) {
            DB::table('conversations')->where('id', $conversation->id)->lockForUpdate()->first();
            $existing = DB::table('group_invitations')->where('conversation_id', $conversation->id)->where('target_user_id', $target)->first();
            abort_if($existing && in_array($existing->status, ['invited', 'approval_requested'], true), 409, 'This invitation is already pending.');
            DB::table('group_invitations')->updateOrInsert(['conversation_id' => $conversation->id, 'target_user_id' => $target], [
                'requested_by' => auth()->id(), 'approved_by' => $status === 'invited' ? auth()->id() : null,
                'status' => $status, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        if ($status === 'invited') {
            BellNotifications::record($target, 'group:'.$conversation->id.':invite:'.$target.':'.now()->timestamp, 'message',
                'Group invitation', 'You were invited to join '.$conversation->name.'.', route('messages', ['invitation' => $conversation->id]));
        } else {
            $managers = DB::table('conversation_user')->where('conversation_id', $conversation->id)
                ->whereIn('group_role', ['owner', 'admin'])->pluck('user_id');
            foreach ($managers as $managerId) {
                BellNotifications::record((int) $managerId, 'group:'.$conversation->id.':approve:'.$target.':'.now()->timestamp,
                    'message', 'Group invite approval', 'A member requested an invitation to '.$conversation->name.'.',
                    route('messages', ['conversation' => $conversation->id]));
            }
        }
        return response()->json(['message' => $status === 'invited' ? 'Invitation sent. The student must accept to join.' : 'Request sent to the group owner and admins for approval.']);
    }

    public function decide(Request $request, int $invitation)
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject', 'accept', 'decline', 'withdraw'])]]);
        $result = DB::transaction(function () use ($invitation, $data) {
            $row = DB::table('group_invitations')->where('id', $invitation)->lockForUpdate()->first();
            abort_unless($row, 404);
            $conversation = Conversation::whereKey($row->conversation_id)->lockForUpdate()->firstOrFail();
            $action = $data['decision'];
            if (in_array($action, ['approve', 'reject'], true)) {
                $this->manager($conversation);
                abort_unless($row->status === 'approval_requested', 409, 'This request is no longer awaiting approval.');
                $next = $action === 'approve' ? 'invited' : 'rejected';
                $approved = $action === 'approve' ? auth()->id() : null;
            } elseif ($action === 'withdraw') {
                $role = $this->memberRole($conversation, auth()->id());
                abort_unless($row->requested_by === auth()->id() || in_array($role, ['owner', 'admin'], true), 403);
                abort_unless(in_array($row->status, ['invited', 'approval_requested'], true), 409);
                $next = 'withdrawn'; $approved = $row->approved_by;
            } else {
                abort_unless($row->target_user_id === auth()->id(), 403);
                abort_unless($row->status === 'invited', 409, 'This invitation is no longer active.');
                $next = $action === 'accept' ? 'accepted' : 'declined'; $approved = $row->approved_by;
                if ($next === 'accepted') {
                    abort_if($conversation->users()->where('users.id', auth()->id())->exists(), 409, 'Already a member.');
                    $lastMessageId = (int) ($conversation->messages()->max('id') ?? 0);
                    $conversation->users()->attach(auth()->id(), [
                        'last_read_at' => now(), 'last_read_message_id' => $lastMessageId,
                        'cleared_through_message_id' => $lastMessageId, 'group_role' => 'member',
                    ]);
                }
            }
            DB::table('group_invitations')->where('id', $row->id)->update(['status' => $next, 'approved_by' => $approved, 'updated_at' => now()]);
            return [$conversation, $next, $row->target_user_id];
        });
        [$conversation, $status, $target] = $result;
        if ($status === 'invited') {
            BellNotifications::record($target, 'group:'.$conversation->id.':invite:'.$target.':'.now()->timestamp, 'message',
                'Group invitation', 'You were invited to join '.$conversation->name.'.', route('messages', ['invitation' => $conversation->id]));
        }
        return response()->json(['message' => match ($status) {
            'invited' => 'Invitation approved and sent.', 'rejected' => 'Request rejected.', 'accepted' => 'You joined the group.',
            'declined' => 'Invitation declined.', default => 'Invitation withdrawn.',
        }, 'status' => $status]);
    }
}
