<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnProfileNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_own_profile_has_reviews_and_edit_link_without_self_actions(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('users.profile', $user))->assertOk()
            ->assertSee('Learner reviews')->assertSee('No learner reviews yet.')
            ->assertSee('Edit Profile')->assertSee('href="'.route('profile').'"', false)
            ->assertDontSee('like-profile-btn')->assertDontSee('fav-profile-btn')->assertDontSee('open-swap-modal');
        $this->get(route('profile'))->assertOk()->assertSee('View my profile')
            ->assertSee('href="'.route('users.profile', $user).'"', false);
        $other = User::factory()->create();
        $this->get(route('users.profile', $other))->assertOk()
            ->assertSee('like-profile-btn')->assertSee('fav-profile-btn')->assertSee('open-swap-modal')
            ->assertDontSee('>Edit Profile</a>', false);
    }
}
