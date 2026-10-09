<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $fillable = ['user_id', 'reason', 'details', 'status'];

    // Connects to either Post or Comment
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    // Connects to the reporting user
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
