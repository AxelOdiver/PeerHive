<?php

namespace App\Http\Controllers;

use App\Models\CatalogEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogController extends Controller
{
    public static function suggest(string $kind, string $name, int $user, ?int $parent = null): CatalogEntry
    {
        if ($kind === 'subject' && CatalogEntry::key($name) === CatalogEntry::key('Existing teaching subjects')) {
            throw ValidationException::withMessages(['subject_name' => 'Enter a real subject name. This placeholder category has been retired.']);
        }
        $entry = CatalogEntry::firstOrCreate(['kind' => $kind, 'name_key' => CatalogEntry::key($name)], ['name' => trim($name), 'parent_id' => $parent, 'submitted_by' => $user]);
        return $entry;
    }

    public function store(Request $request)
    {
        $data = $request->validate(['kind' => 'required|in:program,topic', 'name' => 'required|string|max:100', 'subject_id' => 'nullable|integer', 'subject_name' => 'nullable|string|max:100']);
        DB::transaction(function () use ($data, $request) {
            $parent = null;
            if ($data['kind'] === 'topic') {
                if (!empty($data['subject_id'])) {
                    $parent = CatalogEntry::approved()->where('kind', 'subject')->findOrFail($data['subject_id']);
                } else {
                    if (trim($data['subject_name'] ?? '') === '') throw ValidationException::withMessages(['subject_name' => 'Enter the missing subject name.']);
                    $parent = self::suggest('subject', $data['subject_name'], $request->user()->id);
                    if ($parent->status === 'rejected') throw ValidationException::withMessages(['subject_name' => 'This subject was rejected. Please contact an administrator.']);
                }
            }
            $entry = self::suggest($data['kind'], $data['name'], $request->user()->id, $parent?->id);
            if ($entry->status === 'approved') throw ValidationException::withMessages(['name' => 'This entry already exists. Select it from the catalog.']);
            if ($entry->status === 'rejected') throw ValidationException::withMessages(['name' => 'This suggestion was rejected. Please contact an administrator.']);
        });
        return back()->with('catalog_status', 'Suggestion submitted for admin review. It becomes available to everyone after approval.');
    }

    public function index()
    {
        abort_unless(auth()->user()->role === 'admin', 403);
        $entries = CatalogEntry::with(['parent', 'submitter'])->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")->orderBy('kind')->orderBy('name')->get();
        return view('admin.catalog', compact('entries'));
    }

    public function review(Request $request, CatalogEntry $entry)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate(['status' => 'required|in:approved,rejected', 'review_note' => 'nullable|string|max:1000']);
        DB::transaction(function () use ($entry, $data, $request) {
            $locked = CatalogEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 409, 'This entry has already been reviewed.');
            abort_if($data['status'] === 'approved' && $locked->kind === 'topic' && $locked->parent?->status !== 'approved', 422, 'Approve the parent subject first.');
            $locked->forceFill($data + ['reviewed_by' => $request->user()->id])->save();
        });
        return back()->with('catalog_status', 'Catalog suggestion reviewed.');
    }
}
