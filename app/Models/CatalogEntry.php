<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogEntry extends Model
{
    protected $attributes = ['status' => 'pending'];
    protected $fillable = ['kind', 'name', 'name_key', 'parent_id', 'submitted_by'];
    public static function key(string $name): string { return mb_strtolower(preg_replace('/\s+/u', ' ', trim($name))); }
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function submitter() { return $this->belongsTo(User::class, 'submitted_by'); }
    public function scopeApproved($query) { return $query->where('status', 'approved'); }
    public static function topics() { return self::approved()->where('kind', 'topic')->with('parent')->orderBy('name')->get(); }
    public static function names(): array { return self::topics()->pluck('name')->all(); }
    public static function subjects()
    {
        return self::approved()->where('kind', 'subject')
            ->where('name_key', '!=', self::key('Existing teaching subjects'))->orderBy('name')->get();
    }
}
