<?php

namespace App\Http\Controllers;

use App\Models\{Conversation, SessionActivity, TopicLearningPlan};
use App\Support\{PersonalSessionTopics, BellNotifications};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionActivityController extends Controller
{
    private function authorizeChat(Conversation $chat): void
    {
        abort_unless($chat->session_context && $chat->users()->where('users.id', auth()->id())->exists(), 403);
    }

    public function index(Request $request, Conversation $conversation)
    {
        $this->authorizeChat($conversation);
        $activities = SessionActivity::where('conversation_id', $conversation->id)->with(['teacher', 'learner', 'submissions'])->get();
        $direction = collect(PersonalSessionTopics::directions($conversation))->firstWhere('teacher', auth()->id());
        $session = $conversation->studySession;
        $canAssign = $direction && $session && in_array($session->status, ['scheduled', 'completed'])
            && !$activities->contains('teacher_id', auth()->id())
            && ($session->status !== 'completed' || PersonalSessionTopics::confirmed($session, $direction['teacher'], $direction['learner']));
        $topic = $direction ? PersonalSessionTopics::topic($direction['name']) : null;
        $plan = $topic ? TopicLearningPlan::where('topic_id', $topic->id)->first() : null;
        $ready = $activities->mapWithKeys(fn ($activity) => [$activity->id => PersonalSessionTopics::confirmed($session, $activity->teacher_id, $activity->learner_id)]);
        $data = compact('conversation', 'activities', 'direction', 'canAssign', 'plan', 'ready');
        if ($request->expectsJson()) return response()->json(['html' => view('activities.panel', $data)->render()]);
        return view('activities.index', $data);
    }

    public function store(Request $request, Conversation $conversation)
    {
        $this->authorizeChat($conversation);
        $data = $request->validate([
            'title' => 'required|string|max:150', 'instructions' => 'required|string|max:4000',
            'passing_criteria' => 'required|string|max:2000', 'reference_url' => ['nullable', 'url:http,https', 'max:1000'],
            'outcomes' => 'nullable|array|max:10', 'outcomes.*' => 'required|string|size:64|distinct',
        ]);
        DB::transaction(function () use ($conversation, $request, $data) {
            $chat = Conversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $direction = collect(PersonalSessionTopics::directions($chat))->firstWhere('teacher', auth()->id());
            abort_unless($direction, 403, 'Only the teacher for this topic can assign an activity.');
            $session = $chat->studySession;
            abort_unless($session && in_array($session->status, ['scheduled', 'completed']), 409, 'Accept a session schedule first.');
            if ($session->status === 'completed') abort_unless(PersonalSessionTopics::confirmed($session, $direction['teacher'], $direction['learner']), 409, 'No confirmed learning time was recorded for this topic.');
            abort_if(SessionActivity::where('study_session_id', $session->id)->where('teacher_id', auth()->id())->exists(), 409, 'This teaching direction already has an activity.');
            $topic = PersonalSessionTopics::topic($direction['name']);
            $plan = $topic ? TopicLearningPlan::where('topic_id', $topic->id)->lockForUpdate()->first() : null;
            $outcomes = collect($plan?->outcomes ?? []);
            abort_if(collect($data['outcomes'] ?? [])->diff($outcomes->pluck('key'))->isNotEmpty(), 422, 'Topic requirements changed. Reopen Activities and select the current outcomes.');
            $activity = SessionActivity::create([
                'conversation_id' => $chat->id, 'study_session_id' => $session->id, 'teacher_id' => $direction['teacher'],
                'learner_id' => $direction['learner'], 'topic_id' => $topic?->id, 'topic_name' => $direction['name'],
                'title' => $data['title'], 'instructions' => $data['instructions'], 'passing_criteria' => $data['passing_criteria'],
                'reference_url' => $data['reference_url'] ?? null,
                'covered_outcomes' => $outcomes->whereIn('key', $data['outcomes'] ?? [])->values()->all(),
            ]);
            $this->notify($activity, $activity->learner_id, 'assigned', 'Your teacher assigned an activity. Submit it after both participants confirm the session.');
        });
        return $this->result($request, $conversation, 'Activity assigned. Instructions and passing criteria are now fixed.');
    }

    public function submit(Request $request, Conversation $conversation, SessionActivity $activity)
    {
        $this->authorizeChat($conversation);
        abort_unless($activity->conversation_id === $conversation->id, 404);
        abort_unless($activity->learner_id === auth()->id(), 403);
        $data = $request->validate(['answer' => 'required|string|max:10000', 'project_url' => ['nullable', 'url:http,https', 'max:1000'], 'revision' => 'required|integer|min:0']);
        DB::transaction(function () use ($activity, $data) {
            $locked = SessionActivity::whereKey($activity->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->revision === (int) $data['revision'] && in_array($locked->status, ['assigned', 'revision']), 409, 'This activity changed. Reopen it before submitting.');
            abort_unless(PersonalSessionTopics::confirmed($locked->session, $locked->teacher_id, $locked->learner_id), 409, 'Both participants must confirm the session and learning time before submission.');
            $locked->submissions()->create(['attempt' => $locked->submissions()->count() + 1, 'answer' => $data['answer'], 'project_url' => $data['project_url'] ?? null]);
            $locked->update(['status' => 'submitted', 'revision' => $locked->revision + 1]);
            $this->notify($locked, $locked->teacher_id, 'submitted', 'Your learner submitted an activity for review.');
        });
        return $this->result($request, $conversation, 'Submitted for teacher review.');
    }

    public function review(Request $request, Conversation $conversation, SessionActivity $activity)
    {
        $this->authorizeChat($conversation);
        abort_unless($activity->conversation_id === $conversation->id, 404);
        abort_unless($activity->teacher_id === auth()->id(), 403);
        $data = $request->validate(['decision' => 'required|in:revision,passed', 'feedback' => 'required|string|max:3000', 'revision' => 'required|integer|min:0']);
        DB::transaction(function () use ($activity, $data) {
            $locked = SessionActivity::whereKey($activity->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->revision === (int) $data['revision'] && $locked->status === 'submitted', 409, 'This submission has already been handled. Reopen Activities.');
            abort_unless(PersonalSessionTopics::confirmed($locked->session, $locked->teacher_id, $locked->learner_id), 409);
            $submission = $locked->submissions()->firstOrFail();
            abort_if($submission->reviewed_at, 409);
            $submission->update(['decision' => $data['decision'], 'feedback' => $data['feedback'], 'reviewed_at' => now()]);
            $locked->update(['status' => $data['decision'], 'revision' => $locked->revision + 1]);
            $this->notify($locked, $locked->learner_id, $data['decision'], $data['decision'] === 'passed' ? 'Your activity passed. View your topic progress.' : 'Your teacher requested a revision. Read the feedback and resubmit.');
        });
        return $this->result($request, $conversation, $data['decision'] === 'passed' ? 'Activity passed — reviewed by your peer.' : 'Revision requested.');
    }

    private function notify(SessionActivity $activity, int $user, string $event, string $body): void
    {
        $label = match ($event) {
            'assigned' => 'Activity assigned',
            'submitted' => 'Activity submitted',
            'revision' => 'Activity revision requested',
            'passed' => 'Activity passed',
        };
        BellNotifications::record($user, 'activity:'.$activity->id.':'.$activity->revision.':'.$event, 'activity', $label.': '.$activity->title, $body,
            route('session-activities.index', $activity->conversation_id));
    }

    private function result(Request $request, Conversation $chat, string $message)
    {
        return $request->expectsJson() ? response()->json(['message' => $message])
            : redirect()->route('session-activities.index', $chat)->with('success', $message);
    }
}
