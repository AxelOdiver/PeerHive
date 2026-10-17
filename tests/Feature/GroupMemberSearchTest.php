<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupMemberSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_requires_login_and_never_lists_everyone_for_a_blank_query(): void
    {
        $this->getJson('/messages/member-search?q=peer')->assertUnauthorized();
        $user = User::factory()->create(['username' => 'peer_self']);
        User::factory()->create(['username' => 'peer_other']);
        $this->actingAs($user)->getJson('/messages/member-search')->assertOk()->assertExactJson(['users' => []]);
        $this->getJson('/messages/member-search?q=pe')->assertExactJson(['users' => []]);
        $this->getJson('/messages/member-search?q=peer')->assertOk()->assertJsonCount(1, 'users')->assertJsonPath('users.0.username', 'peer_other')->assertJsonMissingPath('users.0.email');
        $this->getJson('/messages/member-search?q='.str_repeat('x', 255))->assertUnprocessable();
    }

    public function test_search_matches_full_email_and_username_prefix_and_treats_wildcards_literally(): void
    {
        $viewer = User::factory()->create();
        $match = User::factory()->create(['username' => 'study_one', 'email' => 'learner@example.com']);
        User::factory()->create(['username' => 'studyXone']);
        $this->actingAs($viewer)->getJson('/messages/member-search?q=LEARNER%40EXAMPLE.COM')->assertOk()->assertJsonPath('users.0.id', $match->id)->assertJsonCount(1, 'users');
        $this->getJson('/messages/member-search?q=learner%40example')->assertExactJson(['users' => []]);
        $this->getJson('/messages/member-search?q=study_')->assertJsonCount(1, 'users')->assertJsonPath('users.0.id', $match->id);
        $this->getJson('/messages/member-search?q=%25%25%25')->assertExactJson(['users' => []]);
    }

    public function test_search_is_bounded_and_selected_users_can_form_a_group(): void
    {
        $viewer = User::factory()->create();
        $peers = collect(range(1, 12))->map(fn ($i) => User::factory()->create(['username' => 'learner_'.$i]));
        $this->actingAs($viewer)->getJson('/messages/member-search?q=learner')->assertOk()->assertJsonCount(10, 'users');
        $this->postJson('/messages/conversations', ['is_group' => 1, 'name' => 'Study group', 'member_ids' => $peers->take(2)->pluck('id')->all()])->assertOk();
        $this->assertDatabaseCount('conversation_user', 3);
        $this->withoutVite();
        $html = $this->get('/messages')->assertOk()->getContent();
        $groupMarkup = substr($html, strpos($html, 'id="newGroupForm"'));
        $groupMarkup = substr($groupMarkup, 0, strpos($groupMarkup, '</form>'));
        $this->assertStringContainsString('groupSelectedMembers', $groupMarkup);
        $this->assertStringNotContainsString('group-member-checkbox', $groupMarkup);
        $this->assertStringNotContainsString('learner_1', $groupMarkup);
    }

    public function test_usernames_are_normalized_optional_unique_and_editable_by_the_owner(): void
    {
        $user = User::factory()->create(['username' => 'taken_name']);
        $other = User::factory()->create();
        $data = ['first_name' => $other->first_name, 'last_name' => $other->last_name, 'email' => $other->email];
        $this->actingAs($other)->putJson('/profile', $data + ['username' => 'TAKEN_NAME'])->assertUnprocessable()->assertJsonValidationErrors('username');
        $this->putJson('/profile', $data + ['username' => 'New_Name'])->assertOk()->assertJsonPath('user.username', 'new_name');
        $this->putJson('/profile', $data + ['username' => 'new_name'])->assertOk();
        $this->putJson('/profile', $data + ['username' => 'bad name!'])->assertUnprocessable();
        $this->putJson('/profile', $data + ['username' => null])->assertOk();
        $this->assertNull($other->fresh()->username);
        $registration = ['first_name' => 'New', 'last_name' => 'User', 'email' => 'signup@example.com', 'password' => 'StrongPassword1!', 'password_confirmation' => 'StrongPassword1!', 'accept_policies' => 1];
        auth()->logout();
        $this->postJson('/register', $registration + ['username' => 'TAKEN_NAME'])->assertUnprocessable()->assertJsonValidationErrors('username');
        $this->postJson('/register', $registration + ['username' => 'NewMember'])->assertOk();
        $this->assertDatabaseHas('users', ['email' => 'signup@example.com', 'username' => 'newmember']);
    }
}
