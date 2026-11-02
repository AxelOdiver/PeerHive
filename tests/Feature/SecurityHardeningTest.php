<?php

namespace Tests\Feature;

use App\Models\{User, Conversation, Message, Community, Post, CommunityInvite};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_preview_checks_membership_and_actual_file_content(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $chat = Conversation::create(['created_by' => $user->id, 'is_group' => false]);
        $chat->users()->attach($user->id);
        $message = Message::create(['sender_id' => $user->id, 'conversation_id' => $chat->id, 'attachment_path' => 'message_attachments/photo.png', 'attachment_name' => 'photo.png', 'attachment_type' => 'image/png']);
        Storage::disk('local')->put($message->attachment_path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII='));
        $url = route('private.message', $message).'?preview=1';
        $this->actingAs($user)->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Content-Disposition', 'inline');
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        Storage::disk('local')->put($message->attachment_path, '<svg onload="alert(1)"></svg>');
        $this->actingAs($user)->get($url)->assertNotFound();
    }

    public function test_proof_downloads_require_owner_or_admin_and_session_rewards_have_limits(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(); $other = User::factory()->create();
        $proof = \App\Models\UserSubjectQualification::create(['user_id' => $owner->id, 'subject_name' => 'Coding', 'status' => 'pending', 'proof_file_path' => 'qualifications/proof.pdf']);
        Storage::disk('local')->put($proof->proof_file_path, 'proof');
        $this->actingAs($other)->get(route('private.qualification', $proof))->assertForbidden();
        $this->actingAs($owner)->get(route('private.qualification', $proof))->assertOk();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('private.qualification', $proof))->assertOk();
        foreach ([1, 15, 15, 15, 15] as $minutes) {
            $session = \App\Models\StudySession::create(['host_id' => $owner->id, 'title' => 'Practice', 'subject' => 'Coding', 'format' => 'tutor', 'starts_at' => now()->subMinutes($minutes), 'ends_at' => now(), 'timezone' => 'UTC', 'capacity' => 2, 'meeting_method' => 'online', 'meeting_details' => 'room', 'status' => 'completed']);
            \App\Support\LearningHub::award($session, $owner->id);
        }
        $this->assertEquals(90, \Illuminate\Support\Facades\DB::table('point_entries')->where('user_id', $owner->id)->sum('points'));
        $this->assertDatabaseCount('point_entries', 3);
    }

    public function test_login_throttles_and_new_otps_are_hashed_with_attempt_limits(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        \Illuminate\Support\Facades\Cache::flush();
        $user = User::factory()->create();
        $this->postJson('/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
        $otp = \App\Models\LoginOtp::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('******', $otp->code);
        $this->assertNotEmpty($otp->code_hash);
        $this->assertFalse(\Illuminate\Support\Facades\Hash::check('not-a-code', $otp->code_hash));
        $otp->update(['attempts' => 5]);
        $this->withSession(['otp_user_id' => $user->id])->postJson('/otp', ['code' => '000000'])->assertStatus(429);
        for ($i = 0; $i < 5; $i++) $this->postJson('/login', ['email' => 'unknown@example.test', 'password' => 'bad']);
        $this->postJson('/login', ['email' => 'unknown@example.test', 'password' => 'bad'])->assertStatus(429);
        \Illuminate\Support\Facades\Cache::flush();
    }

    public function test_security_headers_and_search_email_privacy(): void
    {
        $this->withoutVite();
        $this->get('/login')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $user = User::factory()->create();
        User::factory()->create(['first_name' => 'SearchPeer']);
        $this->actingAs($user)->getJson('/users/search?q=SearchPeer')->assertOk()->assertJsonMissingPath('0.email');
        $this->getJson('/users/search?q=')->assertExactJson([]);
    }

    public function test_normal_users_cannot_manage_accounts(): void
    {
        $user = User::factory()->create(); $other = User::factory()->create();
        $this->actingAs($user)->getJson('/users/'.$other->id)->assertForbidden();
        $this->putJson('/users/'.$other->id, ['first_name' => 'Hijack'])->assertForbidden();
        $this->deleteJson('/users/'.$other->id)->assertForbidden();
        $this->postJson('/users', [])->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $other->id]);
    }

    public function test_private_attachments_reject_active_content_and_require_membership(): void
    {
        Storage::fake('local'); Storage::fake('public');
        $user = User::factory()->create(); $other = User::factory()->create();
        $chat = Conversation::create(['created_by' => $user->id, 'is_group' => false]);
        $chat->users()->attach([$user->id, $other->id]);
        $this->actingAs($user)->postJson('/messages/conversations/'.$chat->id, ['attachment' => UploadedFile::fake()->createWithContent('bad.html', '<script>alert(1)</script>')])->assertUnprocessable();
        $response = $this->postJson('/messages/conversations/'.$chat->id, ['attachment' => UploadedFile::fake()->createWithContent('notes.txt', 'study notes')])->assertOk();
        $message = Message::findOrFail($response->json('message.id'));
        Storage::disk('local')->assertExists($message->attachment_path);
        Storage::disk('public')->assertMissing($message->attachment_path);
        $this->get($response->json('message.attachment_url'))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs(User::factory()->create())->get($response->json('message.attachment_url'))->assertForbidden();
    }

    public function test_outsiders_cannot_post_comment_or_invite_into_private_communities(): void
    {
        $owner = User::factory()->create(); $outsider = User::factory()->create();
        $community = Community::create(['user_id' => $owner->id, 'name' => 'Private', 'subject' => 'Coding', 'member_limit' => 3, 'visibility' => 'private']);
        $post = Post::create(['user_id' => $owner->id, 'community_id' => $community->id, 'title' => 'Topic', 'body' => 'Hello']);
        $this->actingAs($outsider)->postJson('/community/'.$community->id.'/posts', ['title' => 'Bad', 'body' => 'Bad'])->assertForbidden();
        $this->postJson('/posts/'.$post->id.'/comments', ['body' => 'Bad'])->assertForbidden();
        $this->postJson('/invite', ['user_id' => $owner->id, 'community_id' => $community->id])->assertForbidden();
        $this->postJson('/reports', ['reportable_id' => $post->id, 'reportable_type' => Post::class, 'reason' => 'Spam'])->assertForbidden();
        $invite = CommunityInvite::create(['user_id' => $outsider->id, 'community_id' => $community->id, 'status' => 'accepted']);
        $this->postJson('/invites/'.$invite->id.'/accept')->assertStatus(409);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->deleteJson('/users/'.$admin->id)->assertUnprocessable();
    }
}
