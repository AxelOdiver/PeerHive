<?php

namespace App\Rules;

use App\Support\MeetingLink;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class OnlineMeetingLink implements ValidationRule
{
    public function __construct(private bool $online) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($this->online && (!is_string($value) || !MeetingLink::url($value))) {
            $fail(MeetingLink::HELP);
        }
    }
}
