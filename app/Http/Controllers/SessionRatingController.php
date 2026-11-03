<?php
namespace App\Http\Controllers;
use App\Models\{Conversation, SessionRating};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionRatingController extends Controller
{
    public function store(Request $request, Conversation $conversation) {
        $data = $request->validate([
            'stars' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:500',
        ]);
        DB::transaction(function () use ($conversation, $data, $request) {
            $chat = Conversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            abort_unless($chat->session_context && $chat->users()->where('users.id', $request->user()->id)->exists(), 403);
            $session = $chat->studySession;
            abort_unless($session && $session->status === 'completed', 409, 'Both participants must confirm completion before rating.');
            $participation = $session->participants()->where('user_id', $request->user()->id)->first();
            abort_unless($participation && $participation->confirmed_at && $participation->learning_minutes > 0, 403, 'Only a confirmed learner can rate their teacher.');
            $c = $chat->session_context;
            $teacher = $c['learner'] === $request->user()->id ? $c['teacher'] : $c['learner'];
            abort_if(SessionRating::where('study_session_id', $session->id)->where('learner_id', $request->user()->id)->exists(), 409, 'You already rated this session.');
            SessionRating::create([
                'study_session_id' => $session->id, 'learner_id' => $request->user()->id,
                'teacher_id' => $teacher, 'stars' => $data['stars'],
                'comment' => filled($data['comment'] ?? null) ? trim($data['comment']) : null,
            ]);
        });
        return response()->json(['message' => 'Thank you. Your rating has been saved.']);
    }
}
