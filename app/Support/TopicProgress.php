<?php

namespace App\Support;

use App\Models\{CatalogEntry, SessionActivity, TopicLearningPlan, User};
use Illuminate\Database\Eloquent\Collection;

class TopicProgress
{
    public static function forUser(User $user, ?Collection $records = null): array
    {
        $topics = CatalogEntry::topics();
        $byName = $topics->keyBy('name_key');
        $plans = TopicLearningPlan::with('prerequisite')->get()->keyBy('topic_id');
        $totals = [];
        foreach (($records ?? LearningHub::records($user))->load('session.conversation') as $record) {
            $session = $record->session;
            $minutes = $record->learning_minutes ?? (($session->format === 'tutor' && $session->host_id === $user->id) ? 0 : (int) $session->starts_at->diffInMinutes($session->ends_at));
            if ($minutes <= 0) continue;
            $chat = $session->conversation;
            $name = $chat ? collect(PersonalSessionTopics::directions($chat))->firstWhere('learner', $user->id)['name'] ?? null : $session->subject;
            $topic = $name ? $byName->get(CatalogEntry::key($name)) : null;
            if (!$topic) continue; // Broad legacy subjects are not guessed into child topics.
            $totals[$topic->id]['sessions'] = ($totals[$topic->id]['sessions'] ?? 0) + 1;
            $totals[$topic->id]['minutes'] = ($totals[$topic->id]['minutes'] ?? 0) + $minutes;
        }
        $passed = SessionActivity::where('learner_id', $user->id)->where('status', 'passed')
            ->with(['session.participants', 'teacher'])->get()->filter(function ($activity) {
                $people = $activity->session->participants->keyBy('user_id');
                return $activity->session->status === 'completed' && isset($people[$activity->teacher_id], $people[$activity->learner_id])
                    && $people[$activity->teacher_id]->confirmed_at && $people[$activity->learner_id]->confirmed_at
                    && $people[$activity->teacher_id]->teaching_minutes > 0 && $people[$activity->learner_id]->learning_minutes > 0;
            });
        return $topics->map(function ($topic) use ($plans, $totals, $passed) {
            $plan = $plans->get($topic->id);
            $activities = $passed->where('topic_id', $topic->id);
            $covered = $activities->flatMap(fn ($activity) => collect($activity->covered_outcomes ?? [])->pluck('key'))->unique();
            $requirements = collect($plan?->outcomes ?? []);
            $complete = $requirements->isNotEmpty() && $requirements->pluck('key')->diff($covered)->isEmpty();
            return [
                'topic' => $topic, 'plan' => $plan, 'sessions' => $totals[$topic->id]['sessions'] ?? 0,
                'minutes' => $totals[$topic->id]['minutes'] ?? 0, 'passed' => $activities,
                'covered' => $covered, 'requirements' => $requirements,
                'status' => $complete ? 'Completed' : ($activities->isNotEmpty() ? 'Activity passed' : (isset($totals[$topic->id]) ? 'Practicing' : 'Not started')),
            ];
        })->all();
    }
}
