<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Comment;
use App\Models\Message;
use App\Models\Post;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\CommunityBanner;
use App\Models\SessionRating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'reportable_id' => 'required|integer',
            'reportable_type' => ['required', Rule::in([Post::class, Comment::class, Message::class, User::class, CommunityBanner::class, SessionRating::class])],
            'reason' => 'required|string|max:255',
            'details' => 'nullable|string|max:1000',
            'block_user' => ['nullable', 'boolean'],
        ]);

        $reportable = $validated['reportable_type']::query()->findOrFail($validated['reportable_id']);
        $targetUserId = match (true) {
            $reportable instanceof User => $reportable->id,
            $reportable instanceof Message => $reportable->sender_id,
            $reportable instanceof Post => $reportable->user_id,
            $reportable instanceof Comment => $reportable->user_id,
            $reportable instanceof CommunityBanner => $reportable->uploaded_by ?? $reportable->community->user_id,
            $reportable instanceof SessionRating => $reportable->learner_id,
        };
        abort_if($targetUserId === auth()->id(), 422, 'You cannot report your own account or content.');
        if ($reportable instanceof SessionRating) {
            abort_unless($reportable->teacher_id === auth()->id(), 403, 'Only the review recipient can report this review.');
            abort_if($reportable->reports()->where('status', 'resolved')->exists(), 409, 'This review was already removed by an administrator.');
            $request->validate(['reason' => ['required', Rule::in(['Harassment or threats', 'Spam or unrelated content', 'Suspected retaliation'])]]);
        }

        if ($reportable instanceof Message) {
            abort_unless($reportable->conversation->users()->whereKey(auth()->id())->exists(), 403);
            abort_unless(Message::visibleTo(auth()->id())->whereKey($reportable->id)->exists(), 404);
            abort_if($reportable->is_unsent, 422, 'Unsent messages cannot be reported.');
        }
        if ($reportable instanceof Post || $reportable instanceof Comment) {
            $community = $reportable instanceof Post ? $reportable->community : $reportable->post?->community;
            abort_unless($community && ($community->visibility === 'public' || \App\Support\LearningHub::member($request->user(), $community)), 403);
        }
        if ($reportable instanceof CommunityBanner) abort_if($reportable->removed_at, 409, 'This banner changed. Refresh the community before reporting it.');

        // Prevent duplicate reports from the same user on the same item
        $exists = Report::where('user_id', auth()->id())
            ->where('reportable_id', $validated['reportable_id'])
            ->where('reportable_type', $validated['reportable_type'])
            ->exists();

        if ($exists) {
            if (!empty($validated['block_user'])) {
                UserBlock::block(auth()->id(), $targetUserId);
                return response()->json(['message' => 'You already reported this item; the account is now blocked from direct messages.']);
            }
            return response()->json(['message' => 'You have already reported this item.'], 422);
        }

        DB::transaction(function () use ($reportable, $validated, $targetUserId) {
            if ($reportable instanceof SessionRating) {
                $reportable = SessionRating::whereKey($reportable->id)->lockForUpdate()->firstOrFail();
                abort_if(Report::where('reportable_type', SessionRating::class)->where('reportable_id', $reportable->id)->where('user_id', auth()->id())->exists(), 422, 'You have already reported this item.');
                $validated['details'] = ($validated['details'] ?? '')."\nReported review snapshot: ".$reportable->stars.'/5 — '.($reportable->comment ?? '(no comment)');
            }
            if ($reportable instanceof CommunityBanner) {
                $reportable = CommunityBanner::whereKey($reportable->id)->lockForUpdate()->firstOrFail();
                abort_if($reportable->removed_at, 409, 'This banner changed. Refresh the community before reporting it.');
                abort_if($reportable->reports()->where('user_id', auth()->id())->exists(), 422, 'You have already reported this item.');
            }
            $report = new Report([
                'user_id' => auth()->id(),
                'reason' => $validated['reason'],
                'details' => $validated['details'] ?? null,
            ]);
            $report->reportable()->associate($reportable);
            $report->save();

            if (!empty($validated['block_user'])) {
                UserBlock::block(auth()->id(), $targetUserId);
            }
        });

        return response()->json(['message' => !empty($validated['block_user'])
            ? 'Report submitted and account blocked from direct messages.'
            : 'Report submitted successfully. Our team will review it shortly.']);
    }
}
