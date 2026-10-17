<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class StudySession extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['meeting_details'];
    protected function casts(): array { return ['starts_at' => 'datetime', 'ends_at' => 'datetime']; }
    public function conversation() { return $this->hasOne(Conversation::class); }
    public function host() { return $this->belongsTo(User::class, 'host_id'); }
    public function community() { return $this->belongsTo(Community::class); }
    public function participants() { return $this->hasMany(StudyParticipant::class); }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->role === 'admin') return $query->where(fn ($q) => $q->whereNotNull('community_id')->orWhereHas('participants', fn ($p) => $p->where('user_id', $user->id)));
        return $query->where(fn ($scope) => $scope->where(fn ($private) => $private->whereNull('community_id')->whereHas('participants', fn ($p) => $p->where('user_id', $user->id)))->orWhereHas('community', fn ($q) => $q->where('visibility', 'public')->orWhere('user_id', $user->id)
            ->orWhereHas('members', fn ($m) => $m->where('users.id', $user->id))));
    }

    public function canView(User $user): bool
    {
        return static::whereKey($this->id)->visibleTo($user)->exists();
    }
}
