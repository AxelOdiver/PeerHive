<?php

namespace App\Http\Controllers;

use App\Models\Swap;
use App\Models\User;
use Illuminate\Http\Request;

class SwapController extends Controller
{
    public function add(Request $request)
    {
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
        abort_if($swap->requester_id !== auth()->id(), 403);

        $swap->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Swap request cancelled successfully.',
            ]);
        }

        return redirect()
            ->route('swap')
            ->with('success', 'Swap request cancelled successfully.');
    }
}
