<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Schedule;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
    public function index()
    {
        $bookings = \App\Models\StudyParticipant::where('user_id', auth()->id())->with('session')->whereHas('session', fn ($q) => $q->where('status', 'scheduled'))->get()->sortBy('session.starts_at');
        $confirmations = \App\Models\StudyParticipant::where('user_id', auth()->id())->whereNotNull('attended_at')->whereNull('confirmed_at')->with('session')->whereHas('session', fn ($q) => $q->where('status', 'completed')->where('host_id', '!=', auth()->id()))->get();
        $timezone = auth()->user()->learning_timezone;
        $bookingEvents = $bookings->map(fn ($booking) => [
            'title' => $booking->session->title,
            'date' => $booking->session->starts_at->copy()->setTimezone($timezone)->format('Y-m-d'),
            'time' => $booking->session->starts_at->copy()->setTimezone($timezone)->format('g:i A'),
            'url' => route('study-sessions.show', $booking->session),
        ])->values();
        return view('schedule', compact('bookings', 'confirmations', 'bookingEvents', 'timezone'));
    }

    // Fetch the logged-in user's schedule
    public function getSchedule()
    {
        $schedule = Schedule::where('user_id', Auth::id())->get();

        return response()->json([
            'schedule' => $schedule,
        ]);
    }

    // Create or Update a specific day's time
    public function store(Request $request) 
    {
        // Validates that the request actually has the needed data
        $validated = $request->validate([
            'day_index' => 'required|integer',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
        ]);



        Schedule::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'day_index' => $validated['day_index'],
            ],
            [
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
            ]
        );

        return response()->json([
            'message' => 'Schedule updated successfully',
        ]);
    }

    // Delete a specific day's time
    public function destroy(Request $request)
    {
        Schedule::where('user_id', Auth::id())
            ->where('day_index', $request->input('day_index'))
            ->delete();

        return response()->json([
            'message' => 'Schedule deleted successfully',
        ]);
    }
}
