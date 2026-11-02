<?php

namespace App\Http\Controllers;

use App\Models\UserSubjectQualification;
use Illuminate\Http\Request;

class AdminQualificationController extends Controller
{
    // Show the CRUD Table
    public function index()
    {
        // STRICT GATEKEEPER: Kick out anyone who isn't an admin
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Unauthorized. Admins only.');
        }

        // Grab all qualifications, sorting "pending" ones to the top!
        $qualifications = UserSubjectQualification::with('user')
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 WHEN status = 'approved' THEN 1 ELSE 2 END")
            ->latest()
            ->get();

        return view('admin.qualifications', compact('qualifications'));
    }

    // Handle the Approve/Reject button clicks
    public function respond(Request $request, $id)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Unauthorized.');
        }

        $request->validate([
            'status' => 'required|in:approved,rejected',
            'rejection_reason' => 'required_if:status,rejected|nullable|string|max:1000',
        ]);

        $qualification = UserSubjectQualification::findOrFail($id);
        abort_unless($qualification->status === 'pending', 409, 'This qualification has already been reviewed.');
        
        $updated = UserSubjectQualification::whereKey($qualification->id)->where('status', 'pending')->update([
            'status' => $request->status,
            'rejection_reason' => $request->status === 'rejected'
                ? $request->input('rejection_reason')
                : null,
        ]);
        abort_unless($updated === 1, 409, 'This qualification has already been reviewed.');

        return back()->with('success', "Student qualification has been {$request->status}!");
    }
}
