<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $fillable = ['is_group', 'name', 'created_by'];

    protected $casts = ['is_group' => 'boolean'];

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot('last_read_at', 'is_muted', 'muted_until')->withTimestamps();
    }

    public function isMutedFor($userId): bool
    {
        $pivot = $this->users->firstWhere('id', $userId)?->pivot;
        return $pivot && $pivot->is_muted && (!$pivot->muted_until || now()->lt($pivot->muted_until));
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
