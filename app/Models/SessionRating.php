<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionRating extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['stars' => 'integer'];

    public function canEditBy(User $user): bool
    {
        return $this->learner_id === $user->id && $this->created_at->copy()->addDay()->isFuture()
            && !$this->reports()->where('status', 'resolved')->exists();
    }

    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable');
    }

    public function reviewVersion(): string
    {
        return hash('sha256', json_encode([$this->stars, $this->comment, $this->updated_at->toISOString()]));
    }

    public function learner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'learner_id');
    }
}
