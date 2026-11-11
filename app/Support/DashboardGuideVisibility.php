<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class DashboardGuideVisibility
{
    /**
     * Show the dashboard walkthrough to newly registered, verified-and-onboarded
     * accounts for their first 7 days. Older accounts still have Help access.
     */
    public static function forUser(User $user, ?CarbonInterface $clock = null): bool
    {
        if ((bool) $user->onboarding_required || $user->onboarding_completed_at === null) {
            return false;
        }

        $createdAt = $user->created_at;
        if ($createdAt === null) {
            return false;
        }

        $now = $clock ?? CarbonImmutable::now();
        return $createdAt->lessThanOrEqualTo($now)
            && $createdAt->greaterThanOrEqualTo($now->copy()->subDays(7));
    }
}
