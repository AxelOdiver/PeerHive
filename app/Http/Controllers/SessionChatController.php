<?php
namespace App\Http\Controllers;

use App\Models\{Conversation, StudySession, User};
use App\Support\{LearningHub, TeachingEligibility, BellNotifications};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SessionChatController extends Controller
{
    public function show(Conversation $conversation) {
        abort_unless($conversation->users()->where('users.id', auth()->id())->exists(), 403);
        if (!$conversation->session_context) return response()->json(['html' => '', 'finish' => false]);
        if (!$conversation->messages()->whereNotNull('session_event')->exists()) {
            DB::transaction(function () use ($conversation) {
                $chat = Conversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
                if (!$chat->messages()->whereNotNull('session_event')->exists()) \App\Support\SessionChatEvents::record($chat, $chat->session_context['proposer'] ?? $chat->created_by, 'summary');
            });
        }
        $session = $conversation->studySession;
        $rating = null;
        if ($session && $session->status === 'completed') {
            $participant = $session->participants()->where('user_id', auth()->id())->first();
            if ($participant && $participant->confirmed_at && $participant->learning_minutes > 0 && !\App\Models\SessionRating::where('study_session_id', $session->id)->where('learner_id', auth()->id())->exists()) {
                $context = $conversation->session_context;
                $teacherId = $context['learner'] === auth()->id() ? $context['teacher'] : $context['learner'];
                $teacher = $conversation->users->firstWhere('id', $teacherId);
                $rating = ['conversation_id' => $conversation->id, 'learner_id' => auth()->id(), 'teacher_name' => trim($teacher->first_name.' '.$teacher->last_name)];
            }
        }
        return response()->json(['html' => view('messages.session-panel', compact('conversation', 'session'))->render(),
            'rating' => $rating,
            'session_finished' => $conversation->isSessionFinished(),
            'before_start' => $session && $session->starts_at->isFuture(),
            'finish' => $session && $session->status === 'scheduled' && !in_array(auth()->id(), $conversation->session_context['confirmed'] ?? [])]);
    }

    public function update(Request $request, Conversation $conversation) {
        $request->validate(['action' => 'required|in:propose,accept,changes,finish,cancel,problem', 'revision' => 'required|integer|min:0']);
        DB::transaction(function () use ($request, $conversation) {
            $chat = Conversation::whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            abort_unless($chat->session_context && $chat->users()->where('users.id', auth()->id())->exists(), 403);
            $c = $chat->session_context;
            abort_unless((int)$request->revision === $c['revision'], 409, 'This session changed. Refresh its details and try again.');
            $action = $request->action;
            $session = $chat->studySession;
            if ($action === 'propose') {
                abort_unless(!$session && in_array($c['state'], ['scheduling', 'proposed']), 409);
                $data = $request->validate(['starts_at' => 'required|date_format:Y-m-d\TH:i', 'timezone' => 'required|timezone', 'duration' => 'required|integer|between:15,240', 'teaching_minutes' => 'nullable|integer|min:1', 'meeting_method' => 'required|in:online,in_person', 'meeting_details' => 'required|string|max:1000']);
                $start = Carbon::createFromFormat('Y-m-d\TH:i', $data['starts_at'], $data['timezone'])->seconds(0)->utc();
                abort_unless($start->isFuture(), 422, 'Choose a future time.');
                if ($c['type'] === 'skill_swap') abort_unless(($data['teaching_minutes'] ?? 0) > 0 && $data['teaching_minutes'] < $data['duration'], 422, 'Divide the duration between both teaching portions.');
                $data['duration'] = (int) $data['duration'];
                if (isset($data['teaching_minutes'])) $data['teaching_minutes'] = (int) $data['teaching_minutes'];
                $data['starts_at'] = $start->toIso8601String();
                $c['proposal'] = $data; $c['proposer'] = auth()->id(); $c['state'] = 'proposed';
            } elseif ($action === 'changes') {
                abort_unless(!$session && $c['state'] === 'proposed', 409);
                $c['state'] = 'scheduling';
            } elseif ($action === 'accept') {
                abort_unless(!$session && $c['state'] === 'proposed' && $c['proposer'] !== auth()->id(), 409);
                $p = $c['proposal']; $start = Carbon::parse($p['starts_at']); $end = $start->copy()->addMinutes((int) $p['duration']);
                abort_unless($start->isFuture(), 422, 'This proposal has expired. Suggest a new time.');
                User::whereIn('id', [$c['learner'], $c['teacher']])->orderBy('id')->lockForUpdate()->get();
                TeachingEligibility::check($c['learner'], $c['teacher'], $c['type'], $c['subject'], $c['offered_subject']);
                foreach ([$c['learner'], $c['teacher']] as $id) LearningHub::checkConflict($id, $start, $end);
                $session = StudySession::create(['community_id' => null, 'host_id' => $c['teacher'], 'title' => $chat->name, 'subject' => $c['subject'], 'description' => $c['message'], 'format' => $c['type'] === 'skill_swap' ? 'swap' : 'tutor', 'starts_at' => $start, 'ends_at' => $end, 'timezone' => $p['timezone'], 'capacity' => 2, 'meeting_method' => $p['meeting_method'], 'meeting_details' => $p['meeting_details']]);
                $first = $c['type'] === 'skill_swap' ? $p['teaching_minutes'] : $p['duration'];
                $second = $p['duration'] - $first;
                $session->participants()->create(['user_id' => $c['learner'], 'learning_minutes' => $first, 'teaching_minutes' => $second]);
                $session->participants()->create(['user_id' => $c['teacher'], 'learning_minutes' => $second, 'teaching_minutes' => $first]);
                $chat->study_session_id = $session->id; $c['state'] = 'scheduled';
            } elseif ($action === 'finish') {
                abort_unless($session && $session->status === 'scheduled' && $c['state'] !== 'problem', 409, 'Accept a session schedule first, with no unresolved attendance problem.');
                if (empty($c['confirmed'])) {
                    if ($session->starts_at->isFuture()) {
                        $actual = $request->validate(['actual_minutes' => 'required|integer|min:1|max:'.$c['proposal']['duration']]);
                        $actualEnd = now();
                        $actualStart = $actualEnd->copy()->subMinutes((int) $actual['actual_minutes']);
                        foreach ([$c['learner'], $c['teacher']] as $id) LearningHub::checkConflict($id, $actualStart, $actualEnd, $session->id);
                        $session->update(['starts_at' => $actualStart, 'ends_at' => $actualEnd]);
                    }
                    $end = now()->min($session->ends_at);
                    $minutes = max(0, (int) $session->starts_at->diffInMinutes($end));
                    abort_if($minutes < 1, 422, 'At least one minute of the session must pass before finishing.');
                    $session->update(['ends_at' => $end]);
                    $first = $c['type'] === 'skill_swap' ? min($minutes, (int) $c['proposal']['teaching_minutes']) : $minutes;
                    $second = $minutes - $first;
                    $session->participants()->where('user_id', $c['learner'])->update(['learning_minutes' => $first, 'teaching_minutes' => $second]);
                    $session->participants()->where('user_id', $c['teacher'])->update(['learning_minutes' => $second, 'teaching_minutes' => $first]);
                    $c['finished_at'] = $end->toIso8601String();
                    $c['finished_minutes'] = $minutes;
                }
                $c['confirmed'] = array_values(array_unique(array_merge($c['confirmed'] ?? [], [auth()->id()])));
                if (count($c['confirmed']) === 2) {
                    $session->update(['status' => 'completed']); $c['state'] = 'completed';
                    $session->participants()->update(['attended_at' => now(), 'confirmed_at' => now()]);
                    foreach ($c['confirmed'] as $id) LearningHub::award($session, $id);
                }
            } elseif ($action === 'problem') {
                abort_unless($session && $session->status === 'scheduled', 409);
                $data = $request->validate(['reason' => 'required|string|max:1000']);
                $c['state'] = 'problem'; $c['problem'] = $data['reason'];
                $session->update(['status' => 'disputed']);
            } else {
                abort_if(in_array($c['state'], ['completed', 'cancelled']), 409);
                if ($session) $session->update(['status' => 'cancelled']);
                $c['state'] = 'cancelled';
            }
            $c['revision']++; $chat->session_context = $c; $chat->save();
            \App\Support\SessionChatEvents::record($chat, auth()->id(), $action);
            foreach ($chat->users as $user) if ($user->id !== auth()->id()) BellNotifications::record($user->id, 'chat:'.$chat->id.':'.$c['revision'], 'session', $chat->name, match($action) { 'propose' => 'A session schedule was proposed.', 'accept' => 'Your session schedule was accepted.', 'finish' => $c['state'] === 'completed' ? 'Both participants confirmed. Progress updated.' : 'Your partner confirmed completion. Please confirm your attendance.', 'problem' => 'Your partner reported an attendance problem. No progress will be awarded.', 'changes' => 'Your partner requested schedule changes.', default => 'The session was cancelled.' }, route('messages', ['conversation' => $chat->id]));
        });
        return $this->show($conversation->fresh());
    }
}
