<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SessionActivity extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['covered_outcomes' => 'array', 'revision' => 'integer']; }
    public function conversation() { return $this->belongsTo(Conversation::class); }
    public function session() { return $this->belongsTo(StudySession::class, 'study_session_id'); }
    public function teacher() { return $this->belongsTo(User::class, 'teacher_id'); }
    public function learner() { return $this->belongsTo(User::class, 'learner_id'); }
    public function topic() { return $this->belongsTo(CatalogEntry::class, 'topic_id'); }
    public function submissions() { return $this->hasMany(ActivitySubmission::class)->orderByDesc('attempt'); }
}
