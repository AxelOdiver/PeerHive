<?php

namespace App\Http\Controllers;

use App\Models\CatalogEntry;
use App\Support\BellNotifications;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogController extends Controller
{
    /** Register a requester within the caller's transaction. */
    public static function suggest(string $kind, string $name, int $user, ?int $parent = null, ?string $purpose = null, string $source = 'profile', ?string $description = null): CatalogEntry
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        if ($kind === 'subject' && CatalogEntry::key($name) === CatalogEntry::key('Existing teaching subjects')) {
            throw ValidationException::withMessages(['subject_name' => 'Enter a real subject name. This placeholder category has been retired.']);
        }

        $entry = CatalogEntry::firstOrCreate(
            ['kind' => $kind, 'name_key' => CatalogEntry::key($name)],
            ['name' => $name, 'parent_id' => $parent, 'submitted_by' => $user]
        );
        // Use the same lock as review so the requester is registered before a
        // decision, or sees the completed decision and cannot join afterward.
        $entry = CatalogEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
        // Preserve the original Profile Settings behavior: a personal degree-program
        // value can remain saved even if its shared-catalog suggestion was reviewed.
        // The public suggestion endpoint rejects already reviewed entries below.
        if ($entry->status !== 'pending') return $entry;
        if ($kind === 'topic' && (int) $entry->parent_id !== (int) $parent) {
            throw ValidationException::withMessages(['name' => 'A topic with this name is already suggested under another subject. Review that suggestion first.']);
        }
        $requester = DB::table('catalog_entry_requesters')->where('catalog_entry_id', $entry->id)->where('user_id', $user);
        $existing = (clone $requester)->lockForUpdate()->first();
        if (!$existing) {
            DB::table('catalog_entry_requesters')->insertOrIgnore([
                'catalog_entry_id' => $entry->id, 'user_id' => $user,
                'purpose' => $purpose, 'source' => $source, 'description' => $description,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return $entry;
        }
        if ($existing && $purpose && $existing->purpose && $existing->purpose !== $purpose && $existing->purpose !== 'both') {
            $requester->update(['purpose' => 'both', 'updated_at' => now()]);
        } elseif ($existing && $purpose && !$existing->purpose) {
            $requester->update(['purpose' => $purpose, 'updated_at' => now()]);
        }
        return $entry;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'kind' => ['required', 'in:program,subject,topic'],
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'subject_id' => ['nullable', 'integer'],
            'subject_name' => ['nullable', 'string', 'min:3', 'max:100'],
            'purpose' => ['nullable', 'in:learn,share,both'],
            'source' => ['nullable', 'in:onboarding_skills,onboarding_learn,profile'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);
        $entry = DB::transaction(function () use ($data, $request) {
            $parent = null;
            if ($data['kind'] === 'topic') {
                if (!empty($data['subject_id'])) {
                    $parent = CatalogEntry::approved()->where('kind', 'subject')->findOrFail($data['subject_id']);
                } else {
                    if (trim($data['subject_name'] ?? '') === '') {
                        throw ValidationException::withMessages(['subject_name' => 'Choose a subject or enter a missing subject name.']);
                    }
                    $parent = self::suggest('subject', $data['subject_name'], $request->user()->id, null, $data['purpose'] ?? null, $data['source'] ?? 'profile');
                    if (!in_array($parent->status, ['approved', 'pending'], true)) {
                        throw ValidationException::withMessages(['subject_name' => 'This subject was already reviewed. Choose an approved subject or suggest another one.']);
                    }
                }
            }
            $entry = self::suggest(
                $data['kind'], $data['name'], $request->user()->id, $parent?->id,
                $data['purpose'] ?? null, $data['source'] ?? 'profile', $data['description'] ?? null
            );
            if ($entry->status === 'approved') {
                throw ValidationException::withMessages(['name' => 'This entry already exists. Select it from the catalog.']);
            }
            if ($entry->status !== 'pending') {
                throw ValidationException::withMessages(['name' => 'This suggestion was already reviewed. Check the approved catalog or contact an administrator.']);
            }
            return $entry;
        });
        $message = 'Suggestion submitted for admin review. You can continue using PeerHive while it is pending.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'id' => $entry->id, 'status' => $entry->status]);
        }
        return back()->with('catalog_status', $message);
    }

    public function index(Request $request)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $kind = $request->query('kind', 'program');
        abort_unless(is_string($kind) && in_array($kind, ['program', 'subject', 'topic'], true), 404);
        $entries = CatalogEntry::with(['parent', 'submitter', 'mergedInto', 'requesters'])
            ->withCount('requesters')
            ->where('kind', $kind)
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('name')->get();
        $mergeTargets = CatalogEntry::approved()->where('kind', $kind)->orderBy('name')->get(['id', 'name']);
        return view('admin.catalog', compact('entries', 'kind', 'mergeTargets'));
    }

    public function review(Request $request, CatalogEntry $entry)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $status = DB::transaction(function () use ($entry, $request) {
            $locked = CatalogEntry::whereKey($entry->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 409, 'This entry has already been reviewed.');
            $data = $request->validate([
                'status' => ['required', 'in:approved,merged,rejected'],
                'merge_into_id' => ['required_if:status,merged', 'nullable', 'integer'],
                'review_note' => ['required_if:status,rejected', 'nullable', 'string', 'max:1000'],
            ]);
            if ($data['status'] === 'rejected' && mb_strlen(trim($data['review_note'] ?? '')) < 10) {
                throw ValidationException::withMessages(['review_note' => 'Provide a reason of at least 10 characters when rejecting a suggestion.']);
            }

            $target = null;
            if ($data['status'] === 'approved' && $locked->kind === 'topic') {
                abort_unless($locked->parent?->status === 'approved', 422, 'Approve or merge the parent subject first.');
            }
            if ($data['status'] === 'merged') {
                $target = CatalogEntry::approved()->where('kind', $locked->kind)
                    ->whereKey($data['merge_into_id'])->lockForUpdate()->first();
                abort_unless($target && $target->id !== $locked->id, 422, 'Choose an approved entry of the same type to merge into.');
            }
            $note = trim((string) ($data['review_note'] ?? ''));
            $locked->forceFill([
                'status' => $data['status'], 'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(), 'review_note' => $note ?: null,
                'merged_into_id' => $target?->id,
            ])->save();

            if ($locked->kind === 'subject' && $data['status'] === 'merged') {
                CatalogEntry::where('kind', 'topic')->where('status', 'pending')
                    ->where('parent_id', $locked->id)->update(['parent_id' => $target->id, 'updated_at' => now()]);
            }
            if ($locked->kind === 'subject' && $data['status'] === 'rejected') {
                $children = CatalogEntry::where('kind', 'topic')->where('status', 'pending')
                    ->where('parent_id', $locked->id)->lockForUpdate()->get();
                foreach ($children as $child) {
                    $child->forceFill([
                        'status' => 'rejected', 'reviewed_by' => $request->user()->id,
                        'reviewed_at' => now(), 'review_note' => 'Parent subject was rejected: '.$locked->name,
                    ])->save();
                    self::notifyRequesters($child, 'rejected', null);
                }
            }
            self::notifyRequesters($locked, $data['status'], $target);
            return $locked->status;
        });
        return back()->with('catalog_status', 'Catalog suggestion '.$status.'.');
    }

    private static function notifyRequesters(CatalogEntry $entry, string $status, ?CatalogEntry $target): void
    {
        $kindLabel = $entry->kind === 'topic' ? 'topic' : ($entry->kind === 'program' ? 'program' : 'subject');
        $title = 'Catalog suggestion '.($status === 'merged' ? 'merged' : $status);
        $body = match ($status) {
            'approved' => 'Your suggested '.$kindLabel.' "'.$entry->name.'" was approved and is now available in the catalog.',
            'merged' => 'Your suggested '.$kindLabel.' "'.$entry->name.'" was merged into "'.$target?->name.'".',
            default => 'Your suggested '.$kindLabel.' "'.$entry->name.'" was not approved. Check your suggestions for details.',
        };
        $destination = route('profile', ['tab' => $entry->kind === 'program' ? 'academic' : 'skills']);
        foreach ($entry->requesters()->pluck('users.id') as $userId) {
            BellNotifications::record((int) $userId, 'catalog:'.$entry->id.':'.$status.':'.$userId,
                'catalog', $title, $body, $destination);
        }
    }
}
