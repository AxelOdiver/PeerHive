<?php
namespace App\Support;
use App\Models\{Conversation, Swap};

class SessionChats
{
    // Called while the accepted request is locked.
    public static function create(Swap $swap): Conversation
    {
        $chat = Conversation::where('swap_id', $swap->id)->first();
        if ($chat) return $chat;
        $chat = Conversation::create([
            'is_group' => false, 'created_by' => $swap->requested_user_id, 'swap_id' => $swap->id,
            'name' => ($swap->request_type === 'learning' ? 'Learn · ' : 'Swap · ').$swap->requested_subject.($swap->offered_subject ? ' ↔ '.$swap->offered_subject : ''),
            'session_context' => ['type' => $swap->request_type, 'learner' => $swap->requester_id, 'teacher' => $swap->requested_user_id,
                'subject' => $swap->requested_subject, 'offered_subject' => $swap->offered_subject, 'message' => $swap->message, 'state' => 'scheduling', 'revision' => 0],
        ]);
        $chat->users()->attach([$swap->requester_id, $swap->requested_user_id]);
        SessionChatEvents::record($chat, $swap->requested_user_id, 'summary');
        return $chat;
    }
}
