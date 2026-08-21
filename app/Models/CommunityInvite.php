<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityInvite extends Model
{
    protected $fillable = [
        'community_id',
        'user_id',
        'status'
    ];

    // Helper relationships to easily fetch the data later
    public function community()
    {
        return $this->belongsTo(Community::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
