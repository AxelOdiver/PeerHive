<?php

namespace App\Support;

use App\Models\UserSubjectQualification;
use Illuminate\Validation\ValidationException;

class TeachingEligibility
{
    public static function check(int $learner, int $tutor, string $type, ?string $requested, ?string $offered): void
    {
        if (!in_array($type, ['learning', 'skill_swap'], true) || !$requested) {
            throw ValidationException::withMessages(['request_type' => 'Please send a new request with a request type and teaching subject.']);
        }
        if (!self::approved($tutor, $requested)) {
            throw ValidationException::withMessages(['requested_subject' => 'This peer must have an approved teaching qualification for the subject you want to learn.']);
        }
        if ($type === 'skill_swap' && (!$offered || !self::approved($learner, $offered))) {
            throw ValidationException::withMessages(['offered_subject' => 'To offer a skill swap, you need an approved teaching qualification for the subject you offer. You can request learning help without one.']);
        }
    }

    private static function approved(int $userId, string $subject): bool
    {
        return UserSubjectQualification::where('user_id', $userId)->where('status', 'approved')->where('subject_name', $subject)->exists();
    }
}
