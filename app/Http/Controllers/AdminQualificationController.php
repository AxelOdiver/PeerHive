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
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'rejected')")
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
            'status' => 'required|in:approved,rejected'
        ]);

        $qualification = UserSubjectQualification::findOrFail($id);
        
        $qualification->update([
            'status' => $request->status
        ]);

        return back()->with('success', "Student qualification has been {$request->status}!");
    }
}