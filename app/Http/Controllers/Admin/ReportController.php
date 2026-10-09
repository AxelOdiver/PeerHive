<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    // Display the list of reports
    public function index()
    {
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Unauthorized. Admins only.');
        }

        $reports = Report::with(['reporter', 'reportable.user'])
            ->orderByRaw("FIELD(status, 'pending') DESC") // Show pending first
            ->latest()
            ->paginate(20);

        return view('admin.reports', compact('reports'));
    }

    // Dismiss a false report
    public function dismiss(Report $report)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Unauthorized. Admins only.');
        }

        $report->update(['status' => 'dismissed']);
        
        return back()->with('success', 'Report dismissed successfully.');
    }

    // Delete the offending content (Post or Comment) and resolve the report
    public function destroyContent(Report $report)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Unauthorized. Admins only.');
        }

        // Delete the actual post or comment
        if ($report->reportable) {
            $report->reportable->delete();
        }

        // Mark the report as resolved
        $report->update(['status' => 'resolved']);

        return back()->with('success', 'Content deleted and report resolved.');
    }
}
