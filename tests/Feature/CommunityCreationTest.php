<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\CatalogEntry;
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
        ])->assertCreated()->assertJsonPath('community.subject', 'Design');
    }

    public function test_creation_uses_approved_catalog_subjects(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $subject = CatalogEntry::create(['kind' => 'subject', 'name' => 'Mathematics', 'name_key' => 'mathematics']);
        $subject->forceFill(['status' => 'approved'])->save();
        CatalogEntry::create(['kind' => 'subject', 'name' => 'Pending Subject', 'name_key' => 'pending subject']);
        $this->actingAs($user)->get('/community')->assertOk()
            ->assertSee('Mathematics')->assertSee('Databases')->assertSee('Spreadsheets')
            ->assertDontSee('Pending Subject')->assertDontSee('Existing teaching subjects')
            ->assertDontSee('value="Coding"', false);
        foreach (['Programming', 'Databases', 'Spreadsheets', 'Languages', 'Design', 'Mathematics'] as $name) {
            $this->postJson('/community', ['name' => 'Study group', 'subject' => $name,
                'member_limit' => 10, 'visibility' => 'public'])
                ->assertCreated()->assertJsonPath('community.subject', $name);
        }
        foreach (['Pending Subject', 'Unknown Subject', 'Python Basics', 'BSIT', 'Existing teaching subjects'] as $name) {
            $this->postJson('/community', ['name' => 'Study group', 'subject' => $name,
                'member_limit' => 10, 'visibility' => 'public'])
                ->assertUnprocessable()->assertJsonValidationErrors('subject');
        }
    }
}
