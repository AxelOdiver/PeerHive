<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\Message;
use App\Models\User;
use App\Models\CommunityBanner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    // Display the list of reports
    public function index()
    {
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Unauthorized. Admins only.');
        }

        $reports = Report::with(['reporter', 'reportable'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
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

        if ($report->reportable_type === \App\Models\SessionRating::class) {
            DB::transaction(function () use ($report) {
                $locked = Report::whereKey($report->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->status === 'pending', 409, 'This report has already been reviewed.');
                $locked->update(['status' => 'dismissed']);
            });
        } else {
            $report->update(['status' => 'dismissed']);
        }
        
        return back()->with('success', 'Report dismissed successfully.');
    }

    // Delete the offending content (Post or Comment) and resolve the report
    public function destroyContent(Report $report)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Unauthorized. Admins only.');
        }

        if ($report->reportable_type === \App\Models\SessionRating::class) {
            DB::transaction(function () use ($report) {
                $locked = Report::whereKey($report->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->status === 'pending', 409, 'This report has already been reviewed.');
                // Retain the session's rating record to prevent a replacement submission.
                \App\Models\SessionRating::whereKey($locked->reportable_id)->lockForUpdate()->first();
                $locked->update(['status' => 'resolved']);
            });
            return back()->with('success', 'Review removed from public ratings and report resolved.');
        }

        if ($report->reportable instanceof CommunityBanner) {
            DB::transaction(function () use ($report) {
                $locked = Report::whereKey($report->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->status === 'pending', 409, 'This report has already been reviewed.');
                $banner = CommunityBanner::whereKey($locked->reportable_id)->lockForUpdate()->first();
                if ($banner && !$banner->removed_at) $banner->update(['removed_at' => now()]);
                $locked->update(['status' => 'resolved']);
            });
            return back()->with('success', 'Banner report resolved. The community remains available.');
        }

        // Account reports are reviewed and dismissed separately; never delete an account here.
        if ($report->reportable instanceof Message && $report->reportable->attachment_path) {
            Storage::disk('public')->delete($report->reportable->attachment_path);
            Storage::disk('local')->delete($report->reportable->attachment_path);
        }
        if ($report->reportable && !($report->reportable instanceof User)) {
            $report->reportable->delete();
        }

        // Mark the report as resolved
        $report->update(['status' => 'resolved']);

        return back()->with('success', 'Content deleted and report resolved.');
    }
}
