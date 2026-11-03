<?php

namespace Tests\Feature;

use App\Models\{Community, Post, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunityPostMediaTest extends TestCase
{
    use RefreshDatabase;

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('project.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aZ1sAAAAASUVORK5CYII='
        ));
    }

    private function community(User $owner, string $visibility = 'public'): Community
    {
        return Community::create(['user_id' => $owner->id, 'name' => 'Media Club', 'subject' => 'Coding',
            'visibility' => $visibility, 'member_limit' => 5]);
    }

    public function test_member_can_post_image_without_body_and_delete_its_file(): void
    {
        Storage::fake('local');
        $this->withoutVite();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $community = $this->community($owner);
        $community->members()->attach($member);
        $this->actingAs($member)->postJson(route('community.posts.store', $community), [
            'title' => 'Our project', 'media' => $this->image(),
        ])->assertOk();
        $post = Post::firstOrFail();
        $this->assertSame('', $post->body);
        $this->assertSame('image/png', $post->media_mime);
        Storage::disk('local')->assertExists($post->media_path);
        $this->get(route('community.show', $community))->assertOk()->assertSee(route('posts.media', $post));
        $this->get(route('posts.media', $post))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->deleteJson(route('posts.destroy', $post))->assertOk();
        Storage::disk('local')->assertMissing($post->media_path);
    }

    public function test_private_video_is_only_accessible_to_members_and_managers(): void
    {
        Storage::fake('local');
        $this->withoutVite();
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $community = $this->community($owner, 'private');
        $this->actingAs($owner)->postJson(route('community.posts.store', $community), [
            'title' => 'Demo', 'body' => 'Watch this',
            'media' => UploadedFile::fake()->create('demo.mp4', 100, 'video/mp4'),
        ])->assertOk();
        $post = Post::firstOrFail();
        $url = route('posts.media', $post);
        $media = $this->get($url)->assertOk()->assertHeader('Content-Type', 'video/mp4')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('private', $media->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $media->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('public', $media->headers->get('Cache-Control'));
        $this->get(route('community.show', $community))->assertSee('<video', false)->assertSee($url);
        $this->actingAs($outsider)->get($url)->assertForbidden();
        $this->post(route('community.join', $community))->assertRedirect();
        $this->get($url)->assertForbidden();
        $community->members()->attach($outsider);
        $this->get($url)->assertOk();
        $community->members()->detach($outsider);
        $this->get($url)->assertForbidden();
        $community->update(['visibility' => 'public']);
        $this->get($url)->assertOk();
        $this->actingAs($owner)->deleteJson(route('community.destroy', $community))->assertOk();
        Storage::disk('local')->assertMissing($post->media_path);
    }

    public function test_upload_rejects_nonmembers_unsupported_files_and_oversized_files(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $community = $this->community($owner);
        $url = route('community.posts.store', $community);
        $this->actingAs($outsider)->postJson($url, [
            'title' => 'No', 'media' => $this->image(),
        ])->assertForbidden();
        $spoof = UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo "unsafe";');
        $this->actingAs($owner)->postJson($url, [
            'title' => 'Spoof', 'media' => new UploadedFile($spoof->getPathname(), 'photo.jpg', 'image/jpeg', null, true),
        ])->assertUnprocessable()->assertJsonValidationErrors('media');
        $this->postJson($url, [
            'title' => 'Too big', 'media' => UploadedFile::fake()->create('large.mp4', 20481, 'video/mp4'),
        ])->assertUnprocessable()->assertJsonValidationErrors('media');
        $this->postJson($url, ['title' => 'Empty'])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->assertDatabaseCount('posts', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
