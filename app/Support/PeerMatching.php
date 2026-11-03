<?php
namespace App\Support;
use App\Models\User;
use Illuminate\Support\Collection;

class PeerMatching
{
    public static function normalize(string $subject): string {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($subject)));
    }
    public static function recommend(User $learner, ?array $topics = null): Collection {
        $interests = collect($topics ?? $learner->learning_interests ?? [])->map(fn ($s) => self::normalize($s))->unique();
        if ($interests->isEmpty()) return collect();
        $availability = $learner->schedules;
        return User::where('id', '!=', $learner->id)->whereHas('approvedTeachingQualifications')
            ->with(['approvedTeachingQualifications', 'schedules'])->withAvg('teachingRatings', 'stars')->withCount('teachingRatings')->get()
            ->map(function ($peer) use ($interests, $availability) {
                $matches = $peer->approvedTeachingQualifications->pluck('subject_name')->filter(fn ($s) => $interests->contains(self::normalize($s)))->unique()->values();
                $overlap = $availability->contains(fn ($a) => $peer->schedules->contains(fn ($b) => $a->day_index == $b->day_index && $a->start_time < $b->end_time && $b->start_time < $a->end_time));
                $peer->matched_subjects = $matches;
                $peer->shared_availability = $overlap;
                // Subject fit is primary; availability and a smoothed rating break ties.
                $peer->match_score = $matches->count() * 100 + ($overlap ? 10 : 0)
                    + (($peer->teaching_ratings_avg_stars ?? 0) * $peer->teaching_ratings_count + 15) / ($peer->teaching_ratings_count + 5);
                return $peer;
            })->filter(fn ($peer) => $peer->matched_subjects->isNotEmpty())
            ->sort(fn ($a, $b) => ($b->match_score <=> $a->match_score) ?: ($a->id <=> $b->id))->take(6)->values();
    }
}
