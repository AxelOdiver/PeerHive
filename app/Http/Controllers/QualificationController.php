<?php

namespace App\Http\Controllers;

use App\Models\{CatalogEntry, User, UserSubjectQualification};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Validation\{Rule, ValidationException};

class QualificationController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'subject' => ['required', 'string', Rule::in(CatalogEntry::names())],
            'proof_document' => 'required|file|mimes:jpg,jpeg,png,pdf|extensions:jpg,jpeg,png,pdf|max:10240',
        ]);
        $path = null;
        try {
            DB::transaction(function () use ($request, $data, &$path) {
                User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $past = UserSubjectQualification::where('user_id', $request->user()->id)
                    ->where('subject_name', $data['subject'])->get();
                if ($past->contains(fn ($q) => in_array($q->status, ['pending', 'approved']))) {
                    throw ValidationException::withMessages(['subject' => 'This topic already has a pending or approved application.']);
                }
                if ($past->count() >= 3) {
                    throw ValidationException::withMessages(['subject' => 'You have reached three attempts for this topic. Contact an administrator.']);
                }
                $path = $request->file('proof_document')->store('qualifications', 'local');
                if (!$path) throw new \RuntimeException('Your proof could not be saved. Please try again.');
                UserSubjectQualification::create([
                    'user_id' => $request->user()->id, 'subject_name' => $data['subject'],
                    'proof_file_path' => $path, 'status' => 'pending',
                ]);
            });
        } catch (\Throwable $error) {
            if ($path) Storage::disk('local')->delete($path);
            throw $error;
        }
        return $request->expectsJson()
            ? response()->json(['message' => 'Topic qualification submitted for review.'])
            : back()->with('success', 'Topic qualification submitted for review.');
    }
}
