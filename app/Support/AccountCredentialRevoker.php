<?php

namespace App\Support;

use App\Models\LoginOtp;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Revokes login factors and server-side sessions after a password change. */
final class AccountCredentialRevoker
{
    public static function revoke(User $user, ?string $preservedSessionId = null): void
    {
        LoginOtp::where('user_id', $user->getKey())->delete();
        TrustedDevice::where('user_id', $user->getKey())->delete();

        // The database driver permits explicit, immediate session revocation.
        // The auth.session middleware also rejects sessions whose stored password
        // hash no longer matches, for supported session drivers.
        if (config('session.driver') === 'database') {
            $sessions = DB::connection(config('session.connection'))
                ->table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey());

            if ($preservedSessionId !== null) {
                $sessions->where('id', '!=', $preservedSessionId);
            }
            $sessions->delete();
        }
    }
}
