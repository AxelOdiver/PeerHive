<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBlock extends Model
{
    protected $fillable = ['blocker_id', 'blocked_id'];

    public static function lockPair(int $first, int $second): void
    {
        User::whereIn('id', [$first, $second])->orderBy('id')->lockForUpdate()->get();
    }

    public static function block(int $blocker, int $blocked): self
    {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($blocker, $blocked) {
            self::lockPair($blocker, $blocked);
            return self::firstOrCreate(['blocker_id' => $blocker, 'blocked_id' => $blocked]);
        });
    }

    public static function unblock(int $blocker, int $blocked): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($blocker, $blocked) {
            self::lockPair($blocker, $blocked);
            self::where('blocker_id', $blocker)->where('blocked_id', $blocked)->delete();
        });
    }
}
