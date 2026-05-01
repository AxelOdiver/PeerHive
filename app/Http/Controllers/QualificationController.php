<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserSubjectQualification; // Make sure this matches your model name!

class QualificationController extends Controller
{
    /**
     * Store a newly created teaching qualification request.
     */
    public function store(Request $request)
    {
        $user = auth()->user();
        
        // 1. SECURITY GATES: Check past attempts
        $latest = UserSubjectQualification::where('user_id', $user->id)->latest()->first();
        $attempts = UserSubjectQualification::where('user_id', $user->id)->count();

        if ($latest) {
            if ($latest->status === 'pending') {
                return response()->json(['errors' => ['file' => ['You already have a pending application.']]], 422);
            }
            if ($latest->status === 'approved') {
                return response()->json(['errors' => ['file' => ['You are already approved!']]], 422);
            }
            if ($attempts >= 3) {
                return response()->json(['errors' => ['file' => ['You have reached the maximum limit of 3 attempts.']]], 422);
            }
        }

        // 2. Validate the new file
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'proof_document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240', // 10MB max
        ]);

        // 3. Save the new submission
        $path = $request->file('proof_document')->store('qualifications', 'public');

        UserSubjectQualification::create([
            'user_id' => $user->id,
            'subject_name' => $validated['subject'],
            'proof_file_path' => $path,
            'status' => 'pending',
        ]);

        // 4. Return success to your AJAX SweetAlert
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Application submitted! An admin will review your proof shortly.'
            ]);
        }

        return back()->with('success', 'Application submitted!');
    }
}