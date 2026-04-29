<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class RegisterController extends Controller
{
    public function index()
    {
        return view('register');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).+$/'],
    ], [
        'password.regex' => 'Password must contain at least one uppercase letter, one number, and one special character.',
    ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'],
            'last_name' => $validated['last_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);


        if ($request->expectsJson()) {
            return response()->json([
                'redirect' => route('login'),
                'message' => 'Registration successful!',
            ]);
        }

        return redirect()->route('login')->with('success', 'Registration successful!');
    }
}
