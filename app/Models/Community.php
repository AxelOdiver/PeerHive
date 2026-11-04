<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Community extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'subject',
        'description',
        'visibility',
        'member_limit',
        'user_id',
        'tags',
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function members()
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function joinRequests() { return $this->hasMany(CommunityJoinRequest::class); }
    public function banners() { return $this->hasMany(CommunityBanner::class); }
    public function banner() { return $this->hasOne(CommunityBanner::class)->whereNull('removed_at')->latest('id'); }

    public function memberCount(): int
    {
        return $this->members()->count() + ($this->members()->where('users.id', $this->user_id)->exists() ? 0 : 1);
    }
}
