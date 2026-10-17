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
        $this->assertSame(asset('storage/'.$user->profile_picture), $user->profile_picture_url);
        $this->assertStringNotContainsString('wrong-host', $user->profile_picture_url);
        $this->getJson('/profile/show')->assertJsonPath('user.profile_picture_url', $user->profile_picture_url);
    }
}
