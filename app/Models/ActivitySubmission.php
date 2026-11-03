<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivitySubmission extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['reviewed_at' => 'datetime']; }
    public function activity() { return $this->belongsTo(SessionActivity::class, 'session_activity_id'); }
}
