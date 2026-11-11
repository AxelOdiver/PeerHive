<?php

namespace App\Http\Controllers;

use App\Models\CatalogEntry;
use App\Models\Community;
use App\Models\StudySession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/** Privacy-aware global discovery. Deliberately excludes private messages/posts. */
class SearchDiscoveryController extends Controller
{
    public function index()
    {
        return view('discover.index');
    }

    public function results(Request $request)
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(['all', 'people', 'communities', 'skills', 'sessions'])],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $term = trim(preg_replace('/\s+/u', ' ', $data['q'] ?? '') ?? '');
        $category = $data['category'] ?? 'all';
        $limit = (int) ($data['limit'] ?? 5);
        $empty = ['people' => [], 'communities' => [], 'skills' => [], 'sessions' => []];
        $more = ['people' => false, 'communities' => false, 'skills' => false, 'sessions' => false];

        // No blank/one-character enumeration. All results are bounded and authenticated.
        if (mb_strlen($term) < 2) {
            return response()->json(['query' => $term, 'results' => $empty, 'has_more' => $more]);
        }

        $viewer = $request->user();
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term)).'%';
        $tokens = array_slice(preg_split('/\s+/u', mb_strtolower($term)) ?: [], 0, 8);
        $results = $empty;

        if ($category === 'all' || $category === 'people') {
            $query = User::query()->where('id', '!=', $viewer->id)
                ->whereNotExists(function ($blocked) use ($viewer) {
                    $blocked->selectRaw('1')->from('user_blocks')
                        ->where(fn ($pair) => $pair->where('blocker_id', $viewer->id)->whereColumn('blocked_id', 'users.id')
                            ->orWhere(fn ($inverse) => $inverse->where('blocked_id', $viewer->id)->whereColumn('blocker_id', 'users.id')));
                });
            foreach ($tokens as $token) {
                $needle = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $token).'%';
                $query->where(function ($names) use ($needle) {
                    foreach (['first_name', 'middle_name', 'last_name', 'username'] as $column) {
                        $names->orWhereRaw("LOWER($column) LIKE ? ESCAPE '!'", [$needle]);
                    }
                });
            }
            $rows = $query->orderBy('first_name')->orderBy('id')->limit($limit + 1)->get(['id', 'first_name', 'last_name', 'username', 'profile_picture']);
            $more['people'] = $rows->count() > $limit;
            $results['people'] = $rows->take($limit)->map(fn ($user) => [
                'type' => 'people', 'id' => $user->id,
                'title' => trim($user->first_name.' '.$user->last_name),
                'subtitle' => $user->username ? '@'.$user->username : 'Student',
                'url' => route('users.profile', $user),
                'avatar' => $user->profile_picture_url,
            ])->values()->all();
        }

        if ($category === 'all' || $category === 'communities') {
            $rows = Community::query()
                ->where(function ($visibility) use ($viewer) {
                    $visibility->where('visibility', 'public')->orWhere('user_id', $viewer->id)
                        ->orWhereHas('members', fn ($members) => $members->where('users.id', $viewer->id));
                })
                ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$like])
                ->orderBy('name')->orderBy('id')->limit($limit + 1)
                ->get(['id', 'name', 'subject', 'visibility', 'user_id']);
            $more['communities'] = $rows->count() > $limit;
            $results['communities'] = $rows->take($limit)->map(fn ($community) => [
                'type' => 'communities', 'id' => $community->id,
                'title' => $community->name,
                'subtitle' => $community->visibility === 'public' ? 'Community · '.$community->subject : 'Your private community',
                'url' => route('community.show', $community),
            ])->values()->all();
        }

        if ($category === 'all' || $category === 'skills') {
            $rows = CatalogEntry::approved()->where('kind', 'topic')
                ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$like])
                ->orderBy('name')->orderBy('id')->limit($limit + 1)->get(['id', 'name']);
            $more['skills'] = $rows->count() > $limit;
            $results['skills'] = $rows->take($limit)->map(fn ($topic) => [
                'type' => 'skills', 'id' => $topic->id,
                'title' => $topic->name, 'subtitle' => 'Approved learning topic',
                'url' => route('dashboard', ['learn_topic' => $topic->id]),
            ])->values()->all();
        }

        if ($category === 'all' || $category === 'sessions') {
            $rows = StudySession::visibleTo($viewer)
                // Enforce membership/public visibility even for admins. No private
                // meeting titles or links should surface in global discovery.
                ->where(function ($allowed) use ($viewer) {
                    $allowed->whereHas('participants', fn ($participants) => $participants->where('user_id', $viewer->id))
                        ->orWhereHas('community', function ($community) use ($viewer) {
                            $community->where('visibility', 'public')->orWhere('user_id', $viewer->id)
                                ->orWhereHas('members', fn ($members) => $members->where('users.id', $viewer->id));
                        });
                })
                ->where('status', 'scheduled')
                ->where('starts_at', '>', now())
                ->whereRaw("LOWER(title) LIKE ? ESCAPE '!'", [$like])
                ->orderBy('starts_at')->orderBy('id')->limit($limit + 1)
                ->get(['id', 'title', 'starts_at']);
            $more['sessions'] = $rows->count() > $limit;
            $results['sessions'] = $rows->take($limit)->map(fn ($session) => [
                'type' => 'sessions', 'id' => $session->id,
                'title' => $session->title,
                'subtitle' => 'Study session · '.$session->starts_at->format('M j, Y'),
                'url' => route('study-sessions.show', $session),
            ])->values()->all();
        }

        return response()->json(['query' => $term, 'results' => $results, 'has_more' => $more]);
    }
}
