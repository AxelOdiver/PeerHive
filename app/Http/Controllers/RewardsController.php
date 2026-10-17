<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Support\LearningHub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RewardsController extends Controller
{
    public function index(Request $request)
    {
        $records = LearningHub::records($request->user()); $total = $records->count();
        $teaching = $records->filter(fn ($p) => $p->session->format === 'tutor' && $p->session->host_id === auth()->id())->count();
        $achievements = [
            ['name' => 'First Step', 'description' => 'Confirm your first completed session.', 'count' => $total, 'target' => 1, 'icon' => 'bi-stars'],
            ['name' => 'Learning Together', 'description' => 'Confirm five completed learning sessions.', 'count' => $total - $teaching, 'target' => 5, 'icon' => 'bi-people'],
            ['name' => 'Knowledge Sharer', 'description' => 'Host five tutor-led sessions confirmed by attendees.', 'count' => $teaching, 'target' => 5, 'icon' => 'bi-lightbulb'],
        ];
        $balance = (int) DB::table('point_entries')->where('user_id', auth()->id())->sum('points');
        $owned = DB::table('user_rewards')->where('user_id', auth()->id())->pluck('reward_key')->all();
        $entries = DB::table('point_entries')->where('user_id', auth()->id())->latest('id')->paginate(10);
        return view('learning.rewards', compact('balance', 'owned', 'entries', 'achievements'));
    }

    public function unlock(Request $request)
    {
        $data = $request->validate(['reward_key' => ['required', Rule::in(array_keys(config('learning.rewards')))]]);
        DB::transaction(function () use ($data) {
            User::whereKey(auth()->id())->lockForUpdate()->firstOrFail(); $key = $data['reward_key'];
            if (DB::table('user_rewards')->where('user_id', auth()->id())->where('reward_key', $key)->exists()) return;
            $reward = config('learning.rewards')[$key];
            $balance = (int) DB::table('point_entries')->where('user_id', auth()->id())->sum('points');
            abort_if($balance < $reward['cost'], 422, 'You do not have enough points for this reward.');
            DB::table('user_rewards')->insert(['user_id' => auth()->id(), 'reward_key' => $key, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('point_entries')->insert(['user_id' => auth()->id(), 'event_key' => 'reward:'.$key.':user:'.auth()->id(), 'points' => -$reward['cost'], 'description' => 'Unlocked '.$reward['name'], 'created_at' => now(), 'updated_at' => now()]);
        });
        return back()->with('success', 'Reward unlocked. Select Apply to use it.');
    }

    public function equip(Request $request)
    {
        $data = $request->validate(['reward_key' => ['required', Rule::in(array_merge(array_keys(config('learning.rewards')), ['no_frame', 'no_cover']))]]);
        $key = $data['reward_key'];
        if (!in_array($key, ['no_frame', 'no_cover'])) abort_unless(DB::table('user_rewards')->where('user_id', auth()->id())->where('reward_key', $key)->exists(), 403, 'Unlock this reward first.');
        $field = $key === 'no_cover' || $key === 'journal_cover' ? 'progress_theme' : 'profile_frame';
        $request->user()->forceFill([$field => str_starts_with($key, 'no_') ? null : $key])->save();
        return back()->with('success', 'Your appearance has been updated.');
    }
}
