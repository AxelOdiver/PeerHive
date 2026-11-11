<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\AccountCredentialRevoker;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function request()
    {
        return view('auth.forgot-password');
    }

    public function email(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email']]);
        $remaining = (int) $request->session()->get('password_reset_cooldown_until', 0) - now()->timestamp;
        if ($remaining <= 0) {
            Password::sendResetLink($credentials); // config/auth.php has a 60-second token throttle.
            $request->session()->put('password_reset_cooldown_until', now()->addSeconds(60)->timestamp);
        }

        return back()->with('status', 'If an account exists for this email, a password reset link has been sent. Check your inbox and spam folder. Please wait a minute before requesting another link.');
    }

    public function edit(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function update(Request $request)
    {
        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).+$/'],
        ], [
            'password.regex' => 'Password must contain at least one uppercase letter, one number, and one special character.',
        ]);

        $status = Password::reset($credentials, function (User $user, string $password) {
            DB::transaction(function () use ($user, $password) {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                AccountCredentialRevoker::revoke($user);
            });
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'This reset link is invalid or has expired. Please request a new one.']);
        }

        $request->session()->forget(['otp_user_id', 'otp_purpose']);
        cookie()->queue(cookie()->forget('trusted_device'));

        return redirect()->route('login')->with('status', 'Your password has been reset. Log in with your new password.');
    }
}

