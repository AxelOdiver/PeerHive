<?php

namespace App\Http\Controllers;

use App\Mail\AccountEmailChangeMail;
use App\Support\AccountCredentialRevoker;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountSecurityController extends Controller
{
    public function requestEmail(Request $request)
    {
        if (is_string($request->input('new_email'))) {
            $request->merge(['new_email' => mb_strtolower(trim($request->input('new_email')))]);
        }
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:1024'],
            'new_email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
        ]);
        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
        }
        if (strcasecmp($data['new_email'], $user->email) === 0) {
            throw ValidationException::withMessages(['new_email' => 'Enter an email different from your current one.']);
        }
        // The encrypted payload binds this request to the original email and password hash.
        // No email or password is stored or changed before mailbox verification.
        $token = Crypt::encryptString(json_encode([
            'user_id' => $user->getKey(),
            'old_email' => $user->email,
            'new_email' => $data['new_email'],
            'password_hash' => $user->password,
            'nonce' => Str::random(24),
        ], JSON_THROW_ON_ERROR));
        $url = URL::temporarySignedRoute('profile.email.confirm', now()->addMinutes(30), ['token' => $token]);
        try {
            Mail::to($data['new_email'])->send(new AccountEmailChangeMail($url, $user->first_name));
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'Could not send the verification email. Please check the mail configuration and try again.'], 503);
        }

        return response()->json(['message' => 'Check your new inbox for a verification link. Your current email remains active until you confirm it.']);
    }

    public function confirmEmail(Request $request)
    {
        try {
            $data = json_decode(Crypt::decryptString((string)$request->query('token')), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            abort(403, 'This verification link is invalid.');
        }
        if (!is_array($data) || !isset($data['user_id'], $data['old_email'], $data['new_email'], $data['password_hash'])) {
            abort(403, 'This verification link is invalid.');
        }
        DB::transaction(function () use ($data) {
            $user = User::query()->whereKey($data['user_id'])->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals((string)$data['old_email'], (string)$user->email)
                && hash_equals((string)$data['password_hash'], (string)$user->password), 403, 'This email-change request is no longer valid.');
            $alreadyUsed = User::query()->where('email', $data['new_email'])->where('id', '!=', $user->id)->exists();
            abort_if($alreadyUsed, 409, 'That email has already been registered.');
            $user->forceFill(['email' => $data['new_email'], 'email_verified_at' => now()])->save();
        });
        if (auth()->check()) {
            return redirect()->route('profile', ['tab' => 'account', 'email' => 'verified'])
                ->with('success', 'Your new email address is verified and active.');
        }
        return redirect()->route('login')->with('success', 'New email verified. Sign in using your updated email.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:1024'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'regex:/^(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).+$/'],
        ], ['password.regex' => 'Include at least one uppercase letter, one number, and one special character.']);
        $user = $request->user();
        if (!Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
        }
        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Choose a password different from your current one.']);
        }
        // Serialize password changes and revoke previously issued login factors
        // in the same transaction. A concurrent change cannot reuse old credentials.
        DB::transaction(function () use ($request, $user, $data) {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            if (!Hash::check($data['current_password'], $lockedUser->password)) {
                throw ValidationException::withMessages(['current_password' => 'Current password is incorrect.']);
            }
            if (Hash::check($data['password'], $lockedUser->password)) {
                throw ValidationException::withMessages(['password' => 'Choose a different password.']);
            }
            $lockedUser->forceFill([
                'password' => Hash::make($data['password']),
                'remember_token' => Str::random(60),
            ])->save();
            AccountCredentialRevoker::revoke($lockedUser, $request->session()->getId());
        });

        // Keep only the currently verified browser signed in.
        $user->refresh();
        $request->session()->regenerate();
        $request->session()->put('password_hash_'.Auth::getDefaultDriver(), $user->getAuthPassword());
        $request->session()->forget(['otp_user_id', 'otp_purpose']);
        cookie()->queue(cookie()->forget('trusted_device'));

        return response()->json(['message' => 'Password updated. Other sessions and trusted devices have been signed out.']);
    }
}
