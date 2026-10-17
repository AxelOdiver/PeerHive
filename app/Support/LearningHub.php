<?php

namespace App\Support;

use App\Models\StudyParticipant;
use App\Models\StudySession;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LearningHub
{
    public static function member(User $user, $community): bool
    {
        return $community->user_id === $user->id || $community->members()->where('users.id', $user->id)->exists();
    }

    public static function checkConflict(int $user, $start, $end, ?int $except = null): void
    {
        $busy = StudyParticipant::where('user_id', $user)->whereHas('session', function ($q) use ($start, $end, $except) {
            $q->where('status', 'scheduled')->where('starts_at', '<', $end)->where('ends_at', '>', $start);
            if ($except) $q->where('id', '!=', $except);
        })->exists();
        abort_if($busy, 409, 'You already have a booked session during this time.');
    }

    public static function award(StudySession $session, int $user): void
    {
        DB::table('point_entries')->insertOrIgnore([
            'user_id' => $user, 'study_session_id' => $session->id,
            'event_key' => 'session:'.$session->id.':user:'.$user,
            'points' => config('learning.session_points'), 'description' => 'Confirmed session: '.$session->title,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public static function records(User $user)
    {
        return StudyParticipant::where('user_id', $user->id)->whereNotNull('confirmed_at')
            ->whereHas('session', fn ($q) => $q->where('status', 'completed'))
            ->with('session')->get();
    }

    public static function badges(User $user): array
    {
        $records = self::records($user);
        $teaching = $records->filter(fn ($p) => $p->teaching_minutes !== null ? $p->teaching_minutes > 0 : ($p->session->format === 'tutor' && $p->session->host_id === $user->id))->count();
        $learning = $records->filter(fn ($p) => $p->learning_minutes !== null ? $p->learning_minutes > 0 : !($p->session->format === 'tutor' && $p->session->host_id === $user->id))->count();
        $badges = [];
        if ($records->count() >= 1) $badges[] = 'First Step';
        if ($learning >= 5) $badges[] = 'Learning Together';
        if ($teaching >= 5) $badges[] = 'Knowledge Sharer';
        return $badges;
    }
}
