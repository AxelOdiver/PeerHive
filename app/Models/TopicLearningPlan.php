<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopicLearningPlan extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['outcomes' => 'array', 'revision' => 'integer']; }
    public function topic() { return $this->belongsTo(CatalogEntry::class, 'topic_id'); }
    public function prerequisite() { return $this->belongsTo(CatalogEntry::class, 'prerequisite_id'); }

    public static function outcomeKey(string $text): string
    {
        return hash('sha256', CatalogEntry::key($text));
    }
}
