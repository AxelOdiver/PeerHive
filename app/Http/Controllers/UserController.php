<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // THE GATEKEEPER: Kick out non-admins
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Unauthorized. This area is for administrators only.');
        }

        return view('users');
    }
    
    /**
     * Return data for DataTables.
     */
    public function data(Request $request)
    {
        // THE GATEKEEPER: Stop non-admins from loading the JSON data
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = User::query();

        return response()->json([
            'data' => $query->get()->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => trim($user->first_name . ' ' . $user->middle_name . ' ' . $user->last_name),
                    'email' => $user->email,
                    'joined' => optional($user->created_at)->format('M d, Y'),
                ];
            }),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        $user = User::create($validated);

        return response()->json([
            'message' => 'Created successfully!',
            'user' => $user,
        ]);

    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        return response()->json([
            'user' => $user,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();

        if (empty($validated['password'])) {
            unset($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Updated successfully!',
            'user' => $user,
        ]);

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $user->delete();

        return response()->json([
            'message' => 'Deleted successfully!',
        ]);
    }
}
