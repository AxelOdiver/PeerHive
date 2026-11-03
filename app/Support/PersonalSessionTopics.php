<?php

namespace App\Support;

use App\Models\{CatalogEntry, Conversation, StudySession};

class PersonalSessionTopics
{
    public static function directions(Conversation $chat): array
    {
        $c = $chat->session_context;
        if (!$c || !$chat->study_session_id) return [];
        $directions = [['teacher' => (int) $c['teacher'], 'learner' => (int) $c['learner'], 'name' => $c['subject']]];
        if ($c['type'] === 'skill_swap' && !empty($c['offered_subject'])) {
            $directions[] = ['teacher' => (int) $c['learner'], 'learner' => (int) $c['teacher'], 'name' => $c['offered_subject']];
        }
        return $directions;
    }

    public static function topic(string $name): ?CatalogEntry
    {
        return CatalogEntry::approved()->where('kind', 'topic')->where('name_key', CatalogEntry::key($name))->first();
    }

    public static function confirmed(StudySession $session, int $teacher, int $learner): bool
    {
        if ($session->status !== 'completed') return false;
        $people = $session->participants()->whereIn('user_id', [$teacher, $learner])->get()->keyBy('user_id');
        return isset($people[$teacher], $people[$learner]) && $people[$teacher]->confirmed_at && $people[$learner]->confirmed_at
            && $people[$teacher]->teaching_minutes > 0 && $people[$learner]->learning_minutes > 0;
    }
}
