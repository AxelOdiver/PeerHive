<?php
namespace App\Http\Controllers;

use App\Models\StudyParticipant;
use App\Support\LearningHub;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LearningProgressController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user(); $records = LearningHub::records($user);
        $timezone = $user->learning_timezone ?: config('learning.timezone');
        $week = now()->setTimezone($timezone)->startOfWeek();
        $stats = ['learning' => 0, 'teaching' => 0, 'learning_minutes' => 0, 'teaching_minutes' => 0];
        $weekly = ['learning' => 0, 'teaching' => 0, 'minutes' => 0];
        $days = array_fill(0, 7, ['learning' => 0, 'teaching' => 0]);
        foreach ($records as $record) {
            $s = $record->session;
            $duration = (int) $s->starts_at->diffInMinutes($s->ends_at);
            $isTeacher = $s->format === 'tutor' && $s->host_id === $user->id;
            $minutesByRole = ['learning' => $record->learning_minutes ?? ($isTeacher ? 0 : $duration), 'teaching' => $record->teaching_minutes ?? ($isTeacher ? $duration : 0)];
            $date = $s->starts_at->copy()->setTimezone($timezone);
            foreach ($minutesByRole as $role => $minutes) {
                if (!$minutes) continue;
                $stats[$role]++; $stats[$role.'_minutes'] += $minutes;
                if ($date->greaterThanOrEqualTo($week) && $date->lessThan($week->copy()->addWeek())) {
                    $weekly[$role]++; $weekly['minutes'] += $minutes; $days[$date->dayOfWeekIso - 1][$role] += $minutes;
                }
            }
        }
        $subjects = $records->flatMap(fn ($record) => array_filter([$record->session->subject, $record->session->conversation?->session_context['offered_subject'] ?? null]))->unique()->count();
        $balance = (int) DB::table('point_entries')->where('user_id', $user->id)->sum('points');
        $goals = $user->learning_goals ?? ['learning' => 3, 'teaching' => 2, 'hours' => 4];
        $recent = StudyParticipant::where('user_id', $user->id)->whereHas('session', fn ($q) => $q->where('status', 'completed'))->with('session')->latest('updated_at')->limit(5)->get();
        return view('learning.progress', compact('records', 'stats', 'weekly', 'days', 'subjects', 'balance', 'goals', 'timezone', 'recent'));
    }

    public function goals(Request $request)
    {
        $data = $request->validate(['learning' => ['required', 'integer', 'between:1,20'], 'teaching' => ['required', 'integer', 'between:1,20'], 'hours' => ['required', 'integer', 'between:1,40'], 'timezone' => ['required', 'timezone']]);
        $request->user()->forceFill(['learning_goals' => collect($data)->except('timezone')->all(), 'learning_timezone' => $data['timezone']])->save();
        return back()->with('success', 'Weekly goals updated.');
    }

    public function history()
    {
        $participations = StudyParticipant::where('user_id', auth()->id())->with(['session.community', 'session.host'])->whereHas('session', fn ($q) => $q->whereIn('status', ['completed', 'cancelled']))->latest('updated_at')->paginate(15);
        return view('history', compact('participations'));
    }
}
