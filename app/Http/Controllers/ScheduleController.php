<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Schedule;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
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
        $request->validate([
            'day_index' => 'required|integer',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
        ]);

        Schedule::updateOrCreate(
            [
                'user_id' => Auth::id(),
                'day_index' => $request->input('day_index'),
            ],
            [
                'start_time' => $request->input('start_time'),
                'end_time' => $request->input('end_time'),
            ]
        );

        return response()->json([
            'message' => 'Schedule saved successfully',
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