<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reportable_id' => 'required|integer',
            'reportable_type' => ['required', Rule::in(['App\Models\Post', 'App\Models\Comment'])],
            'reason' => 'required|string|max:255',
            'details' => 'nullable|string|max:1000',
        ]);

        // Prevent duplicate reports from the same user on the same item
        $exists = Report::where('user_id', auth()->id())
            ->where('reportable_id', $validated['reportable_id'])
            ->where('reportable_type', $validated['reportable_type'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'You have already reported this item.'], 422);
        }

        $report = new Report($validated);
        $report->user_id = auth()->id();
        $report->reportable_id = $validated['reportable_id'];
        $report->reportable_type = $validated['reportable_type'];
        $report->save();

        return response()->json(['message' => 'Report submitted successfully. Our team will review it shortly.']);
    }
}
