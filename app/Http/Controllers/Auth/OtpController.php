<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Models\LoginOtp;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class OtpController extends Controller
{
    // How long (days) the trusted-device cookie lasts
    private const TRUST_DAYS = 30;

    public function show(Request $request)
    {
        if (! $request->session()->has('otp_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.otp');
    }

    public function verify(Request $request)
    {
        $request->validate([
            'code'          => ['required', 'string', 'size:6'],
            'trust_device'  => ['nullable', 'boolean'],
        ]);

        $userId = $request->session()->get('otp_user_id');

        if (! $userId) {
            return $this->sessionExpiredResponse($request);
        }

        $otp = LoginOtp::where('user_id', $userId)
            ->where('code', $request->code)
            ->latest()
            ->first();

        if (! $otp) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => ['code' => ['Invalid code. Please try again.']]], 422);
            }
            return back()->withErrors(['code' => 'Invalid code. Please try again.']);
        }

        if ($otp->isExpired()) {
            $otp->delete();
            if ($request->expectsJson()) {
                return response()->json(['errors' => ['code' => ['Code has expired. Please log in again.']]], 422);
            }
            return redirect()->route('login')->withErrors(['code' => 'Code has expired. Please log in again.']);
        }

        // ── Valid OTP ──────────────────────────────────────────────────
        $otp->delete();
        $request->session()->forget('otp_user_id');

        $user = User::findOrFail($userId);
        Auth::login($user);
        $request->session()->regenerate();

        // Optionally trust this device for 30 days
        $cookie = null;
        if ($request->boolean('trust_device')) {
            $token = Str::random(64);

            TrustedDevice::create([
                'user_id'       => $user->id,
                'token'         => $token,
                'device_name'   => $this->parseDeviceName($request->userAgent()),
                'last_used_at'  => now(),
            ]);

            $cookie = cookie(
                'trusted_device',
                $token,
                self::TRUST_DAYS * 24 * 60,
                '/',
                config('session.domain'),
                config('session.secure_cookie', $request->isSecure()),
                true,
                false,
                config('session.same_site', 'lax')
            );
        }

        if ($request->expectsJson()) {
            $response = response()->json( Auth::user()->role === 'admin' ? ['redirect' => route('users')] : ['redirect' => route('dashboard')]);
            return $cookie ? $response->withCookie($cookie) : $response;
        }

        $redirect = Auth::user()->role === 'admin' ? redirect()->route('users') : redirect()->route('dashboard');
        return $cookie ? $redirect->withCookie($cookie) : $redirect;
    }

    public function resend(Request $request)
    {
        $userId = $request->session()->get('otp_user_id');

        if (! $userId) {
            return $this->sessionExpiredResponse($request);
        }

        $user = User::findOrFail($userId);

        LoginOtp::where('user_id', $userId)->delete();

        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        LoginOtp::create([
            'user_id'    => $userId,
            'code'       => $code,
            'expires_at' => now()->addMinutes(10),
        ]);

        Mail::to($user->email)->send(new OtpMail($code, $user->first_name));

        if ($request->expectsJson()) {
            return response()->json(['message' => 'A new code has been sent to your email.']);
        }

        return back()->with('resent', true);
    }

    // ── Helpers ────────────────────────────────────────────────────────

    private function sessionExpiredResponse(Request $request)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Session expired. Please log in again.'], 422);
        }
        return redirect()->route('login')->withErrors(['code' => 'Session expired. Please log in again.']);
    }

    private function parseDeviceName(?string $ua): string
    {
        if (! $ua) {
            return 'Unknown device';
        }

        $browser = match (true) {
            str_contains($ua, 'Edg')     => 'Edge',
            str_contains($ua, 'Chrome')  => 'Chrome',
            str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'Safari')  => 'Safari',
            default                      => 'Browser',
        };

        $os = match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Mac')     => 'Mac',
            str_contains($ua, 'Linux')   => 'Linux',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone')  => 'iPhone',
            default                      => 'Device',
        };

        return "{$browser} on {$os}";
    }
}
