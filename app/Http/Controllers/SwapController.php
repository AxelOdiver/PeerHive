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
        $sentSwaps = Swap::with('requestedUser')
            ->where('requester_id', auth()->id())
            ->latest()
            ->get();

        $receivedSwaps = Swap::with('requester')
            ->where('requested_user_id', auth()->id())
            ->latest()
            ->get();

        return view('swap', compact('sentSwaps', 'receivedSwaps'));
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
