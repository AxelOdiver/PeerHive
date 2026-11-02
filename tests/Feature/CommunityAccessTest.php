<?php

namespace Tests\Feature;

use App\Models\{Community, CommunityInvite, CommunityJoinRequest, Post, StudySession, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityAccessTest extends TestCase
{
    use RefreshDatabase;

    private function community(User $owner, string $visibility = 'private', int $limit = 5): Community
    {
        return Community::create(['user_id' => $owner->id, 'name' => 'Study Circle', 'description' => 'Learn together',
            'subject' => 'Coding', 'visibility' => $visibility, 'member_limit' => $limit]);
    }

    public function test_private_preview_hides_content_until_owner_approves(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create(); $user = User::factory()->create();
        $community = $this->community($owner);
        Post::create(['community_id' => $community->id, 'user_id' => $owner->id, 'title' => 'Private post title', 'body' => 'Private post body']);
        $url = route('community.show', $community);
        $this->actingAs($user)->get('/community')->assertOk()->assertSee($url)
            ->assertSee('stretched-link', false)->assertSee('Join Community')->assertDontSee('View Community');
        $this->get($url)->assertOk()->assertSee('Learn together')->assertSee('Request to join')->assertDontSee('Private post title')->assertDontSee('Private post body')->assertDontSee('createPostForm');
        $this->post(route('community.join', $community))->assertRedirect($url);
        $this->post(route('community.join', $community))->assertRedirect($url);
        $this->assertDatabaseCount('community_join_requests', 1);
        $this->assertFalse($community->members()->where('users.id', $user->id)->exists());
        $this->get($url)->assertSee('awaiting approval')->assertDontSee('Private post body');
        $join = CommunityJoinRequest::firstOrFail();
        $review = route('community.join.respond', [$community, $join]);
        $this->post($review, ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($owner)->get($url)->assertOk()->assertSee('Join requests (1)')->assertSee('Approve');
        $this->post($review, ['decision' => 'approved'])->assertRedirect();
        $this->post($review, ['decision' => 'approved'])->assertStatus(409);
        $this->assertTrue($community->members()->where('users.id', $user->id)->exists());
        $this->actingAs($user)->get($url)->assertOk()->assertSee('Private post body');
        $this->assertDatabaseHas('bell_notifications', ['user_id' => $owner->id, 'kind' => 'community']);
        $this->assertDatabaseHas('bell_notifications', ['user_id' => $user->id, 'body' => 'Your join request was approved.']);
    }

    public function test_decline_cancel_and_resubmit_do_not_grant_membership(): void
    {
        $owner = User::factory()->create(); $user = User::factory()->create();
        $community = $this->community($owner);
        $this->actingAs($user)->post(route('community.join', $community));
        $join = CommunityJoinRequest::firstOrFail();
        $this->actingAs($owner)->post(route('community.join.respond', [$community, $join]), ['decision' => 'declined'])->assertRedirect();
        $this->assertSame('declined', $join->fresh()->status);
        $this->actingAs($user)->post(route('community.join', $community))->assertRedirect();
        $this->assertSame('pending', $join->fresh()->status);
        $this->delete(route('community.join.cancel', $community))->assertRedirect();
        $this->assertSame('cancelled', $join->fresh()->status);
        $this->actingAs($owner)->post(route('community.join.respond', [$community, $join]), ['decision' => 'approved'])->assertStatus(409);
        $this->assertFalse($community->members()->where('users.id', $user->id)->exists());
        $otherCommunity = $this->community($owner);
        $this->post(route('community.join.respond', [$otherCommunity, $join]), ['decision' => 'approved'])->assertNotFound();
    }

    public function test_approval_and_invitation_respect_capacity_and_resolve_pending_requests(): void
    {
        $owner = User::factory()->create(); $user = User::factory()->create();
        $community = $this->community($owner, 'private', 2);
        $this->actingAs($user)->post(route('community.join', $community));
        $join = CommunityJoinRequest::firstOrFail();
        $invite = CommunityInvite::create(['community_id' => $community->id, 'user_id' => $user->id, 'status' => 'pending']);
        $extra = User::factory()->create(); $community->members()->attach($extra);
        $this->actingAs($owner)->post(route('community.join.respond', [$community, $join]), ['decision' => 'approved'])->assertStatus(409);
        $this->actingAs($user)->post(route('community.invite.accept', $invite))->assertStatus(409);
        $this->assertSame('pending', $join->fresh()->status);
        $community->members()->detach($extra);
        $this->post(route('community.invite.accept', $invite))->assertRedirect(route('community.show', $community));
        $this->assertSame('approved', $join->fresh()->status);
        $this->assertSame('accepted', $invite->fresh()->status);
        $this->assertSame(2, $community->memberCount());
    }

    public function test_public_community_is_readable_but_requires_joining_to_post(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create(); $user = User::factory()->create();
        $community = $this->community($owner, 'public');
        $post = Post::create(['community_id' => $community->id, 'user_id' => $owner->id, 'title' => 'Public discussion', 'body' => 'Public content']);
        $this->actingAs($user)->get(route('community.show', $community))->assertOk()->assertSee('Public content')->assertSee('Join Community')->assertDontSee('placeholder="Write a comment..."', false)->assertDontSee('placeholder="Create a new post..."', false);
        $this->postJson('/community/'.$community->id.'/posts', ['title' => 'No membership', 'body' => 'No'])->assertForbidden();
        $this->postJson('/posts/'.$post->id.'/comments', ['body' => 'No'])->assertForbidden();
        $this->post(route('community.join', $community))->assertRedirect();
        $this->assertTrue($community->members()->where('users.id', $user->id)->exists());
        $this->assertDatabaseCount('community_join_requests', 0);
        $this->postJson('/community/'.$community->id.'/posts', ['title' => 'Member discussion', 'body' => 'Allowed'])->assertOk();
    }

    public function test_private_session_details_stay_hidden_while_request_is_pending(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create(); $user = User::factory()->create();
        $community = $this->community($owner);
        $session = StudySession::create(['community_id' => $community->id, 'host_id' => $owner->id, 'title' => 'Secret study session', 'subject' => 'Coding', 'format' => 'peer', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'timezone' => 'UTC', 'capacity' => 3, 'meeting_method' => 'online', 'meeting_details' => 'Secret meeting link', 'status' => 'scheduled']);
        $this->actingAs($user)->post(route('community.join', $community));
        $this->get(route('community.show', $community))->assertDontSee('Secret study session')->assertDontSee('Secret meeting link');
        $this->get(route('study-sessions.index', ['community' => $community->id]))->assertOk()->assertDontSee('Secret study session');
        $this->get(route('study-sessions.show', $session))->assertForbidden();
    }
}
