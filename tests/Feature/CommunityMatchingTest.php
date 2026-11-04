<?php

namespace Tests\Feature;

use App\Models\{CatalogEntry, Community, Post, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityMatchingTest extends TestCase
{
    use RefreshDatabase;

    private function group(User $owner, string $name, string $subject): Community
    {
        return Community::create(['user_id' => $owner->id, 'name' => $name, 'subject' => $subject,
            'visibility' => 'private', 'member_limit' => 20]);
    }

    public function test_a_catalog_topic_matches_a_community_subject_without_exposing_private_posts(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $interests = ['  pyTHON   basics  '];
        $user->forceFill(['learning_interests' => $interests])->save();
        $this->group($user, 'Owned design fallback', 'Design');
        $this->group($owner, 'Older matching group', 'Programming');
        $match = $this->group($owner, 'Discover coding peers', ' Coding ');
        $this->group($owner, 'Unrelated latest group', 'Languages');
        Post::create(['community_id' => $match->id, 'user_id' => $owner->id, 'title' => 'Private members discussion', 'body' => 'Hidden private content']);

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertViewHas('communitySuggestion', fn ($suggestion) => $suggestion['community']?->id === $match->id && $suggestion['matches_interests'])
            ->assertSee('Discover coding peers')->assertSee('Matched to your learning interests.')
            ->assertDontSee('Owned design fallback')->assertDontSee('Older matching group')->assertDontSee('Unrelated latest group')
            ->assertDontSee('Private members discussion')->assertDontSee('Hidden private content');
        $this->get('/community/'.$match->id)->assertOk()->assertSee('Request to join')->assertDontSee('Hidden private content');
        $this->assertSame($interests, $user->fresh()->learning_interests);
    }

    public function test_newly_approved_topics_follow_their_subject_and_pending_or_rejected_entries_do_not_expand_matches(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $owner = User::factory()->create();
        $subject = CatalogEntry::create(['kind' => 'subject', 'name' => 'Économie appliquée', 'name_key' => CatalogEntry::key('Économie appliquée')]);
        $subject->forceFill(['status' => 'approved'])->save();
        $topic = CatalogEntry::create(['kind' => 'topic', 'name' => 'Economic modelling', 'name_key' => CatalogEntry::key('Economic modelling'), 'parent_id' => $subject->id]);
        $fallback = $this->group($user, 'My existing circle', 'Design');
        $match = $this->group($owner, 'Economics circle', ' ÉCONOMIE   APPLIQUÉE ');
        $user->forceFill(['learning_interests' => ['Economic modelling']])->save();

        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertViewHas('communitySuggestion', fn ($suggestion) => $suggestion['community']?->id === $fallback->id && !$suggestion['matches_interests']);
        $topic->forceFill(['status' => 'approved'])->save();
        $this->get('/dashboard')->assertOk()
            ->assertViewHas('communitySuggestion', fn ($suggestion) => $suggestion['community']?->id === $match->id && $suggestion['matches_interests']);
        $subject->forceFill(['status' => 'rejected'])->save();
        $this->get('/dashboard')->assertOk()
            ->assertViewHas('communitySuggestion', fn ($suggestion) => $suggestion['community']?->id === $fallback->id && !$suggestion['matches_interests']);
    }

    public function test_no_interest_match_or_membership_shows_an_empty_state_without_a_random_community(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $user->forceFill(['learning_interests' => ['Python Basics']])->save();
        $this->group(User::factory()->create(), 'Unrelated community', 'Languages');
        $this->actingAs($user)->get('/dashboard')->assertOk()
            ->assertViewHas('communitySuggestion', fn ($suggestion) => $suggestion['community'] === null && !$suggestion['matches_interests'])
            ->assertSee('No matching or joined communities yet.')->assertDontSee('Unrelated community');
    }
}
