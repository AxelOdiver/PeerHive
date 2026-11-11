<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\LoginOtp;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9_]+$/', 'unique:users,username'],
            'first_name' => ['required', 'string', 'max:255', 'regex:/\A[\p{L}\p{M}]+(?:[ \'-][\p{L}\p{M}]+)*\z/u'],
            'middle_name' => ['nullable', 'string', 'max:255', 'regex:/\A[\p{L}\p{M}]+(?:[ \'-][\p{L}\p{M}]+)*\z/u'],
            'last_name' => ['required', 'string', 'max:255', 'regex:/\A[\p{L}\p{M}]+(?:[ \'-][\p{L}\p{M}]+)*\z/u'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).+$/'],
            'accept_policies' => ['required', 'accepted'],
    ], [
        'password.regex' => 'Password must contain at least one uppercase letter, one number, and one special character.',
            'first_name.regex' => 'Use letters, spaces, apostrophes, or hyphens only.',
            'middle_name.regex' => 'Use letters, spaces, apostrophes, or hyphens only.',
            'last_name.regex' => 'Use letters, spaces, apostrophes, or hyphens only.',
    ]);

        $user = DB::transaction(function () use ($validated) {
            $user = new User([
                'first_name' => $validated['first_name'],
                'username' => $validated['username'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
            ]);
            $user->forceFill([
                'policies_accepted_at' => now(),
                'terms_version' => config('policies.terms_version'),
                'privacy_version' => config('policies.privacy_version'),
                'onboarding_required' => true,
            ])->save();
            return $user;
        });

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        LoginOtp::create([
            'user_id' => $user->id, 'code' => '******',
            'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes(10),
        ]);
        // Pending registration is not authenticated until the email code is accepted.
        $request->session()->put(['otp_user_id' => $user->id, 'otp_purpose' => 'registration']);
        Mail::to($user->email)->send(new OtpMail($code, $user->first_name));

        if ($request->expectsJson()) {
            return response()->json([
                'redirect' => route('otp.show'),
                'message' => 'Account created. Verify your email to continue.',
            ]);
        }
        return redirect()->route('otp.show');
    }
}
