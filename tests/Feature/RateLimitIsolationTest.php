<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_password_reset_have_independent_limits(): void
    {
        for ($i = 0; $i < 5; $i++) $this->postJson('/login', [])->assertUnprocessable();
        $this->postJson('/login', [])->assertStatus(429);
        for ($i = 0; $i < 5; $i++) $this->postJson('/forgot-password', [])->assertUnprocessable();
        $this->postJson('/forgot-password', [])->assertStatus(429);
        $this->postJson('/login', [])->assertStatus(429);
    }

    public function test_chat_heartbeats_do_not_consume_qualification_or_member_search_limits(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        for ($i = 0; $i < 6; $i++) $this->postJson('/presence/heartbeat')->assertNoContent();
        for ($i = 0; $i < 5; $i++) $this->postJson('/apply-to-teach', [])->assertUnprocessable();
        $this->postJson('/apply-to-teach', [])->assertStatus(429);
        $this->postJson('/presence/heartbeat')->assertStatus(429);
        $this->getJson('/messages/member-search?q=peer')->assertOk();
    }
}
