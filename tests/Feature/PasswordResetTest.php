<?php

namespace Tests\Feature;

use App\Models\LoginOtp;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_accepts_a_missing_middle_name(): void
    {
        $this->postJson('/register', [
            'first_name' => 'Test', 'last_name' => 'Member', 'email' => 'registration@example.com',
            'password' => 'NewPassword1!', 'password_confirmation' => 'NewPassword1!',
        ])->assertOk()->assertJsonPath('redirect', route('login'));
        $this->assertDatabaseHas('users', ['email' => 'registration@example.com', 'middle_name' => null]);
    }

    public function test_auth_pages_and_reset_link_are_available(): void
    {
        $this->withoutVite();
        $this->get('/login')->assertOk()->assertSee('Welcome back')->assertSee(route('password.request'));
        $this->get('/register')->assertOk()->assertSee('Join PeerHive')->assertSee('At least 8 characters');
        $this->get('/forgot-password')->assertOk()->assertSee('Send reset link');
        $this->get('/reset-password/example?email=test@example.com')->assertOk()->assertSee('test@example.com');
    }

    public function test_reset_email_is_sent_and_unknown_addresses_get_the_same_response(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $response = $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email]);
        $response->assertRedirect('/forgot-password')->assertSessionHas('status');
        $message = session('status');
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $this->assertTrue(Password::tokenExists($user, $notification->token));
            $this->assertStringContainsString('/reset-password/', $notification->toMail($user)->actionUrl);
            return true;
        });
        $this->post('/forgot-password', ['email' => 'unknown@example.com'])->assertSessionHas('status', $message);
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHas('status', $message);
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_valid_token_resets_password_revokes_device_trust_and_cannot_be_reused(): void
    {
        $user = User::factory()->create();
        TrustedDevice::create(['user_id' => $user->id, 'token' => 'trusted-token', 'device_name' => 'Test', 'last_used_at' => now()]);
        LoginOtp::create(['user_id' => $user->id, 'code' => '123456', 'expires_at' => now()->addMinutes(10)]);
        $data = ['email' => $user->email, 'token' => Password::createToken($user), 'password' => 'NewPassword1!', 'password_confirmation' => 'NewPassword1!'];
        $this->post('/reset-password', $data)->assertRedirect('/login')->assertSessionHas('status');
        $this->assertTrue(Hash::check('NewPassword1!', $user->fresh()->password));
        $this->assertDatabaseMissing('trusted_devices', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('login_otps', ['user_id' => $user->id]);
        $this->assertGuest();
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
    }

    public function test_invalid_expired_and_wrong_account_tokens_cannot_reset_password(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $original = $user->password;
        $data = ['email' => $user->email, 'token' => 'invalid', 'password' => 'NewPassword1!', 'password_confirmation' => 'NewPassword1!'];
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $data['token'] = Password::createToken($other);
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $data['token'] = Password::createToken($user);
        $this->travel(61)->minutes();
        $this->post('/reset-password', $data)->assertSessionHasErrors('email');
        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_password_rules_and_confirmation_are_enforced(): void
    {
        $user = User::factory()->create();
        $data = ['email' => $user->email, 'token' => Password::createToken($user), 'password' => 'weak', 'password_confirmation' => 'weak'];
        $this->post('/reset-password', $data)->assertSessionHasErrors('password');
        $data['password'] = 'NewPassword1!';
        $this->post('/reset-password', $data)->assertSessionHasErrors('password');
        $this->assertTrue(Password::tokenExists($user, $data['token']));
    }
}
