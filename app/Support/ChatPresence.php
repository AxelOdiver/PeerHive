<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class ChatPresence
{
    public static function forUsers($users): array
    {
        $users = $users->unique('id');
        $active = Cache::many($users->map(fn ($user) => 'presence:'.$user->id)->all());
        return $users->mapWithKeys(fn ($user) => [$user->id => [
            'is_online' => (int) ($active['presence:'.$user->id] ?? 0) > now()->subSeconds(75)->timestamp,
            'last_seen_at' => $user->last_seen_at?->toIso8601String(),
        ]])->all();
    }
}
