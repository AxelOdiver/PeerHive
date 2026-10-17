<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StudyParticipant extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['attended_at' => 'datetime', 'confirmed_at' => 'datetime']; }
    public function session() { return $this->belongsTo(StudySession::class, 'study_session_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
