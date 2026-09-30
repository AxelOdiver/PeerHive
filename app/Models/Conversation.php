<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['is_group', 'name', 'created_by'];

    protected $casts = ['is_group' => 'boolean'];

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
        return $pivot && (!$pivot->is_hidden || ($this->latestMessage?->id ?? 0) > $pivot->cleared_through_message_id);
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
