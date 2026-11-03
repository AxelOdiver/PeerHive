<?php

namespace App\Http\Controllers;

use App\Models\{CatalogEntry, TopicLearningPlan};
use App\Support\TopicProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SkillTreeController extends Controller
{
    public function index(Request $request)
    {
        $progress = collect(TopicProgress::forUser($request->user()));
        $subjects = CatalogEntry::subjects();
        $requestedTopic = $progress->firstWhere('topic.id', $request->integer('topic'));
        $subject = $subjects->firstWhere('id', $request->integer('subject'))
            ?? $subjects->firstWhere('id', $requestedTopic['topic']->parent_id ?? 0)
            ?? $subjects->firstWhere('name', 'Programming') ?? $subjects->first();
        $nodes = $progress->filter(fn ($node) => $node['topic']->parent_id === $subject?->id)
            ->sortBy(fn ($node) => [$node['plan']?->sort_order ?? 0, $node['topic']->name])->values();
        $selected = $nodes->firstWhere('topic.id', $request->integer('topic')) ?? $nodes->first();
        return view('learning.skill-tree', compact('subjects', 'subject', 'nodes', 'selected'));
    }

    public function admin(Request $request)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $topics = CatalogEntry::topics();
        $topic = $topics->firstWhere('id', $request->integer('topic')) ?? $topics->first();
        $plan = $topic ? TopicLearningPlan::where('topic_id', $topic->id)->first() : null;
        return view('admin.learning-paths', compact('topics', 'topic', 'plan'));
    }

    public function update(Request $request, CatalogEntry $topic)
    {
        abort_unless($request->user()->role === 'admin', 403);
        abort_unless($topic->kind === 'topic' && $topic->status === 'approved', 404);
        $data = $request->validate(['description' => 'nullable|string|max:1500', 'prerequisite_id' => 'nullable|integer',
            'sort_order' => 'required|integer|between:0,1000', 'outcomes_text' => 'nullable|string|max:2500', 'revision' => 'required|integer|min:0']);
        $lines = collect(preg_split('/\R/u', $data['outcomes_text'] ?? ''))->map(fn ($line) => trim($line))->filter()->values();
        if ($lines->count() > 10 || $lines->contains(fn ($line) => mb_strlen($line) > 240)) throw ValidationException::withMessages(['outcomes_text' => 'Use up to 10 outcomes, each at most 240 characters.']);
        $outcomes = $lines->map(fn ($line) => ['key' => TopicLearningPlan::outcomeKey($line), 'text' => $line]);
        if ($outcomes->pluck('key')->duplicates()->isNotEmpty()) throw ValidationException::withMessages(['outcomes_text' => 'Remove duplicate outcomes.']);
        DB::transaction(function () use ($data, $topic, $outcomes) {
            // A single lock order also protects simultaneous prerequisite edits against cycles.
            $approved = CatalogEntry::approved()->where('kind', 'topic')->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $plans = TopicLearningPlan::orderBy('topic_id')->lockForUpdate()->get()->keyBy('topic_id');
            $plan = $plans->get($topic->id) ?? new TopicLearningPlan(['topic_id' => $topic->id, 'revision' => 0]);
            abort_unless($plan->revision === (int) $data['revision'], 409, 'This learning path changed. Reload before saving.');
            $parent = !empty($data['prerequisite_id']) ? (int) $data['prerequisite_id'] : null;
            if ($parent) {
                if (!isset($approved[$parent]) || $approved[$parent]->parent_id !== $topic->parent_id) throw ValidationException::withMessages(['prerequisite_id' => 'Choose an approved topic in the same subject.']);
                $visited = [$topic->id];
                for ($id = $parent; $id; $id = $plans->get($id)?->prerequisite_id) {
                    if (in_array($id, $visited, true)) throw ValidationException::withMessages(['prerequisite_id' => 'Prerequisites cannot form a cycle.']);
                    $visited[] = $id;
                }
            }
            $plan->fill(['description' => $data['description'] ?? null, 'prerequisite_id' => $parent,
                'sort_order' => $data['sort_order'], 'outcomes' => $outcomes->all(), 'revision' => $plan->revision + 1])->save();
        });
        return redirect()->route('admin.learning-paths', ['topic' => $topic->id])->with('success', 'Learning path updated. Existing activity submissions and feedback are preserved.');
    }
}
