<?php

namespace App\Http\Controllers;

use App\Models\CatalogEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GetStartedController extends Controller
{
    public function show(Request $request)
    {
        if (! $request->user()->onboarding_required) {
            return redirect()->route('dashboard');
        }
        $step = $request->query('step') === 'learn' ? 'learn' : 'skills';
        $topics = CatalogEntry::topics();
        $subjects = CatalogEntry::subjects();
        $selected = $step === 'learn'
            ? ($request->user()->learning_interests ?? [])
            : ($request->user()->expertise ?? []);

        return view('onboarding.get-started', compact('step', 'topics', 'selected', 'subjects'));
    }

    public function skills(Request $request)
    {
        $user = $request->user();
        abort_unless($user->onboarding_required, 409, 'Your onboarding is already complete.');
        $topics = $this->validateTopics($request);
        $user->forceFill(['expertise' => $topics])->save();
        return redirect()->route('onboarding.show', ['step' => 'learn']);
    }

    public function learn(Request $request)
    {
        $user = $request->user();
        abort_unless($user->onboarding_required, 409, 'Your onboarding is already complete.');
        $topics = $this->validateTopics($request);
        DB::transaction(function () use ($user, $topics) {
            $user->forceFill([
                'learning_interests' => $topics,
                'onboarding_required' => false,
                'onboarding_completed_at' => now(),
            ])->save();
        });
        return redirect()->route('dashboard')->with('success', 'Welcome to PeerHive!');
    }

    public function skip(Request $request)
    {
        $user = $request->user();
        if (! $user->onboarding_required) return redirect()->route('dashboard');
        if ($request->input('step') === 'skills') {
            return redirect()->route('onboarding.show', ['step' => 'learn']);
        }
        abort_unless($request->input('step') === 'learn', 422);
        $user->forceFill(['onboarding_required' => false, 'onboarding_completed_at' => now()])->save();
        return redirect()->route('dashboard')->with('success', 'You can add skills and interests in your profile anytime.');
    }

    private function validateTopics(Request $request): array
    {
        $allowed = CatalogEntry::topics()->pluck('name')->all();
        $validated = $request->validate([
            'topics' => ['sometimes', 'array', 'max:10'],
            'topics.*' => ['required', 'string', 'distinct:strict', Rule::in($allowed)],
        ]);
        return array_values($validated['topics'] ?? []);
    }
}
