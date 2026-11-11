<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use App\Models\StudyParticipant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScheduleController extends Controller
{
    public function index()
    {
        $bookings = StudyParticipant::where('user_id', auth()->id())
            ->with(['session.host', 'session.community'])
            ->whereHas('session', fn ($q) => $q->where('status', 'scheduled'))
            ->get()->sortBy('session.starts_at');
        $confirmations = StudyParticipant::where('user_id', auth()->id())
            ->whereNotNull('attended_at')->whereNull('confirmed_at')
            ->with('session')->whereHas('session', fn ($q) => $q->where('status', 'completed')->where('host_id', '!=', auth()->id()))->get();
        $timezone = auth()->user()->learning_timezone;
        $bookingEvents = $bookings->map(fn ($booking) => [
            'title' => $booking->session->title,
            'date' => $booking->session->starts_at->copy()->setTimezone($timezone)->format('Y-m-d'),
            'time' => $booking->session->starts_at->copy()->setTimezone($timezone)->format('g:i A'),
            'url' => route('study-sessions.show', $booking->session),
        ])->values();
        return view('schedule', compact('bookings', 'confirmations', 'bookingEvents', 'timezone'));
    }

    public function getSchedule()
    {
        $schedule = Schedule::where('user_id', Auth::id())
            ->orderBy('day_index')->orderBy('start_time')->get();
        return response()->json(['schedule' => $schedule]);
    }

    public function store(Request $request)
    {
        // Preserve compatibility with callers that still submit one start/end pair.
        // The new UI submits the entire list of slots for exactly one weekday.
        $data = $request->validate([
            'day_index' => ['required', 'integer', 'between:0,6'],
            'slots' => ['required_without_all:start_time,end_time', 'array', 'max:8'],
            'slots.*.start_time' => ['required', 'date_format:H:i'],
            'slots.*.end_time' => ['required', 'date_format:H:i'],
            'start_time' => ['required_without:slots', 'date_format:H:i'],
            'end_time' => ['required_without:slots', 'date_format:H:i'],
        ]);
        $slots = array_key_exists('slots', $data)
            ? $data['slots']
            : [['start_time' => $data['start_time'], 'end_time' => $data['end_time']]];
        usort($slots, fn ($a, $b) => strcmp($a['start_time'], $b['start_time']));

        $endOfPrevious = null;
        foreach ($slots as $i => $slot) {
            if ($slot['start_time'] >= $slot['end_time']) {
                throw ValidationException::withMessages([
                    'slots.'.$i.'.end_time' => 'The end time must be later than the start time.',
                ]);
            }
            if ($endOfPrevious !== null && $slot['start_time'] < $endOfPrevious) {
                throw ValidationException::withMessages([
                    'slots.'.$i.'.start_time' => 'Time slots cannot overlap. Adjacent slots are allowed.',
                ]);
            }
            $endOfPrevious = $slot['end_time'];
        }

        DB::transaction(function () use ($data, $slots) {
            // Lock user first so two saves for the same day cannot interleave.
            User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();
            Schedule::where('user_id', Auth::id())->where('day_index', $data['day_index'])->delete();
            foreach ($slots as $slot) {
                Schedule::create([
                    'user_id' => Auth::id(),
                    'day_index' => $data['day_index'],
                    'start_time' => $slot['start_time'],
                    'end_time' => $slot['end_time'],
                ]);
            }
        });

        return response()->json([
            'message' => count($slots) ? 'Availability saved successfully.' : 'Availability cleared successfully.',
            'slots' => count($slots),
        ]);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate(['day_index' => ['required', 'integer', 'between:0,6']]);
        DB::transaction(function () use ($data) {
            User::whereKey(Auth::id())->lockForUpdate()->firstOrFail();
            Schedule::where('user_id', Auth::id())->where('day_index', $data['day_index'])->delete();
        });
        return response()->json(['message' => 'Availability cleared successfully.']);
    }
}
