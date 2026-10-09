<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Post extends Model
{
    use HasFactory;

    // 1. Security: Allow these fields to be saved to the database
    protected $fillable = [
        'community_id',
        'user_id',
        'title',
        'body',
    ];

    // 2. Relationship: A post belongs to the student who wrote it
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // 3. Relationship: A post belongs to a specific community page
    public function community()
    {
        return $this->belongsTo(Community::class);
    }

    // 4. Relationship: A post can have many comments underneath it
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
    
    public function reports(): MorphMany
    {
        return $this->morphMany(Report::class, 'reportable');
    }
}