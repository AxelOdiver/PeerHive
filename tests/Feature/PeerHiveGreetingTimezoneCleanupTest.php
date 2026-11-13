<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeerHiveGreetingTimezoneCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_onboarding_shows_welcome_only_for_first_dashboard_visit(): void
    {
        $this->withoutVite();
        $user = User::factory()->create([
            'first_name' => 'Casey',
            'onboarding_required' => true,
            'onboarding_completed_at' => null,
        ]);

        $this->actingAs($user)->post(route('onboarding.learn'), ['topics' => []])
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertOk()
            ->assertSeeText('Welcome to PeerHive, Casey!')
            ->assertDontSee('&#128075;', false)
            ->assertSeeText('Learn with your peers, share your skills, and keep growing.');

        $this->get(route('dashboard'))->assertOk()
            ->assertSeeText('Welcome back, Casey!')
            ->assertDontSee('&#128075;', false);
    }

    public function test_skipping_onboarding_also_welcomes_the_user_once(): void
    {
        $this->withoutVite();
        $user = User::factory()->create([
            'first_name' => 'Taylor',
            'onboarding_required' => true,
            'onboarding_completed_at' => null,
        ]);

        $this->actingAs($user)->post(route('onboarding.skip'), ['step' => 'learn'])
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertOk()->assertSeeText('Welcome to PeerHive, Taylor!');
        $this->get(route('dashboard'))->assertOk()->assertSeeText('Welcome back, Taylor!');
    }

    public function test_existing_user_sees_returning_greeting_without_emoji(): void
    {
        $this->withoutVite();
        $user = User::factory()->create([
            'first_name' => 'Alex',
            'onboarding_required' => false,
            'onboarding_completed_at' => now()->subDays(5),
        ]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSeeText('Welcome back, Alex!')
            ->assertDontSee('&#128075;', false);
    }

    public function test_schedule_removes_only_timezone_header_notice(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['learning_timezone' => 'Asia/Manila']);

        $this->actingAs($user)->get(route('schedule'))->assertOk()
            ->assertDontSee('ph-schedule-timezone-note', false)
            ->assertSee('ph-schedule-availability-header', false)
            ->assertSee('id="phAvailabilityTimezone"', false)
            ->assertSeeText('Your confirmed bookings')
            ->assertSeeText('Asia/Manila');

        $this->assertSame('Asia/Manila', $user->fresh()->learning_timezone);
    }
}
