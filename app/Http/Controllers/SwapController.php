<?php

namespace App\Http\Controllers;

use App\Models\Swap;
use App\Models\User;
use App\Models\UserSubjectQualification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\TeachingEligibility;

class SwapController extends Controller
{

    public function options(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'Choose another member.');
        $subjects = fn ($id) => UserSubjectQualification::where('user_id', $id)->where('status', 'approved')
            ->orderBy('subject_name')->pluck('subject_name')->unique()->values();
        return response()->json([
            'peer_name' => trim($user->first_name.' '.$user->last_name),
            'peer_subjects' => $subjects($user->id),
            'my_subjects' => $subjects($request->user()->id),
        ]);
    }

    public function add(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'request_type' => ['required', 'in:learning,skill_swap'],
            'requested_subject' => ['required', 'string', 'max:255'],
            'offered_subject' => ['required_if:request_type,skill_swap', 'prohibited_if:request_type,learning', 'nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);
        $peerId = (int) $validated['user_id'];
        abort_if($peerId === $request->user()->id, 422, 'You cannot send a request to yourself.');
        $swap = DB::transaction(function () use ($request, $validated, $peerId) {
            // Serialize requests by this sender so simultaneous clicks cannot reset a request.
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $swap = Swap::where('requester_id', $request->user()->id)->where('requested_user_id', $peerId)->lockForUpdate()->first();
            if ($swap && $swap->status === 'accepted') {
                $previousChat = $swap->conversation;
                abort_unless($previousChat && in_array($previousChat->session_context['state'] ?? '', ['completed', 'cancelled']), 409, 'Finish or cancel your existing session before starting another with this peer.');
                $previousChat->update(['swap_id' => null]);
            }
            abort_if($swap && $swap->status === 'pending', 409, 'You already have a pending request with this peer.');
            TeachingEligibility::check($request->user()->id, $peerId, $validated['request_type'], $validated['requested_subject'], $validated['offered_subject'] ?? null);
            $swap ??= new Swap(['requester_id' => $request->user()->id, 'requested_user_id' => $peerId]);
            $swap->fill([
                'request_type' => $validated['request_type'], 'requested_subject' => $validated['requested_subject'],
                'offered_subject' => $validated['offered_subject'] ?? null, 'message' => $validated['message'] ?? null,
                'status' => 'pending', 'responded_at' => null, 'response_read_at' => null,
            ])->save();
            \App\Support\BellNotifications::record($peerId, 'swap:'.$swap->id.':request:'.$swap->updated_at->timestamp, 'swap', 'New learn/swap request', $request->user()->first_name.' sent you a learn/swap request.', route('swap', ['tab' => 'received']).'#swap-'.$swap->id);
            return $swap;
        });
        return response()->json(['status' => 'success', 'swap_id' => $swap->id, 'redirect' => route('swap')]);
    }
    public function index()
    {
        // Upgrade earlier accepted requests to the dedicated conversation flow.
        Swap::where('status', 'accepted')->whereIn('request_type', ['learning', 'skill_swap'])
            ->where(fn ($q) => $q->where('requester_id', auth()->id())->orWhere('requested_user_id', auth()->id()))
            ->whereDoesntHave('conversation')->get()->each(function ($swap) {
                DB::transaction(function () use ($swap) {
                    $locked = Swap::whereKey($swap->id)->lockForUpdate()->first();
                    if ($locked && $locked->status === 'accepted') \App\Support\SessionChats::create($locked);
                });
            });
        if (!request()->boolean('refresh') && request('tab') !== 'received') {
            Swap::where('requester_id', auth()->id())->whereIn('status', ['accepted', 'declined'])->whereNull('response_read_at')->update(['response_read_at' => now()]);
            \App\Support\BellNotifications::query(auth()->id())->where('kind', 'swap')
                ->where('event_key', 'like', 'swap:%:response:%')->whereNull('read_at')->update(['read_at' => now()]);
        }
        $sent = Swap::with(['requestedUser', 'requester'])
            ->where('requester_id', auth()->id())
            ->get();

        $received = Swap::with(['requestedUser', 'requester'])
            ->where('requested_user_id', auth()->id())
            ->get();

        return view('swap', compact('sent', 'received'));
    }

    public function respond(Request $request, Swap $swap)
    {
        // Only the requested user (receiver) can respond
        if ($swap->requested_user_id !== auth()->id()) {
            return response()->json([
                'message' => 'Unauthorized action.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:accepted,declined',
        ]);

        DB::transaction(function () use ($swap, $validated) {
            $locked = Swap::whereKey($swap->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 409, 'This request has already been answered.');
            if ($validated['status'] === 'accepted') {
                TeachingEligibility::check($locked->requester_id, $locked->requested_user_id, $locked->request_type ?? '', $locked->requested_subject, $locked->offered_subject);
            }
            if ($validated['status'] === 'accepted') \App\Support\SessionChats::create($locked);
            $locked->update(['status' => $validated['status'], 'responded_at' => now(), 'response_read_at' => null]);
            \App\Support\BellNotifications::record($locked->requester_id, 'swap:'.$locked->id.':response:'.$locked->updated_at->timestamp, 'swap', 'Request '.$locked->status, 'Your learn/swap request was '.$locked->status.'.', route('swap', ['tab' => 'sent']).'#swap-'.$locked->id);
        });
        $swap->refresh();
        return response()->json([
            'status' => 'success',
            'swap_status' => $swap->status,
            'message' => $validated['status'] === 'accepted'
                ? 'Swap accepted! Redirecting to messages...'
                : 'Swap declined.',
            'redirect' => $validated['status'] === 'accepted' ? route('messages', ['conversation' => \App\Models\Conversation::where('swap_id', $swap->id)->value('id')]) : null,
        ]);
    }

    public function destroy(Swap $swap)
    {
        // Check if the logged-in user is EITHER the sender OR the recipient
        if (auth()->id() !== $swap->requester_id && auth()->id() !== $swap->requested_user_id) {
            abort(403, 'You do not have permission to remove this swap.');
        }

        $swap->delete();

        // Handle AJAX/JSON requests
        if (request()->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Swap request removed successfully.',
            ]);
        }

        // Handle standard form submissions
        return redirect()
            ->route('swap')
            ->with('success', 'Swap request removed successfully.');
    }

    public function store(Request $request)
    {
        $targetUser = User::findOrFail($request->target_user_id);

        // Prevent sending to unverified users
        if (!$targetUser->hasVerifiedEmail()) { 
            return response()->json([
                'status' => 'error',
                'message' => 'This student must verify their account before they can receive swap requests.'
            ], 403);
        }
        
    }

    public function accept(Swap $swap)
    {
        // Prevent unverified users from accepting requests
        if (!auth()->user()->hasVerifiedEmail()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You must verify your account before you can accept swap requests.'
            ], 403);
        }

    }
}
