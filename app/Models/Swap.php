<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Swap extends Model
{
    protected $fillable = [
        'requester_id',
        'requested_user_id',
        'status',
        'message',
        'responded_at',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function requestedUser()
    {
        return $this->belongsTo(User::class, 'requested_user_id');
    }
}
