<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'password',
        'profile_picture',
        'description',
    ];

    protected $appends = [
        'profile_picture_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function favorites()
    {
        return $this->belongsToMany(User::class, 'favorites', 'user_id', 'item_id')->withTimestamps();
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function getProfilePictureUrlAttribute(): ?string
    {
        if (!$this->profile_picture) {
            return null;
        }

        return Storage::url($this->profile_picture);
    }

    public function communities()
    {
        return $this->hasMany(Community::class);
    }

    public function joinedCommunities()
    {
        return $this->belongsToMany(Community::class)->withTimestamps();
    }

    // Users who liked this user
    public function likedBy()
    {
        return $this->belongsToMany(User::class, 'likes', 'liked_user_id', 'user_id')->withTimestamps();
    }

    // Users this user has liked
    public function likes()
    {
        return $this->belongsToMany(User::class, 'likes', 'user_id', 'liked_user_id')->withTimestamps();
    }

    // Swaps this user has initiated
    public function swaps()
    {
        return $this->hasMany(Swap::class, 'requester_id');
    }

    // Comments this user has made
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    // Posts this user has made
    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
