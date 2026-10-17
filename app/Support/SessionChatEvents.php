<?php
namespace App\Support;
use App\Models\Conversation;

class SessionChatEvents
{
    public static function record(Conversation $chat, int $actor, string $action): void
    {
        $c = $chat->session_context;
        $message = $chat->messages()->make(['sender_id' => $actor, 'body' => match($action) {
            'propose' => 'Proposed a session', 'accept' => 'Accepted the session schedule',
            'changes' => 'Requested schedule changes', 'finish' => $c['state'] === 'completed' ? 'Session completed — both participants confirmed' : 'Confirmed completion — waiting for partner',
            'problem' => 'Reported an attendance problem', 'cancel' => 'Cancelled the session', default => 'Learn/Swap session details',
        }]);
        $message->session_event = ['action' => $action, 'context' => $c];
        $message->save();
    }
}
