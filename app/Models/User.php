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
        'username',
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
        'course', 'expertise', 'school', 'school_location',
        'show_course', 'show_expertise', 'show_school', 'show_school_location',
        'policies_accepted_at', 'terms_version', 'privacy_version',
        'learning_goals', 'learning_timezone', 'learning_interests',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
            'expertise' => 'array',
            'show_course' => 'boolean',
            'show_expertise' => 'boolean',
            'show_school' => 'boolean',
            'show_school_location' => 'boolean',
            'policies_accepted_at' => 'datetime',
            'learning_goals' => 'array', 'learning_interests' => 'array',
        ];
    }

    public function teachingRatings() { return $this->hasMany(SessionRating::class, 'teacher_id')->whereDoesntHave('reports', fn ($query) => $query->where('status', 'resolved')); }

    public function getProfileFrameClassAttribute(): string
    {
        return match ($this->profile_frame) {
            'forest_frame' => 'profile-frame-forest', 'sunrise_frame' => 'profile-frame-sunrise', default => '',
        };
    }

    public function getLearningTimezoneAttribute($value): string
    {
        return $value ?: config('learning.timezone', 'Asia/Taipei');
    }

    public function setUsernameAttribute($value): void
    {
        $this->attributes['username'] = filled($value) ? strtolower(trim($value)) : null;
    }

    public function approvedTeachingQualifications(): HasMany
    {
        return $this->hasMany(UserSubjectQualification::class)
            ->where('status', 'approved')
            ->select('id', 'user_id', 'subject_name');
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
        if (!$this->profile_picture || !Storage::disk('public')->exists($this->profile_picture)) {
            return null;
        }

        if (dirname($this->profile_picture) === 'profile_pictures') {
            return route('profile-picture.show', ['filename' => basename($this->profile_picture)]);
        }

        return asset('storage/'.$this->profile_picture);
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

    // Fetch all invites for this user that are currently pending
    public function pendingInvites()
    {
        return $this->hasMany(CommunityInvite::class)->where('status', 'pending');
    }

    public function conversations()
    {
        return $this->belongsToMany(Conversation::class)->withPivot('last_read_at')->withTimestamps();
    }

    public function blocksMade()
    {
        return $this->hasMany(UserBlock::class, 'blocker_id');
    }

    public function blocksReceived()
    {
        return $this->hasMany(UserBlock::class, 'blocked_id');
    }

    public function reports()
    {
        return $this->morphMany(Report::class, 'reportable');
    }
}
