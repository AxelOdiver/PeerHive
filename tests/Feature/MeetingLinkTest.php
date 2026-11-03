<?php

namespace Tests\Feature;

use App\Rules\OnlineMeetingLink;
use App\Support\MeetingLink;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MeetingLinkTest extends TestCase
{
    public function test_supported_links_render_in_a_protected_new_tab(): void
    {
        foreach (['https://meet.google.com/abc-defg-hij', 'https://us02web.zoom.us/j/123?pwd=secret',
            'https://teams.microsoft.com/l/meetup-join/abc', 'https://teams.live.com/meet/123',
            'https://teams.cloud.microsoft/meet/123'] as $url) {
            $this->assertSame($url, MeetingLink::url($url));
            $this->assertFalse(Validator::make(['link' => $url], ['link' => new OnlineMeetingLink(true)])->fails());
            $this->blade('<x-meeting-details method="online" :details="$url" />', compact('url'))
                ->assertSee('href="'.e($url).'"', false)->assertSee('target="_blank"', false)
                ->assertSee('rel="noopener noreferrer"', false);
        }
    }

    public function test_unsafe_and_unsupported_links_are_rejected_and_not_clickable(): void
    {
        foreach (['javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', 'http://meet.google.com/abc',
            'https://meet.google.com.evil.example/abc', 'https://evilzoom.us/j/123',
            'https://meet.google.com@evil.example/', 'https://user@meet.google.com/abc',
            'https://meet.google.com:8080/abc', 'https://127.0.0.1/', 'https://bit.ly/meeting',
            'https://evil.example/', '//meet.google.com/abc', 'https://meet.google.com\\@evil.example/',
            '<img src=x onerror=alert(1)>', 'old meeting code'] as $url) {
            $this->assertNull(MeetingLink::url($url), $url);
            $this->assertTrue(Validator::make(['link' => $url], ['link' => new OnlineMeetingLink(true)])->fails(), $url);
            $this->blade('<x-meeting-details method="online" :details="$url" />', compact('url'))
                ->assertDontSee('href=', false)->assertDontSee('<img', false)->assertSee('not supported');
        }
    }

    public function test_in_person_venues_remain_plain_text(): void
    {
        $this->assertFalse(Validator::make(['link' => 'Library room 2'], ['link' => new OnlineMeetingLink(false)])->fails());
        $this->blade('<x-meeting-details method="in_person" details="Library room 2" />')
            ->assertSee('Library room 2')->assertDontSee('href=', false)->assertDontSee('not supported');
    }
}
