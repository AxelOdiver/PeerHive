<?php

namespace App\Http\Controllers;

use App\Models\Swap;
use App\Models\User;
use App\Models\UserSubjectQualification;
use Illuminate\Http\Request;

class SwapController extends Controller
{

    public function add(Request $request)
    {
      
        // THE GATEKEEPER CHECK
        $isVerified = UserSubjectQualification::where('user_id', auth()->id())
            ->where('status', 'approved')
            ->exists();

        if (!$isVerified) {
            // This triggers the 403 error that your SweetAlert is waiting for!
            return response()->json([
                'message' => 'You must have an approved subject qualification before you can swap with peers!'
            ], 403); 
        }

        // NORMAL SWAP LOGIC
        $validated = $request->validate([
            'id' => ['nullable', 'integer', 'exists:users,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'message' => ['nullable', 'string'],
        ]);

        $userId = (int) ($validated['user_id'] ?? $validated['id'] ?? 0);

        if ($request->user()->id === $userId) {
            return response()->json([
                'message' => 'You cannot start a swap with yourself.',
            ], 422);
        }

        $swap = Swap::updateOrCreate(
            [
                'requester_id' => $request->user()->id,
                'requested_user_id' => $userId,
            ],
            [
                'message' => $validated['message'] ?? null,
                'status' => 'pending',
                'responded_at' => null,
            ]
        );

        return response()->json([
            'status' => 'success',
            'swap_id' => $swap->id,
            'redirect' => route('swap'),
        ]);
    }

    public function index()
    {
        $sent = Swap::with('requestedUser')
            ->where('requester_id', auth()->id())
            ->get();

        $received = Swap::with('requester')
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

        $swap->update([
            'status' => $validated['status'],
            'responded_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'swap_status' => $swap->status,
            'message' => $validated['status'] === 'accepted'
                ? 'Swap accepted! Redirecting to messages...'
                : 'Swap declined.',
            'redirect' => $validated['status'] === 'accepted' ? route('messages') : null,
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
