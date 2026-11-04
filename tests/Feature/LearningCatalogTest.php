<?php

namespace Tests\Feature;

use App\Models\{CatalogEntry, User, UserSubjectQualification};
use App\Support\PeerMatching;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LearningCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_pages_have_shared_category_tabs_and_remain_admin_only(): void
    {
        $this->withoutVite();
        foreach (['/admin/qualifications', '/admin/catalog'] as $url) {
            $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
            $this->actingAs(User::factory()->create(['role' => 'admin']))->get($url)->assertOk()->assertSee('Approval categories')->assertSee('Teaching qualifications')->assertSee('Programs')->assertSee('Subjects')->assertSee('Topics')->assertSee('aria-current="page"', false);
        }
    }

    public function test_admin_catalog_tabs_separate_programs_subjects_and_topics(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (['program', 'subject', 'topic'] as $kind) {
            $this->actingAs($admin)->get('/admin/catalog?kind='.$kind)->assertOk()
                ->assertViewHas('kind', $kind)
                ->assertViewHas('entries', fn ($entries) => $entries->isNotEmpty() && $entries->every(fn ($entry) => $entry->kind === $kind))
                ->assertSee('Programs')->assertSee('Subjects')->assertSee('Topics');
        }
        $this->get('/admin/catalog')->assertOk()->assertViewHas('kind', 'program');
        $this->get('/admin/catalog?kind=invalid')->assertNotFound();
        $this->get('/admin/catalog?kind[]=program')->assertNotFound();
    }

    private function profile(User $user, array $extra = []): array
    {
        return $extra + ['first_name' => $user->first_name, 'last_name' => $user->last_name, 'email' => $user->email];
    }

    public function test_other_program_is_saved_on_profile_then_shared_after_admin_approval(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(); $other = User::factory()->create(); $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->putJson('/profile', $this->profile($user, ['course' => '__other', 'course_unlisted' => 1, 'course_other' => 'BSTM']))->assertOk()->assertJsonPath('user.course', 'BSTM');
        $entry = CatalogEntry::where('kind', 'program')->where('name', 'BSTM')->firstOrFail();
        $this->assertSame('pending', $entry->status);
        $this->actingAs($other)->putJson('/profile', $this->profile($other, ['course' => 'BSTM']))->assertUnprocessable();
        $this->get('/admin/catalog')->assertForbidden();
        $this->actingAs($admin)->post('/admin/catalog/'.$entry->id, ['status' => 'approved'])->assertRedirect();
        $this->post('/admin/catalog/'.$entry->id, ['status' => 'rejected'])->assertStatus(409);
        $this->actingAs($other)->putJson('/profile', $this->profile($other, ['course' => 'BSTM']))->assertOk();
        $this->get('/profile')->assertOk()->assertSee('BSTM')->assertSee('row-cols-lg-2');
        $this->putJson('/profile', $this->profile($other, ['course_unlisted' => 1, 'course_other' => ['invalid']]))->assertUnprocessable();
        $this->actingAs($admin)->get('/admin/catalog')->assertOk()->assertSee('BSTM');
    }

    public function test_new_subject_and_topic_need_separate_catalog_review_and_do_not_verify_teachers(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(); $admin = User::factory()->create(['role' => 'admin']);
        $payload = ['kind' => 'topic', 'name' => 'Japanese Conversation', 'subject_name' => 'Foreign Languages'];
        $this->actingAs($user)->post('/catalog/suggestions', $payload)->assertRedirect();
        $this->post('/catalog/suggestions', $payload)->assertRedirect();
        $topic = CatalogEntry::where('name', 'Japanese Conversation')->firstOrFail();
        $this->assertSame(1, CatalogEntry::where('name', 'Japanese Conversation')->count());
        $this->postJson('/apply-to-teach', ['subject' => $topic->name, 'proof_document' => UploadedFile::fake()->create('proof.pdf', 1, 'application/pdf')])->assertUnprocessable();
        $this->actingAs($admin)->postJson('/admin/catalog/'.$topic->id, ['status' => 'approved'])->assertUnprocessable();
        $this->post('/admin/catalog/'.$topic->parent_id, ['status' => 'approved'])->assertRedirect();
        $this->post('/admin/catalog/'.$topic->id, ['status' => 'approved'])->assertRedirect();
        $this->assertDatabaseCount('user_subject_qualifications', 0);
        $this->actingAs($user)->post('/catalog/suggestions', $payload)->assertSessionHasErrors('name');
        $this->putJson('/profile', $this->profile($user, ['learning_interests' => [$topic->name]]))->assertOk();
    }

    public function test_multiple_qualifications_have_independent_duplicates_and_attempt_limits(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        UserSubjectQualification::create(['user_id' => $user->id, 'subject_name' => 'Python Basics', 'proof_file_path' => 'proof.pdf', 'status' => 'approved']);
        $this->actingAs($user)->postJson('/apply-to-teach', ['subject' => 'Java Basics', 'proof_document' => UploadedFile::fake()->create('java.pdf', 1, 'application/pdf')])->assertOk();
        $this->postJson('/apply-to-teach', ['subject' => 'Java Basics', 'proof_document' => UploadedFile::fake()->create('java.pdf', 1, 'application/pdf')])->assertUnprocessable();
        $this->postJson('/apply-to-teach', ['subject' => 'SQL Basics', 'proof_document' => UploadedFile::fake()->create('sql.pdf', 1, 'application/pdf')])->assertOk();
        $this->postJson('/apply-to-teach', ['subject' => 'Python Basics', 'proof_document' => UploadedFile::fake()->create('python.pdf', 1, 'application/pdf')])->assertUnprocessable();
        foreach (range(1, 3) as $i) UserSubjectQualification::create(['user_id' => $user->id, 'subject_name' => 'Excel Basics', 'proof_file_path' => 'proof.pdf', 'status' => 'rejected']);
        $this->postJson('/apply-to-teach', ['subject' => 'Excel Basics', 'proof_document' => UploadedFile::fake()->create('excel.pdf', 1, 'application/pdf')])->assertUnprocessable();
        $this->travel(11)->minutes();
        $this->postJson('/apply-to-teach', ['subject' => 'Excel Formulas', 'proof_document' => UploadedFile::fake()->create('excel.pdf', 1, 'application/pdf')])->assertOk();
        $admin = User::factory()->create(['role' => 'admin']);
        $java = UserSubjectQualification::where('user_id', $user->id)->where('subject_name', 'Java Basics')->firstOrFail();
        $this->actingAs($admin)->post('/admin/qualifications/'.$java->id.'/respond', ['status' => 'approved'])->assertRedirect();
        $this->postJson('/admin/qualifications/'.$java->id.'/respond', ['status' => 'rejected', 'rejection_reason' => 'Changed decision'])->assertStatus(409);
        $this->assertDatabaseHas('user_subject_qualifications', ['user_id' => $user->id, 'subject_name' => 'SQL Basics', 'status' => 'pending']);
        $this->withoutVite();
        $this->actingAs($user)->get('/profile')->assertOk()->assertSee('Java Basics')->assertSee('SQL Basics');
    }

    public function test_catalog_topics_match_across_programs_and_requests_only_offer_verified_topics(): void
    {
        $learner = User::factory()->create(['course' => 'BSBA', 'learning_interests' => ['Python Basics']]);
        $teacher = User::factory()->create(['course' => 'BSCS']);
        UserSubjectQualification::create(['user_id' => $teacher->id, 'subject_name' => 'Python Basics', 'status' => 'approved', 'proof_file_path' => 'proof.pdf']);
        UserSubjectQualification::create(['user_id' => $teacher->id, 'subject_name' => 'Java Basics', 'status' => 'pending', 'proof_file_path' => 'proof.pdf']);
        $this->assertSame([$teacher->id], PeerMatching::recommend($learner)->pluck('id')->all());
        $this->actingAs($learner)->getJson('/swap/options/'.$teacher->id)->assertOk()->assertJsonPath('peer_subjects', ['Python Basics'])->assertJsonPath('topic_subjects.Python Basics', 'Programming');
        $this->postJson('/swap/add', ['user_id' => $teacher->id, 'request_type' => 'learning', 'requested_subject' => 'Java Basics'])->assertUnprocessable();
        $this->postJson('/swap/add', ['user_id' => $teacher->id, 'request_type' => 'learning', 'requested_subject' => 'Python Basics'])->assertOk();
        $this->putJson('/profile', $this->profile($learner, ['learning_interests' => ['Unapproved invented topic']]))->assertUnprocessable();
        $learner->forceFill(['expertise' => ['Previous custom skill']])->save();
        $this->putJson('/profile', $this->profile($learner, ['expertise' => ['Previous custom skill']]))->assertOk();
        $this->putJson('/profile', $this->profile($learner, ['learning_interests' => [], 'expertise' => []]))->assertOk()->assertJsonPath('user.learning_interests', []);
    }
}
