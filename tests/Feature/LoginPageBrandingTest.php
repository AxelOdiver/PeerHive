<?php

namespace Tests\Feature;

use Tests\TestCase;

class LoginPageBrandingTest extends TestCase
{
    public function test_login_page_uses_brand_layout_and_keeps_the_authentication_form(): void
    {
        $this->withoutVite();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('peerhive-login-shell')
            ->assertSee('Welcome back')
            ->assertSee('name="email"', false)
            ->assertSee('name="password"', false)
            ->assertSee('id="form"', false)
            ->assertSee('action="'.route('login.store').'"', false)
            ->assertSee('id="loginSubmitBtn"', false)
            ->assertSee('id="loginSubmitSpinner"', false)
            ->assertSee('id="loginSubmitText"', false)
            ->assertSee('toggle-password')
            ->assertDontSee('On a new device')
            ->assertSee('data-bs-theme-value="light"', false);
    }

    public function test_login_page_has_centered_password_reset_below_submit_and_button_to_register(): void
    {
        $this->withoutVite();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('class="peerhive-login-forgot"', false)
            ->assertSee('href="'.route('password.request').'"', false)
            ->assertSee('href="'.route('register').'"', false)
            ->assertSee('peerhive-login-register')
            ->assertSeeInOrder(['id="loginSubmitBtn"', 'Forgot password?', 'New to PeerHive?', 'Create an account'], false);
    }

    public function test_existing_password_reset_page_is_still_available(): void
    {
        $this->withoutVite();
        $this->get(route('password.request'))->assertOk()->assertSee('Send reset link');
    }
}
