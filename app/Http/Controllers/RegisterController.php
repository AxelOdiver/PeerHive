<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class RegisterController extends Controller
{
    public function index()
    {
        return view('Register');
    }

    public function store(Request $request)
    {
        if (is_string($request->input('username'))) {
            $request->merge(['username' => strtolower(trim($request->input('username')))]);
        }
        $validated = $request->validate([
            'username' => ['nullable', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9_]+$/', 'unique:users,username'],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).+$/'],
            'accept_policies' => ['required', 'accepted'],
    ], [
        'password.regex' => 'Password must contain at least one uppercase letter, one number, and one special character.',
    ]);

        $user = new User([
            'first_name' => $validated['first_name'],
            'username' => $validated['username'] ?? null,
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $user->forceFill([
            'policies_accepted_at' => now(),
            'terms_version' => config('policies.terms_version'),
            'privacy_version' => config('policies.privacy_version'),
        ])->save();
        if ($request->expectsJson()) {
            return response()->json([
                'redirect' => route('login'),
                'message' => 'Registration successful!',
            ]);
        }

        return redirect()->route('login')->with('success', 'Registration successful!');
    }
}
