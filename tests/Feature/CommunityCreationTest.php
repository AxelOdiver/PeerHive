<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_community_creation_accepts_the_graphic_design_label(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/community', [
            'name' => 'Design Circle',
            'subject' => 'Graphic Design',
            'member_limit' => 10,
            'visibility' => 'public',
        ])->assertCreated()->assertJsonPath('community.subject', 'Graphic Designing');
    }
}
