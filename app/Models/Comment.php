<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Comment extends Model
{
    use HasFactory;

    // 1. Security: Allow these fields to be saved
    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'body',
    ];

    // 2. Relationship: A comment belongs to the student who wrote it
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // 3. Relationship: A comment belongs to the main post
    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    // 4. The Reddit Magic: A comment can have its own nested replies!
    // We tell Laravel to look for other comments that have THIS comment's ID as their parent_id
    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }
}