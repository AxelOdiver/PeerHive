<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['is_group', 'name', 'created_by', 'swap_id', 'session_context', 'study_session_id'];

    protected $casts = ['is_group' => 'boolean', 'session_context' => 'array'];
    public function studySession() { return $this->belongsTo(StudySession::class); }

    public function isSessionFinished(): bool
    {
        return ($this->session_context['state'] ?? null) === 'completed';
    }

    public function blockedBy(int $userId): bool
    {
        return !$this->is_group && UserBlock::where('blocker_id', $userId)
            ->whereIn('blocked_id', $this->users()->where('users.id', '!=', $userId)->pluck('users.id'))->exists();
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('last_read_at', 'is_muted', 'muted_until', 'last_read_message_id', 'cleared_through_message_id', 'is_hidden')->withTimestamps();
    }

    public function isMutedFor($userId): bool
    {
        $pivot = $this->users->firstWhere('id', $userId)?->pivot;
        return $pivot && $pivot->is_muted && (!$pivot->muted_until || now()->lt($pivot->muted_until));
    }

    public function clearedThrough($userId): int
    {
        return (int) ($this->users->firstWhere('id', $userId)?->pivot->cleared_through_message_id ?? 0);
    }

    public function visibleTo($userId): bool
    {
        $pivot = $this->users->firstWhere('id', $userId)?->pivot;
        return $pivot && (!$pivot->is_hidden || $this->messages()->visibleTo($userId)->where('id', '>', $pivot->cleared_through_message_id)->exists());
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}
