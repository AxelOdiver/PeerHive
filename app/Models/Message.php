<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Message extends Model
{
    protected $fillable = ['conversation_id', 'sender_id', 'reply_to_id', 'body', 'attachment_path', 'attachment_name', 'attachment_type', 'edited_at', 'is_unsent'];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function repliedTo()
    {
        return $this->belongsTo(Message::class, 'reply_to_id');
    }

    public function getAttachmentUrlAttribute()
    {
        return $this->attachment_path ? Storage::url($this->attachment_path) : null;
    }
}