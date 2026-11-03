<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePictureTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_picture_uses_profile_fallback_instead_of_a_broken_url(): void
    {
        Storage::fake('public');
        $this->withoutVite();
        $user = User::factory()->create(['profile_picture' => 'profile_pictures/missing.png']);
        $this->assertNull($user->profile_picture_url);
        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('images/profile-placeholder.jpeg')->assertDontSee('storage/profile_pictures/missing.png');
        $this->get('/users/'.$user->id.'/profile')->assertOk()->assertDontSee('storage/profile_pictures/missing.png');
    }

    public function test_uploaded_picture_is_stored_publicly_and_returned_on_the_current_origin(): void
    {
        Storage::fake('public');
        config(['filesystems.default' => 'local', 'filesystems.disks.public.url' => 'http://wrong-host/storage']);
        $user = User::factory()->create();
        $this->actingAs($user)->post('/profile', [
            '_method' => 'PUT', 'first_name' => $user->first_name, 'last_name' => $user->last_name,
            'email' => $user->email, 'profile_picture' => UploadedFile::fake()->createWithContent('avatar.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=')),
        ], ['Accept' => 'application/json'])->assertOk();
        $user->refresh();
        Storage::disk('public')->assertExists($user->profile_picture);
        $this->assertSame(route('profile-picture.show', ['filename'=>basename($user->profile_picture)]), $user->profile_picture_url);
        $this->assertStringNotContainsString('wrong-host', $user->profile_picture_url);
        $this->getJson('/profile/show')->assertJsonPath('user.profile_picture_url', $user->profile_picture_url);
    }

    public function test_profile_images_have_browser_cache_headers_and_support_conditional_requests(): void
    {
        Storage::fake('public');
        $image=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=');
        Storage::disk('public')->put('profile_pictures/avatar.png',$image);
        $url=route('profile-picture.show',['filename'=>'avatar.png']);
        $response=$this->get($url)->assertOk()->assertHeader('Content-Type','image/png')->assertHeader('X-Content-Type-Options','nosniff');
        $this->assertStringContainsString('private',$response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=86400',$response->headers->get('Cache-Control'));
        $this->assertStringContainsString('immutable',$response->headers->get('Cache-Control'));
        $etag=$response->headers->get('ETag');
        $this->assertNotEmpty($etag);
        $this->get($url,['If-None-Match'=>$etag])->assertStatus(304);
        $this->get(route('profile-picture.show',['filename'=>'default']))->assertOk()->assertHeader('Content-Type','image/jpeg');
        Storage::disk('public')->put('profile_pictures/not-an-image.png','<svg onload="alert(1)"></svg>');
        $this->get(route('profile-picture.show',['filename'=>'not-an-image.png']))->assertNotFound();
        $this->get(route('profile-picture.show',['filename'=>'missing.png']))->assertNotFound();
        $this->get('/profile-pictures/..%2Fqualifications%2Fproof.pdf')->assertNotFound();
    }

    public function test_replacing_and_removing_a_photo_do_not_reuse_its_cached_url(): void
    {
        Storage::fake('public');
        $this->withoutVite();
        $user=User::factory()->create();
        $image=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=');
        $upload=function()use($user,$image){
            $this->actingAs($user)->putJson('/profile',['first_name'=>$user->first_name,'last_name'=>$user->last_name,'email'=>$user->email,'profile_picture'=>UploadedFile::fake()->createWithContent('avatar.png',$image)])->assertOk();
            return $user->fresh()->profile_picture_url;
        };
        $first=$upload();$second=$upload();
        $this->assertNotSame($first,$second);
        $page=$this->get('/dashboard')->assertOk()->assertSee($second);
        $page->assertSee('rel="preload" as="image"',false)->assertSee('decoding="sync"',false);
        $this->delete('/profile/picture')->assertRedirect();
        $this->assertNull($user->fresh()->profile_picture_url);
        $this->get($second)->assertNotFound();
        $this->get('/dashboard')->assertOk()->assertSee(route('profile-picture.show',['filename'=>'default']));
    }
}
