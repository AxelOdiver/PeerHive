<?php

namespace App\Http\Controllers;

use App\Models\{Community, StudySession, StudyParticipant, User, UserSubjectQualification};
use App\Support\LearningHub;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StudySessionController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('filter', 'all');
        $sessions = StudySession::visibleTo($request->user())->with(['community', 'host.approvedTeachingQualifications'])
            ->withCount('participants');
        if ($filter === 'history') {
            $sessions->whereIn('status', ['completed', 'cancelled'])
                ->where(fn ($q) => $q->where('host_id', auth()->id())
                    ->orWhereHas('participants', fn ($participants) => $participants->where('user_id', auth()->id())));
        } elseif ($filter === 'mine') {
            $sessions->where(fn ($q) => $q->where('host_id', auth()->id())
                ->orWhereHas('participants', fn ($participants) => $participants->where('user_id', auth()->id())))
                ->whereNotIn('status', ['completed', 'cancelled']);
        } else {
            $sessions->where('status', 'scheduled')->where('starts_at', '>', now());
        }
        if (in_array($filter, ['peer', 'tutor'])) $sessions->where('format', $filter);
        if ($request->filled('community')) $sessions->where('community_id', $request->integer('community'));
        $sessions = $sessions->orderBy('starts_at')->paginate(12)->withQueryString();
        $joined = StudyParticipant::where('user_id', auth()->id())->pluck('study_session_id')->all();
        $navigationCommunity = Community::find($request->integer('community'));
        return view('learning.sessions', compact('sessions', 'joined', 'filter', 'navigationCommunity'));
    }

    public function create(Request $request)
    {
        $communities = Community::where('user_id', auth()->id())->orWhereHas('members', fn ($q) => $q->where('users.id', auth()->id()))->orderBy('name')->get();
        $navigationCommunity = $communities->firstWhere('id', $request->integer('community'));
        return view('learning.create', compact('communities', 'navigationCommunity'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'community_id' => ['required', 'integer', 'exists:communities,id'], 'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'], 'subject' => ['required', 'string', 'max:255'],
            'format' => ['required', 'in:peer,tutor'], 'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'timezone' => ['required', 'timezone'], 'duration' => ['required', 'integer', 'between:15,240'],
            'capacity' => ['required', 'integer', 'between:2,50'], 'meeting_method' => ['required', 'in:online,in_person'],
            'meeting_details' => ['required', 'string', 'max:1000'],
        ]);
        $community = Community::findOrFail($data['community_id']);
        abort_unless(LearningHub::member($request->user(), $community), 403, 'Join this community before hosting a session.');
        $start = Carbon::createFromFormat('Y-m-d\TH:i', $data['starts_at'], $data['timezone'])->seconds(0)->utc();
        abort_unless($start->isFuture(), 422, 'Choose a future start time in the selected timezone.');
        $end = $start->copy()->addMinutes((int) $data['duration']);
        $session = DB::transaction(function () use ($request, $data, $start, $end) {
            User::whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            $this->checkTutor(auth()->id(), $data['format'], $data['subject']);
            LearningHub::checkConflict(auth()->id(), $start, $end);
            $session = StudySession::create(collect($data)->except(['duration', 'starts_at'])->all() + [
                'host_id' => auth()->id(), 'starts_at' => $start, 'ends_at' => $end,
            ]);
            $session->participants()->create(['user_id' => auth()->id()]);
            return $session;
        });
        return redirect()->route('study-sessions.show', ['session' => $session,
            'community' => $request->integer('community') === (int) $session->community_id ? $session->community_id : null,
        ])->with('success', 'Study session created.');
    }

    public function show(Request $request, StudySession $session)
    {
        abort_unless($session->canView($request->user()), 403);
        if ($session->conversation) return redirect()->route('messages', ['conversation' => $session->conversation->id]);
        $session->load(['community', 'host.approvedTeachingQualifications', 'participants.user']);
        $participation = $session->participants->firstWhere('user_id', auth()->id());
        if ($participation && $session->status === 'scheduled' && $session->starts_at->lte(now()) && !$participation->session_start_read_at) {
            $participation->update(['session_start_read_at' => now()]);
        }
        $navigationCommunity = $request->integer('community') === (int) $session->community_id ? $session->community : null;
        return view('learning.session', compact('session', 'participation', 'navigationCommunity'));
    }

    public function join(Request $request, StudySession $session)
    {
        abort_if($session->conversation()->exists(), 403, 'Manage this private session in its dedicated chat.');
        DB::transaction(function () use ($request, $session) {
            $session = StudySession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless(LearningHub::member($request->user(), $session->community), 403, 'Join this community before booking a session.');
            abort_unless($session->status === 'scheduled' && $session->starts_at->isFuture(), 409, 'This session is no longer open for booking.');
            $this->checkTutor($session->host_id, $session->format, $session->subject);
            if ($session->participants()->where('user_id', auth()->id())->exists()) return;
            abort_if($session->participants()->count() >= $session->capacity, 409, 'This session is full.');
            User::whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            LearningHub::checkConflict(auth()->id(), $session->starts_at, $session->ends_at, $session->id);
            $session->participants()->create(['user_id' => auth()->id()]);
        });
        return back()->with('success', 'Your place is booked.');
    }

    public function leave(Request $request, StudySession $session)
    {
        abort_if($session->conversation()->exists(), 403, 'Manage this private session in its dedicated chat.');
        DB::transaction(function () use ($session) {
            $locked = StudySession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->host_id === auth()->id(), 403, 'Hosts must cancel the session instead.');
            abort_unless($locked->status === 'scheduled' && $locked->starts_at->isFuture(), 409, 'You can only leave before the session starts.');
            $locked->participants()->where('user_id', auth()->id())->delete();
        });
        return back()->with('success', 'You left the session.');
    }

    public function cancel(Request $request, StudySession $session)
    {
        abort_if($session->conversation()->exists(), 403, 'Manage this private session in its dedicated chat.');
        DB::transaction(function () use ($session) {
            $locked = StudySession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->host_id === auth()->id(), 403);
            abort_unless($locked->status === 'scheduled', 409);
            $locked->update(['status' => 'cancelled']);
        });
        return back()->with('success', 'Session cancelled. No points are awarded.');
    }

    public function complete(Request $request, StudySession $session)
    {
        abort_if($session->conversation()->exists(), 403, 'Manage this private session in its dedicated chat.');
        $data = $request->validate(['attendees' => ['nullable', 'array'], 'attendees.*' => ['integer', 'distinct']]);
        DB::transaction(function () use ($session, $data) {
            $locked = StudySession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->host_id === auth()->id(), 403);
            abort_unless($locked->status === 'scheduled' && $locked->ends_at->isPast(), 409, 'Attendance can be recorded after the scheduled end.');
            $ids = $data['attendees'] ?? [];
            $valid = $locked->participants()->where('user_id', '!=', $locked->host_id)->pluck('user_id')->all();
            abort_if(count(array_diff($ids, $valid)), 422, 'Only booked attendees can be marked present.');
            $locked->participants()->whereIn('user_id', array_merge($ids, [$locked->host_id]))->update(['attended_at' => now()]);
            $locked->update(['status' => 'completed']);
        });
        return back()->with('success', 'Attendance recorded. Attendees must confirm before progress and points are credited.');
    }

    public function confirm(Request $request, StudySession $session)
    {
        abort_if($session->conversation()->exists(), 403, 'Manage this private session in its dedicated chat.');
        DB::transaction(function () use ($request, $session) {
            $locked = StudySession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->canView($request->user()), 403);
            abort_if($locked->host_id === auth()->id(), 403, 'An attendee must confirm the host’s session.');
            abort_unless($locked->status === 'completed', 409);
            $participant = $locked->participants()->where('user_id', auth()->id())->firstOrFail();
            abort_unless($participant->attended_at, 422, 'The host has not marked you present. Contact the host if this is incorrect.');
            if ($participant->confirmed_at) return;
            $participant->update(['confirmed_at' => now()]);
            $locked->participants()->where('user_id', $locked->host_id)->whereNull('confirmed_at')->update(['confirmed_at' => now()]);
            LearningHub::award($locked, auth()->id());
            LearningHub::award($locked, $locked->host_id);
        });
        return back()->with('success', 'Participation confirmed. Progress and points updated.');
    }

    private function checkTutor(int $host, string $format, string $subject): void
    {
        abort_if($format === 'tutor' && !UserSubjectQualification::where('user_id', $host)->where('status', 'approved')->where('subject_name', $subject)->exists(), 422, 'The host needs an approved teaching qualification for this subject.');
    }
}
