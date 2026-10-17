<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

class BellNotifications
{
    public static function record(int $user, string $key, string $kind, string $title, string $body, string $url): void
    {
        DB::table('bell_notifications')->insertOrIgnore([
            'user_id' => $user, 'event_key' => $key, 'kind' => $kind,
            'title' => $title, 'body' => $body, 'url' => $url,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public static function query(int $user) { return DB::table('bell_notifications')->where('user_id', $user)->whereNull('cleared_at'); }
}
