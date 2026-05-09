<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Mail\OtpMail;
use App\Models\LoginOtp;
use App\Models\TrustedDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class LoginController extends Controller
{
    public function show()
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request)
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials, false)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Invalid email or password.'], 401);
            }
            return back()->withErrors(['email' => 'Invalid email or password.']);
        }

        $user = Auth::user();
        Auth::logout();

        // ── Check for a trusted-device cookie ──────────────────────────
        $deviceToken = $request->cookie('trusted_device');

        if ($deviceToken) {
            $trusted = TrustedDevice::where('user_id', $user->id)
                ->where('token', $deviceToken)
                ->first();

            if ($trusted) {
                $trusted->update(['last_used_at' => now()]);
                Auth::login($user);
                $request->session()->regenerate();

                if ($request->expectsJson()) {
                    $route = $user->role === 'admin' ? route('users') : route('dashboard');
                    return response()->json(['redirect' => $route]);
                }

                return $user->role === 'admin'
                    ? redirect()->route('users')
                    : redirect()->route('dashboard');
            }

            // Stale cookie — clear it
            cookie()->queue(cookie()->forget('trusted_device'));
        }

        // ── New/unknown device — generate OTP ─────────────────────────
        LoginOtp::where('user_id', $user->id)->delete();

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        LoginOtp::create([
            'user_id'    => $user->id,
            'code'       => $code,
            'expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($user->email)->send(new OtpMail($code, $user->first_name));

        session(['otp_user_id' => $user->id]);

        if ($request->expectsJson()) {
            return response()->json(['redirect' => route('otp.show')]);
        }

        return redirect()->route('otp.show');
    }

    public function destroy(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}