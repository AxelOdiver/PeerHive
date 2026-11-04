<?php

namespace Tests\Feature;

use App\Models\{Community, CommunityBanner, Post, Report, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunityBannerTest extends TestCase
{
    use RefreshDatabase;

    private function group(User $owner): Community
    {
        $this->withoutVite();
        return Community::create(['user_id' => $owner->id, 'name' => 'Banner circle', 'subject' => 'Programming', 'visibility' => 'private', 'member_limit' => 20]);
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('banner.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII='));
    }

    private function upload(User $actor, Community $community, ?int $expected = null): CommunityBanner
    {
        $this->actingAs($actor)->postJson(route('community.banner.store', $community), ['banner' => $this->image(), 'expected_banner_id' => $expected])->assertOk();
        return $community->fresh()->banner;
    }

    private function reportBanner(User $reporter, CommunityBanner $banner): Report
    {
        $this->actingAs($reporter)->postJson(route('reports.store'), ['reportable_type' => CommunityBanner::class,
            'reportable_id' => $banner->id, 'reason' => 'Sexual or explicit content'])->assertOk();
        return Report::where('reportable_type', CommunityBanner::class)->where('reportable_id', $banner->id)->firstOrFail();
    }

    public function test_cards_open_the_community_and_only_inside_banners_open_the_shared_viewer(): void
    {
        Storage::fake('local'); Storage::fake('public');
        $owner = User::factory()->create(); $viewer = User::factory()->create();
        $community = $this->group($owner);
        $banner = $this->upload($owner, $community);
        Storage::disk('local')->assertExists($banner->path);
        Storage::disk('public')->assertMissing($banner->path);
        $this->assertArrayNotHasKey('path', $banner->toArray());
        $url = route('community.banner.show', $banner);
        $this->get(route('community.show', $community))->assertOk()->assertSee('Change banner');
        $page = $this->actingAs($viewer)->get(route('community'))->assertOk()->assertSee($url, false);
        $page->assertDontSee('data-community-banner-url', false)->assertDontSee('id="communityBannerViewer"', false);
        $inside = $this->get(route('community.show', $community))->assertOk()->assertSee($url, false)->assertDontSee('Change banner')->assertSee('data-community-banner-url', false)->assertSee('community-hero');
        $this->assertSame(1, substr_count($inside->getContent(), 'id="communityBannerViewer"'));
        $this->actingAs($owner)->get('/dashboard')->assertOk()->assertSee($url, false)->assertDontSee('data-community-banner-url', false);
        $image = $this->actingAs($viewer)->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('private', $image->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $image->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', $image->headers->get('Cache-Control'));
        auth()->logout();
        $this->get($url)->assertRedirect('/login');
    }

    public function test_only_managers_can_change_banners_and_invalid_uploads_save_nothing(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(); $member = User::factory()->create();
        $community = $this->group($owner); $community->members()->attach($member);
        $url = route('community.banner.store', $community);
        $this->actingAs($member)->postJson($url, ['banner' => $this->image(), 'expected_banner_id' => null])->assertForbidden();
        $this->deleteJson(route('community.banner.destroy', $community), ['expected_banner_id' => 1])->assertForbidden();
        $this->actingAs($owner)->postJson($url, ['banner' => UploadedFile::fake()->createWithContent('image.svg', '<svg onload="alert(1)"></svg>'), 'expected_banner_id' => null])->assertUnprocessable();
        $this->postJson($url, ['banner' => UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo "unsafe";'), 'expected_banner_id' => null])->assertUnprocessable();
        $this->postJson($url, ['banner' => UploadedFile::fake()->create('large.png', 5121, 'image/png'), 'expected_banner_id' => null])->assertUnprocessable();
        $normalImage = $this->image();
        $bytes = file_get_contents($normalImage->getPathname());
        $wide = UploadedFile::fake()->createWithContent('wide.png', substr_replace($bytes, pack('N', 6001), 16, 4));
        $this->postJson($url, ['banner' => $wide, 'expected_banner_id' => null])->assertUnprocessable();
        $this->assertDatabaseCount('community_banners', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $admin = User::factory()->create(['role' => 'admin']);
        $this->upload($admin, $community);
    }

    public function test_stale_updates_do_not_replace_newer_banners_or_leave_unreferenced_uploads(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(); $community = $this->group($owner);
        $first = $this->upload($owner, $community);
        $second = $this->upload($owner, $community, $first->id);
        $this->assertNotNull($first->fresh()->removed_at);
        $this->get(route('community.banner.show', $first))->assertNotFound();
        $this->postJson(route('community.banner.store', $community), ['banner' => $this->image(), 'expected_banner_id' => $first->id])->assertStatus(409);
        $this->deleteJson(route('community.banner.destroy', $community), ['expected_banner_id' => $first->id])->assertStatus(409);
        $this->assertSame($second->id, $community->fresh()->banner->id);
        $this->assertCount(2, Storage::disk('local')->allFiles());
        $this->deleteJson(route('community.banner.destroy', $community), ['expected_banner_id' => $second->id])->assertOk();
        $this->assertNull($community->fresh()->banner);
        $this->get(route('community.show', $community))->assertOk()->assertSee('Upload banner');
        $this->get(route('community.banner.show', $second))->assertNotFound();
    }

    public function test_positions_are_saved_and_existing_banners_can_be_repositioned_without_reuploading(): void
    {
        // Exercise several validation cases without exhausting the upload rate limit.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Storage::fake('local');
        $owner = User::factory()->create(); $community = $this->group($owner);
        $url = route('community.banner.store', $community);
        $this->actingAs($owner)->postJson($url, ['expected_banner_id' => null, 'position_x' => 50, 'position_y' => 50])->assertUnprocessable();
        $this->postJson($url, ['banner' => $this->image(), 'expected_banner_id' => null, 'position_x' => 25.15, 'position_y' => 0, 'zoom' => 1.75])->assertOk();
        $banner = $community->fresh()->banner;
        $this->assertEquals(25.15, $banner->position_x);
        $this->assertEquals(0, $banner->position_y);
        $this->assertEquals(1.75, $banner->zoom);
        $this->postJson($url, ['expected_banner_id' => $banner->id, 'position_x' => 100, 'position_y' => 72.5, 'zoom' => 2.5])->assertOk();
        $this->assertSame($banner->id, $community->fresh()->banner->id);
        $this->assertEquals(100, $banner->fresh()->position_x);
        $this->assertEquals(72.5, $banner->fresh()->position_y);
        $this->assertEquals(2.5, $banner->fresh()->zoom);
        $this->assertDatabaseCount('community_banners', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->get(route('community.show', $community))->assertOk()->assertSee('--banner-x: 100%; --banner-y: 72.5%; --banner-zoom: 2.5', false)->assertSee('data-current-banner-zoom="2.5"', false)->assertDontSee('Horizontal position')->assertDontSee('Vertical position')->assertSee('Zoom');
        foreach ([['position_x' => -1], ['position_y' => 101], ['position_x' => 'bad'], ['position_y' => null], ['zoom' => 0.99], ['zoom' => 3.01], ['zoom' => 'bad'], ['zoom' => null]] as $invalid) {
            $this->postJson($url, $invalid + ['expected_banner_id' => $banner->id])->assertUnprocessable();
        }
        $this->actingAs(User::factory()->create())->postJson($url, ['expected_banner_id' => $banner->id, 'position_x' => 10])->assertForbidden();
        $this->assertEquals(100, $banner->fresh()->position_x);
        $replacement = $this->upload($owner, $community, $banner->id);
        $this->assertEquals(50, $replacement->position_x);
        $this->assertEquals(50, $replacement->position_y);
        $this->assertEquals(1, $replacement->zoom);
        $this->postJson($url, ['expected_banner_id' => $banner->id, 'position_x' => 5])->assertStatus(409);
        $this->assertEquals(50, $replacement->fresh()->position_x);
    }

    public function test_members_can_report_private_community_preview_images_and_admins_remove_only_the_banner(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(); $viewer = User::factory()->create();
        $community = $this->group($owner); $banner = $this->upload($owner, $community);
        $post = Post::create(['community_id' => $community->id, 'user_id' => $owner->id, 'title' => 'Keep this discussion', 'body' => 'Keep this text']);
        $payload = ['reportable_type' => CommunityBanner::class, 'reportable_id' => $banner->id, 'reason' => 'Inappropriate'];
        $this->postJson('/reports', $payload)->assertUnprocessable();
        $report = $this->reportBanner($viewer, $banner);
        $this->postJson('/reports', $payload)->assertUnprocessable();
        $this->delete(route('admin.reports.destroyContent', $report))->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.reports.index'))->assertOk()->assertSee('Community banner')->assertSee('View reported image')->assertSee('Remove banner');
        $this->delete(route('admin.reports.destroyContent', $report))->assertRedirect();
        $this->assertSame('resolved', $report->fresh()->status);
        $this->assertNotNull($banner->fresh()->removed_at);
        $this->assertDatabaseHas('communities', ['id' => $community->id]);
        $this->assertDatabaseHas('posts', ['id' => $post->id]);
        $this->get(route('community.banner.show', $banner))->assertOk();
        $this->actingAs($viewer)->get(route('community.banner.show', $banner))->assertNotFound();
    }

    public function test_reviewing_an_old_report_preserves_a_replacement_and_a_new_image_can_be_reported_separately(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(); $viewer = User::factory()->create();
        $community = $this->group($owner); $first = $this->upload($owner, $community);
        $report = $this->reportBanner($viewer, $first);
        $second = $this->upload($owner, $community, $first->id);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('community.banner.show', $first))->assertOk();
        $this->delete(route('admin.reports.destroyContent', $report))->assertRedirect();
        $this->assertSame($second->id, $community->fresh()->banner->id);
        $this->delete(route('admin.reports.destroyContent', $report))->assertStatus(409);
        $this->actingAs($viewer)->get(route('community.banner.show', $second))->assertOk();
        $this->postJson('/reports', ['reportable_type' => CommunityBanner::class, 'reportable_id' => $first->id, 'reason' => 'Old image'])->assertStatus(409);
        $this->reportBanner($viewer, $second);
        $this->assertDatabaseCount('reports', 2);
    }

    public function test_dismissing_a_report_keeps_the_banner_visible(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(); $community = $this->group($owner); $banner = $this->upload($owner, $community);
        $report = $this->reportBanner(User::factory()->create(), $banner);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->patch(route('admin.reports.dismiss', $report))->assertRedirect();
        $this->assertSame('dismissed', $report->fresh()->status);
        $this->assertSame($banner->id, $community->fresh()->banner->id);
    }

    public function test_community_deletion_cleans_up_banner_versions_and_missing_or_changed_files_are_not_served(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(); $community = $this->group($owner);
        $first = $this->upload($owner, $community); $second = $this->upload($owner, $community, $first->id);
        Storage::disk('local')->put($second->path, '<svg onload="alert(1)"></svg>');
        $this->get(route('community.banner.show', $second))->assertNotFound();
        Storage::disk('local')->delete($second->path);
        $this->get(route('community.banner.show', $second))->assertNotFound();
        $this->deleteJson(route('community.destroy', $community))->assertOk();
        $this->assertDatabaseCount('community_banners', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
