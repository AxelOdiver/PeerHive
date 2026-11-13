<?php

namespace Tests\Feature;

use App\Models\LoginOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PeerHiveItFeedbackPatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_otp_pages_omit_generic_slogan_and_login_device_notice(): void
    {
        $this->withoutVite();
        $this->get(route('login'))->assertOk()
            ->assertDontSee('LEARN · SHARE · GROW')
            ->assertDontSee('On a new device')
            ->assertSee('Welcome back');

        $user = User::factory()->create();
        $this->withSession(['otp_user_id' => $user->id])->get(route('otp.show'))->assertOk()
            ->assertDontSee('LEARN · SHARE · GROW')
            ->assertSee('id="countdown"', false)
            ->assertSee('data-server-now-ms=', false);
    }

    public function test_otp_page_uses_existing_code_expiration(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $otp = LoginOtp::create([
            'user_id' => $user->id,
            'code' => '******',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(3),
        ]);

        $this->withSession(['otp_user_id' => $user->id])->get(route('otp.show'))->assertOk()
            ->assertSee('data-expires-at-ms="'.$otp->expires_at->getTimestampMs().'"', false);
    }

    public function test_expired_code_returns_expired_message_even_when_digits_are_wrong(): void
    {
        $user = User::factory()->create();
        $otp = LoginOtp::create([
            'user_id' => $user->id,
            'code' => '******',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->subMinute(),
        ]);
        $this->withSession(['otp_user_id' => $user->id])->postJson(route('otp.verify'), ['code' => '999999'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Code has expired. Request a new code.');
        $this->assertDatabaseMissing('login_otps', ['id' => $otp->id]);
    }

    public function test_wrong_unexpired_code_returns_readable_validation_error(): void
    {
        $user = User::factory()->create();
        LoginOtp::create([
            'user_id' => $user->id,
            'code' => '******',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(10),
        ]);
        $this->withSession(['otp_user_id' => $user->id])->postJson(route('otp.verify'), ['code' => '999999'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'Invalid code. Please try again.');
    }

    public function test_resend_returns_timestamp_of_new_code(): void
    {
        Mail::fake();
        $user = User::factory()->create();
        LoginOtp::create([
            'user_id' => $user->id,
            'code' => '******',
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->subMinute(),
        ]);
        $response = $this->withSession(['otp_user_id' => $user->id])
            ->postJson(route('otp.resend'))
            ->assertOk()->assertJsonStructure(['message', 'expires_at_ms', 'server_now_ms']);
        $this->assertSame(LoginOtp::where('user_id', $user->id)->firstOrFail()->expires_at->getTimestampMs(), $response->json('expires_at_ms'));
        $this->assertDatabaseCount('login_otps', 1);
    }

    public function test_schedule_clear_day_requires_existing_confirmation_helper(): void
    {
        $source = file_get_contents(resource_path('js/pages/schedule.js'));
        $this->assertIsString($source);
        $this->assertStringContainsString('window.confirmAction(', $source);
        $this->assertStringContainsString('if (!result?.isConfirmed) return;', $source);
    }
}
