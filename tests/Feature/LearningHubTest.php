<?php

namespace Tests\Feature;

use App\Models\{Community, StudySession, StudyParticipant, User, UserSubjectQualification};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearningHubTest extends TestCase
{
    use RefreshDatabase;

    private function group(User $host, array $members = [], string $visibility = 'public'): Community
    {
        $group = Community::create(['user_id' => $host->id, 'name' => 'Learning Circle', 'subject' => 'Coding', 'member_limit' => 25, 'visibility' => $visibility]);
        $group->members()->attach(array_unique(array_merge([$host->id], array_map(fn ($user) => $user->id, $members))));
        return $group;
    }

    private function payload(Community $group, array $overrides = []): array
    {
        return array_replace(['community_id' => $group->id, 'title' => 'Python practice', 'description' => 'Practise together.', 'subject' => 'Coding', 'format' => 'peer', 'starts_at' => now()->addDay()->setTimezone('Asia/Taipei')->format('Y-m-d\TH:i'), 'timezone' => 'Asia/Taipei', 'duration' => 60, 'capacity' => 3, 'meeting_method' => 'online', 'meeting_details' => 'secret-room-code'], $overrides);
    }

    private function createSession(User $host, Community $group, array $extra = []): StudySession
    {
        $this->actingAs($host)->post('/study-sessions', $this->payload($group, $extra))->assertRedirect();
        return StudySession::latest('id')->firstOrFail();
    }

    public function test_pages_require_login_and_new_accounts_show_real_empty_states(): void
    {
        $this->get('/progress')->assertRedirect('/login');
        $this->get('/rewards')->assertRedirect('/login');
        $this->get('/study-sessions')->assertRedirect('/login');
        $this->withoutVite(); $user = User::factory()->create();
        foreach (['/progress', '/rewards', '/study-sessions', '/study-sessions/create', '/schedule', '/history', '/dashboard'] as $url) {
            $this->actingAs($user)->get($url)->assertOk();
        }
        $this->get('/rewards')->assertSee('No points yet.');
        $this->get('/study-sessions')->assertSee('No upcoming sessions here yet');
    }

    public function test_peer_hosting_needs_membership_but_not_teaching_verification_and_converts_timezones(): void
    {
        $host = User::factory()->create(); $outsider = User::factory()->create(); $group = $this->group($host);
        $this->actingAs($outsider)->postJson('/study-sessions', $this->payload($group))->assertForbidden();
        $session = $this->createSession($host, $group);
        $this->assertSame('peer', $session->format);
        $this->assertDatabaseHas('study_participants', ['study_session_id' => $session->id, 'user_id' => $host->id]);
        $local = $session->starts_at->copy()->setTimezone('Asia/Taipei')->format('Y-m-d\TH:i');
        $this->assertSame($this->payload($group)['starts_at'], $local);
        $this->assertSame(60, (int) $session->starts_at->diffInMinutes($session->ends_at));
        $this->postJson('/study-sessions', $this->payload($group, ['duration' => 241]))->assertUnprocessable();
    }

    public function test_my_sessions_keeps_hosted_and_joined_sessions_after_they_start(): void
    {
        $this->withoutVite();
        $host = User::factory()->create(); $member = User::factory()->create(); $outsider = User::factory()->create();
        $session = $this->createSession($host, $this->group($host, [$member]));
        $this->actingAs($member)->post('/study-sessions/'.$session->id.'/join')->assertRedirect();
        $this->get('/study-sessions?filter=mine')->assertSee('Python practice');
        $this->travel(2)->days();
        $this->get('/study-sessions?filter=mine')->assertSee('Python practice')->assertSee('Awaiting attendance');
        $this->get('/study-sessions')->assertDontSee('Python practice');
        $this->actingAs($host)->get('/study-sessions?filter=mine')->assertSee('Python practice')->assertSee('Hosting');
        foreach (['completed', 'cancelled'] as $status) {
            $session->update(['status' => $status]);
            $this->get('/study-sessions?filter=mine')->assertDontSee('Python practice');
            $this->get('/study-sessions?filter=history')->assertSee('Python practice')->assertSee(ucfirst($status));
        }
        $this->actingAs($outsider)->get('/study-sessions?filter=mine')->assertDontSee('Python practice');
        $this->actingAs($host)->get('/progress')->assertSee('Back to Dashboard');
        $this->get('/dashboard')->assertSee(route('study-sessions.index'), false);
    }

    public function test_session_start_notifications_are_personal_and_clear_only_after_opening_a_started_session(): void
    {
        $this->withoutVite();
        $host = User::factory()->create(); $member = User::factory()->create(); $outsider = User::factory()->create();
        $session = $this->createSession($host, $this->group($host, [$member]));
        $this->actingAs($member)->post('/study-sessions/'.$session->id.'/join');
        $this->get('/study-sessions/'.$session->id)->assertOk();
        $this->getJson('/messages/unread-count')->assertOk()->assertJsonCount(0, 'session_notifications');
        $this->travelTo($session->starts_at);
        $this->getJson('/messages/unread-count')->assertJsonCount(1, 'session_notifications')->assertJsonPath('session_notifications.0.name', 'Python practice');
        $this->getJson('/messages/unread-count')->assertJsonCount(1, 'session_notifications');
        $this->actingAs($outsider)->getJson('/messages/unread-count')->assertJsonCount(0, 'session_notifications');
        $this->actingAs($member)->get('/study-sessions/'.$session->id)->assertOk();
        $this->getJson('/messages/unread-count')->assertJsonCount(0, 'session_notifications');
        $this->actingAs($host)->getJson('/messages/unread-count')->assertJsonCount(1, 'session_notifications');
        $session->update(['status' => 'cancelled']);
        $this->getJson('/messages/unread-count')->assertJsonCount(0, 'session_notifications');
    }

    public function test_tutor_requires_subject_approval_and_approval_is_rechecked_when_joining(): void
    {
        $host = User::factory()->create(); $member = User::factory()->create(); $group = $this->group($host, [$member]);
        $this->actingAs($host)->postJson('/study-sessions', $this->payload($group, ['format' => 'tutor']))->assertUnprocessable();
        $approval = UserSubjectQualification::create(['user_id' => $host->id, 'subject_name' => 'Coding', 'proof_file_path' => 'proof.pdf', 'status' => 'approved']);
        $session = $this->createSession($host, $group, ['format' => 'tutor']);
        $approval->update(['status' => 'rejected']);
        $this->actingAs($member)->postJson('/study-sessions/'.$session->id.'/join')->assertUnprocessable();
    }

    public function test_private_sessions_and_meeting_details_are_protected(): void
    {
        $this->withoutVite();
        $host = User::factory()->create(); $member = User::factory()->create(); $outsider = User::factory()->create();
        $private = $this->createSession($host, $this->group($host, [$member], 'private'));
        $this->actingAs($outsider)->get('/study-sessions/'.$private->id)->assertForbidden();
        $this->get('/study-sessions')->assertDontSee('Python practice');
        $this->actingAs($member)->get('/study-sessions/'.$private->id)->assertOk()->assertDontSee('secret-room-code');
        $this->post('/study-sessions/'.$private->id.'/join')->assertRedirect();
        $this->get('/study-sessions/'.$private->id)->assertSee('secret-room-code');
        $this->assertArrayNotHasKey('meeting_details', $private->toArray());
        $this->actingAs($outsider)->postJson('/study-sessions/'.$private->id.'/join')->assertForbidden();
    }

    public function test_capacity_duplicate_joins_conflicts_and_leaving_are_enforced(): void
    {
        $host = User::factory()->create(); $member = User::factory()->create(); $third = User::factory()->create();
        $group = $this->group($host, [$member, $third]);
        $session = $this->createSession($host, $group, ['capacity' => 2]);
        $this->actingAs($member)->post('/study-sessions/'.$session->id.'/join')->assertRedirect();
        $this->post('/study-sessions/'.$session->id.'/join')->assertRedirect();
        $this->assertSame(2, $session->participants()->count());
        $this->actingAs($third)->postJson('/study-sessions/'.$session->id.'/join')->assertStatus(409);
        // The member cannot host another session over their booking.
        $this->actingAs($member)->postJson('/study-sessions', $this->payload($group))->assertStatus(409);
        $this->delete('/study-sessions/'.$session->id.'/leave')->assertRedirect();
        $this->actingAs($third)->post('/study-sessions/'.$session->id.'/join')->assertRedirect();
        $this->actingAs($host)->deleteJson('/study-sessions/'.$session->id.'/leave')->assertForbidden();
    }

    public function test_attendance_requires_both_host_and_attendee_and_awards_only_once(): void
    {
        $host = User::factory()->create(); $member = User::factory()->create(); $other = User::factory()->create();
        $group = $this->group($host, [$member, $other]);
        $session = $this->createSession($host, $group);
        $this->actingAs($member)->post('/study-sessions/'.$session->id.'/join');
        $this->postJson('/study-sessions/'.$session->id.'/complete', ['attendees' => [$member->id]])->assertForbidden();
        $this->actingAs($host)->postJson('/study-sessions/'.$session->id.'/complete', ['attendees' => [$member->id]])->assertStatus(409);
        $this->travel(2)->days();
        $this->postJson('/study-sessions/'.$session->id.'/complete', ['attendees' => [$other->id]])->assertUnprocessable();
        $this->post('/study-sessions/'.$session->id.'/complete', ['attendees' => [$member->id]])->assertRedirect();
        $this->assertDatabaseCount('point_entries', 0);
        $this->postJson('/study-sessions/'.$session->id.'/confirm')->assertForbidden();
        $this->actingAs($member)->post('/study-sessions/'.$session->id.'/confirm')->assertRedirect();
        $this->post('/study-sessions/'.$session->id.'/confirm')->assertRedirect();
        $this->assertDatabaseCount('point_entries', 2);
        $this->assertSame(30, (int) DB::table('point_entries')->where('user_id', $host->id)->sum('points'));
        $this->withoutVite();
        $this->get('/progress')->assertOk()->assertViewHas('stats', fn ($stats) => $stats['learning'] === 1 && $stats['learning_minutes'] === 60);
        $this->get('/rewards')->assertOk()->assertSee('First Step');
        $this->get('/history')->assertOk()->assertSee('Participation confirmed');
    }

    public function test_cancelled_sessions_and_no_shows_do_not_award_points(): void
    {
        $host = User::factory()->create(); $member = User::factory()->create(); $group = $this->group($host, [$member]);
        $session = $this->createSession($host, $group);
        $this->actingAs($member)->post('/study-sessions/'.$session->id.'/join');
        $this->postJson('/study-sessions/'.$session->id.'/cancel')->assertForbidden();
        $this->actingAs($host)->post('/study-sessions/'.$session->id.'/cancel')->assertRedirect();
        $this->actingAs($member)->postJson('/study-sessions/'.$session->id.'/confirm')->assertStatus(409);
        $second = $this->createSession($host, $group);
        $this->actingAs($member)->post('/study-sessions/'.$second->id.'/join');
        $this->travel(2)->days();
        $this->actingAs($host)->post('/study-sessions/'.$second->id.'/complete', ['attendees' => []]);
        $this->actingAs($member)->postJson('/study-sessions/'.$second->id.'/confirm')->assertUnprocessable();
        $this->assertDatabaseCount('point_entries', 0);
    }

    public function test_rewards_spend_balance_once_and_cannot_be_equipped_without_ownership(): void
    {
        $user = User::factory()->create(); $this->actingAs($user);
        $this->putJson('/rewards/equip', ['reward_key' => 'forest_frame'])->assertForbidden();
        $this->postJson('/rewards/unlock', ['reward_key' => 'forest_frame'])->assertUnprocessable();
        DB::table('point_entries')->insert(['user_id' => $user->id, 'event_key' => 'test-funding', 'points' => 360, 'description' => 'Test fixture', 'created_at' => now(), 'updated_at' => now()]);
        $this->post('/rewards/unlock', ['reward_key' => 'forest_frame'])->assertRedirect();
        $this->post('/rewards/unlock', ['reward_key' => 'forest_frame'])->assertRedirect();
        $this->assertSame(160, (int) DB::table('point_entries')->where('user_id', $user->id)->sum('points'));
        $this->assertDatabaseCount('user_rewards', 1);
        $this->put('/rewards/equip', ['reward_key' => 'forest_frame'])->assertRedirect();
        $this->assertSame('profile-frame-forest', $user->fresh()->profile_frame_class);
        $this->postJson('/rewards/unlock', ['reward_key' => 'sunrise_frame'])->assertUnprocessable();
        $this->postJson('/rewards/unlock', ['reward_key' => 'admin_verification'])->assertUnprocessable();
        $this->assertNotSame('admin', $user->fresh()->role);
        $this->put('/rewards/equip', ['reward_key' => 'no_frame'])->assertRedirect();
        $this->assertSame('', $user->fresh()->profile_frame_class);
    }

    public function test_goals_are_private_and_validate_bounds(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->put('/progress/goals', ['learning' => 2, 'teaching' => 1, 'hours' => 6, 'timezone' => 'Asia/Taipei'])->assertRedirect();
        $this->assertSame(6, $user->fresh()->learning_goals['hours']);
        $this->assertArrayNotHasKey('learning_goals', $user->fresh()->toArray());
        $this->putJson('/progress/goals', ['learning' => 0, 'teaching' => 2, 'hours' => 100, 'timezone' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors(['learning', 'hours', 'timezone']);
    }
}
