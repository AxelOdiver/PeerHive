<?php

namespace App\Support;

use App\Models\{CatalogEntry, Community, User};

class CommunityMatching
{
    private const SUBJECT_ALIASES = [
        'coding' => 'programming', 'graphic design' => 'design', 'graphic designing' => 'design',
        'foreign language' => 'languages', 'foreign languages' => 'languages',
        'database' => 'databases', 'excel' => 'spreadsheets',
    ];

    private static function subjectKey(string $name): string
    {
        $key = CatalogEntry::key($name);
        return self::SUBJECT_ALIASES[$key] ?? $key;
    }

    public static function forUser(User $user): array
    {
        $interests = collect($user->learning_interests ?? [])->map(fn ($name) => CatalogEntry::key($name))->filter()->unique();
        $subjects = $interests->map(fn ($name) => self::subjectKey($name));
        if ($interests->isNotEmpty()) {
            $topics = CatalogEntry::approved()->where('kind', 'topic')->whereIn('name_key', $interests)
                ->whereHas('parent', fn ($q) => $q->approved()->where('kind', 'subject'))->with('parent')->get();
            $subjects = $subjects->merge($topics->map(fn ($topic) => self::subjectKey($topic->parent->name)))->unique();
            // Match normalized labels in PHP so Unicode and legacy spacing behave consistently across databases.
            $labels = Community::query()->distinct()->pluck('subject')
                ->filter(fn ($label) => $subjects->contains(self::subjectKey($label)))->values();
            $match = Community::with(['user', 'banner'])->whereIn('subject', $labels)->latest()->orderByDesc('id')->first();
            if ($match) return ['community' => $match, 'matches_interests' => true];
        }

        $fallback = Community::with(['user', 'banner'])->where(fn ($q) => $q->where('user_id', $user->id)
            ->orWhereHas('members', fn ($m) => $m->where('users.id', $user->id)))
            ->latest()->orderByDesc('id')->first();
        return ['community' => $fallback, 'matches_interests' => false];
    }
}
