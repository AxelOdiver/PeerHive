<?php

namespace Tests\Feature;

use Tests\TestCase;

class PeerHiveLoginHighlightsCleanupTest extends TestCase
{
    public function test_login_removes_promotional_highlights_without_hiding_brand_or_legal_links(): void
    {
        $this->withoutVite();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('ph-auth-story')
            ->assertSee('Better learning')
            ->assertSee('Find your people, share what you know')
            ->assertSee('Your community for meaningful learning.')
            ->assertSee('ph-story-legal')
            ->assertSee('Terms and Conditions')
            ->assertSee('Privacy Policy')
            ->assertDontSee('ph-story-features')
            ->assertDontSee('Connect with student peers')
            ->assertDontSee('Swap skills and knowledge')
            ->assertDontSee('Grow with every session');
    }
}
