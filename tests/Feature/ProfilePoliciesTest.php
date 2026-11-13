<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePoliciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_teaching_badges_only_show_approved_subjects_and_follow_status_changes(): void
    {
        $this->withoutVite();
        $teacher = User::factory()->create();
        $viewer = User::factory()->create();
        $viewer->forceFill(['learning_interests' => ['Coding']])->save();
        $qualification = \App\Models\UserSubjectQualification::create([
            'user_id' => $teacher->id, 'subject_name' => 'Coding',
            'proof_file_path' => 'qualifications/private-proof.pdf', 'status' => 'pending',
        ]);
        $url = '/users/'.$teacher->id.'/profile';
        $this->actingAs($viewer)->get($url)->assertOk()->assertDontSee('Verified to teach: Coding');
        $qualification->update(['status' => 'approved']);
        $this->get($url)->assertOk()->assertSee('Verified to teach: Coding')->assertDontSee('private-proof.pdf');
        $this->get('/dashboard')->assertOk()->assertSee('Verified to teach: Coding');
        $qualification->update(['status' => 'rejected']);
        $this->get($url)->assertOk()->assertDontSee('Verified to teach: Coding');
        $this->actingAs($teacher)->get('/profile')->assertOk()->assertSee('Teaching Verification');
    }

    public function test_policy_pages_are_public_and_registration_requires_acceptance(): void
    {
        $this->withoutVite();
        $this->get('/terms')->assertOk()->assertSee('Terms and Conditions');
        $this->get('/privacy')->assertOk()->assertSee('Privacy Policy');
        $this->get('/register')->assertOk()->assertSee('policyAcceptanceModal')->assertSee('policyScrollContent')->assertSee('End of Terms and Privacy Policy.');
        $this->get('/login')->assertOk()->assertSee('loginTermsModal')->assertSee('loginPrivacyModal')
            ->assertSee('data-bs-target="#loginTermsModal"', false)->assertSee('data-bs-target="#loginPrivacyModal"', false);
        $data = ['first_name' => 'New', 'last_name' => 'Member', 'username' => 'newmember', 'email' => 'new@example.com', 'password' => 'StrongPassword1!', 'password_confirmation' => 'StrongPassword1!'];
        $this->postJson('/register', $data)->assertUnprocessable()->assertJsonValidationErrors('accept_policies');
        $this->assertDatabaseCount('users', 0);
        $this->postJson('/register', $data + ['accept_policies' => '0'])->assertUnprocessable();
        $this->postJson('/register', $data + ['accept_policies' => '1', 'terms_version' => 'forged'])->assertOk();
        $user = User::first();
        $this->assertNotNull($user->policies_accepted_at);
        $this->assertSame(config('policies.terms_version'), $user->terms_version);
        $this->assertSame(config('policies.privacy_version'), $user->privacy_version);
        $this->assertFalse($user->show_school);
    }

    public function test_profile_fields_save_normalize_and_remain_private_in_generic_json(): void
    {
        $user = User::factory()->create();
        $data = ['first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => $user->email,
            'course' => 'BSCS', 'expertise' => ' Python Basics, Graphic Design, Python Basics, ', 'school' => 'Example College', 'school_location' => 'Taipei campus',
            'show_course' => 1, 'show_expertise' => 1, 'show_school' => 0, 'show_school_location' => 0];
        $this->actingAs($user)->putJson('/profile', $data)->assertOk()->assertJsonPath('user.expertise', ['Python Basics', 'Graphic Design']);
        $user->refresh();
        $this->assertSame('Example College', $user->school);
        $this->assertArrayNotHasKey('school', $user->toArray());
        $this->assertArrayNotHasKey('expertise', $user->toArray());
        $this->getJson('/profile/show')->assertOk()->assertJsonPath('user.school', 'Example College');
        $this->withoutVite();
        $this->get('/profile')->assertOk()->assertSee('show_school')->assertSee('Skills &amp; interests', false);
        $this->assertNotSame('admin', $user->role);
    }

    public function test_visibility_is_enforced_in_another_members_profile_and_api(): void
    {
        $this->withoutVite();
        $owner = User::factory()->create();
        $owner->forceFill(['school' => 'HiddenSchool123', 'school_location' => 'HiddenCampus123', 'course' => 'VisibleCourse123', 'expertise' => ['HiddenSkill123'], 'show_course' => true])->save();
        $viewer = User::factory()->create();
        $this->actingAs($viewer)->get('/users/'.$owner->id.'/profile')->assertOk()->assertSee('VisibleCourse123')->assertDontSee('HiddenSchool123')->assertDontSee('HiddenCampus123')->assertDontSee('HiddenSkill123');
        $this->getJson('/users/'.$owner->id)->assertForbidden();
        $owner->forceFill(['show_school' => true, 'show_school_location' => true, 'show_expertise' => true])->save();
        $this->get('/users/'.$owner->id.'/profile')->assertSee('HiddenSchool123')->assertSee('HiddenCampus123')->assertSee('HiddenSkill123')->assertSee('Self-described expertise');
        $owner->forceFill(['show_school' => false])->save();
        $this->get('/users/'.$owner->id.'/profile')->assertDontSee('HiddenSchool123');
    }

    public function test_profile_limits_and_visibility_validation_reject_invalid_data(): void
    {
        $user = User::factory()->create();
        $base = ['first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => $user->email];
        $this->actingAs($user)->putJson('/profile', $base + ['expertise' => [str_repeat('x', 41)], 'show_school' => 'invalid', 'course' => str_repeat('x', 121)])
            ->assertUnprocessable()->assertJsonValidationErrors(['expertise.0', 'show_school', 'course']);
        $this->putJson('/profile', $base + ['expertise' => range(1, 11)])->assertUnprocessable()->assertJsonValidationErrors('expertise');
        $this->assertNull($user->fresh()->course);
    }

    public function test_existing_accounts_have_no_invented_acceptance_and_profile_updates_cannot_forge_it(): void
    {
        $user = User::factory()->create();
        $this->assertNull($user->policies_accepted_at);
        $this->actingAs($user)->putJson('/profile', ['first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => $user->email, 'terms_version' => 'forged', 'policies_accepted_at' => now()->toISOString()])->assertOk();
        $this->assertNull($user->fresh()->policies_accepted_at);
    }
}
