<?php

namespace Tests\Feature;

use App\Models\Swap;
use App\Models\User;
use App\Models\UserSubjectQualification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningRequestsTest extends TestCase
{
    use RefreshDatabase;

    private function qualify(User $user, string $subject, string $status = 'approved'): UserSubjectQualification
    {
        return UserSubjectQualification::create(['user_id' => $user->id, 'subject_name' => $subject, 'status' => $status, 'proof_file_path' => 'proof.pdf']);
    }

    public function test_unverified_learner_can_request_help_from_a_subject_verified_tutor_and_be_accepted(): void
    {
        $learner = User::factory()->create();
        $tutor = User::factory()->create();
        $this->qualify($tutor, 'Coding');
        $this->actingAs($learner)->getJson('/swap/options/'.$tutor->id)->assertOk()->assertJsonPath('peer_subjects', ['Coding'])->assertJsonPath('my_subjects', []);
        $this->postJson('/swap/add', ['user_id' => $tutor->id, 'request_type' => 'learning', 'requested_subject' => 'Coding'])->assertOk();
        $swap = Swap::firstOrFail();
        $this->assertNull($swap->offered_subject);
        $this->withoutVite();
        $this->get('/swap')->assertOk()->assertSee('Learning help')->assertSee('Coding');
        $this->actingAs($tutor)->get('/swap')->assertOk()->assertSee('Learning help')->assertSee('Coding');
        $this->postJson('/swap/'.$swap->id.'/respond', ['status' => 'accepted'])->assertOk();
        $this->assertSame('accepted', $swap->fresh()->status);
    }

    public function test_request_and_response_notifications_are_private_and_link_to_the_correct_peer(): void
    {
        $this->withoutVite();
        $learner = User::factory()->create(); $tutor = User::factory()->create(); $outsider = User::factory()->create();
        $this->qualify($tutor, 'Coding');
        $this->actingAs($learner)->postJson('/swap/add', ['user_id' => $tutor->id, 'request_type' => 'learning', 'requested_subject' => 'Coding'])->assertOk();
        $swap = Swap::firstOrFail();
        $this->actingAs($outsider)->getJson('/messages/unread-count')->assertJsonCount(0, 'swap_notifications');
        $this->actingAs($tutor)->getJson('/messages/unread-count')->assertJsonCount(1, 'swap_notifications');
        $this->postJson('/swap/'.$swap->id.'/respond', ['status' => 'accepted'])->assertOk()->assertJsonPath('redirect', route('messages', ['conversation' => \App\Models\Conversation::where('swap_id', $swap->id)->value('id')]));
        $this->getJson('/messages/unread-count')->assertJsonCount(0, 'swap_notifications');
        $this->actingAs($learner)->getJson('/messages/unread-count')->assertJsonCount(1, 'swap_notifications')->assertJsonPath('swap_notifications.0.text', 'Your learn/swap request was accepted.');
        $this->get('/swap?refresh=1')->assertOk()->assertSee('Accepted')->assertSee('data-status="accepted"', false);
        $this->getJson('/messages/unread-count')->assertJsonCount(1, 'swap_notifications');
        $this->get('/swap')->assertOk()->assertSee('Accepted');
        $this->getJson('/messages/unread-count')->assertJsonCount(0, 'swap_notifications');
        $this->getJson('/messages/unread-count')->assertJsonPath('swap_unread_count', 0)
            ->assertJsonCount(1, 'history')->assertJsonPath('history.0.read', true);
        $this->getJson('/messages/unread-count')->assertJsonPath('swap_unread_count', 0)->assertJsonCount(1, 'history');
        $first = $this->postJson('/messages/conversations', ['user_id' => $tutor->id])->assertOk()->json('conversation_id');
        $this->actingAs($tutor)->postJson('/messages/conversations', ['user_id' => $learner->id])->assertOk()->assertJsonPath('conversation_id', $first);
    }

    public function test_both_swap_participants_must_be_approved_for_the_exact_subject_they_offer(): void
    {
        $sender = User::factory()->create();
        $peer = User::factory()->create();
        $this->qualify($peer, 'Coding');
        $pending = $this->qualify($sender, 'Graphic Design', 'pending');
        $data = ['user_id' => $peer->id, 'request_type' => 'skill_swap', 'requested_subject' => 'Coding', 'offered_subject' => 'Graphic Design'];
        $this->actingAs($sender)->postJson('/swap/add', $data)->assertUnprocessable()->assertJsonValidationErrors('offered_subject');
        $pending->update(['status' => 'approved']);
        $this->postJson('/swap/add', array_replace($data, ['requested_subject' => 'Foreign Language']))->assertUnprocessable()->assertJsonValidationErrors('requested_subject');
        $this->postJson('/swap/add', $data)->assertOk();
        $swap = Swap::first();
        $this->actingAs($peer)->postJson('/swap/'.$swap->id.'/respond', ['status' => 'accepted'])->assertOk();
        $this->actingAs($sender)->postJson('/swap/add', $data)->assertStatus(409);
        $this->assertSame('accepted', $swap->fresh()->status);
    }

    public function test_learning_help_rejects_unapproved_subjects_self_requests_and_forged_offer_fields(): void
    {
        $learner = User::factory()->create();
        $peer = User::factory()->create();
        $this->qualify($peer, 'Coding', 'rejected');
        $data = ['user_id' => $peer->id, 'request_type' => 'learning', 'requested_subject' => 'Coding'];
        $this->actingAs($learner)->postJson('/swap/add', $data)->assertUnprocessable()->assertJsonValidationErrors('requested_subject');
        $this->postJson('/swap/add', array_replace($data, ['user_id' => $learner->id]))->assertUnprocessable();
        $this->postJson('/swap/add', $data + ['offered_subject' => 'Graphic Design'])->assertUnprocessable()->assertJsonValidationErrors('offered_subject');
        $this->postJson('/swap/add', ['user_id' => $peer->id])->assertUnprocessable()->assertJsonValidationErrors(['request_type', 'requested_subject']);
        $this->assertDatabaseCount('swaps', 0);
    }

    public function test_acceptance_rechecks_qualifications_and_only_the_recipient_can_respond_once(): void
    {
        $learner = User::factory()->create();
        $tutor = User::factory()->create();
        $approval = $this->qualify($tutor, 'Coding');
        $data = ['user_id' => $tutor->id, 'request_type' => 'learning', 'requested_subject' => 'Coding'];
        $this->actingAs($learner)->postJson('/swap/add', $data)->assertOk();
        $swap = Swap::first();
        $this->postJson('/swap/add', $data)->assertStatus(409);
        $this->postJson('/swap/'.$swap->id.'/respond', ['status' => 'accepted'])->assertForbidden();
        $approval->update(['status' => 'rejected']);
        $this->actingAs($tutor)->postJson('/swap/'.$swap->id.'/respond', ['status' => 'accepted'])->assertUnprocessable();
        $this->assertSame('pending', $swap->fresh()->status);
        $this->postJson('/swap/'.$swap->id.'/respond', ['status' => 'declined'])->assertOk();
        $this->postJson('/swap/'.$swap->id.'/respond', ['status' => 'accepted'])->assertStatus(409);
    }

    public function test_legacy_requests_are_not_silently_reclassified_and_options_require_login(): void
    {
        $sender = User::factory()->create();
        $peer = User::factory()->create();
        $this->getJson('/swap/options/'.$peer->id)->assertUnauthorized();
        $legacy = Swap::create(['requester_id' => $sender->id, 'requested_user_id' => $peer->id, 'status' => 'pending']);
        $this->actingAs($peer)->postJson('/swap/'.$legacy->id.'/respond', ['status' => 'accepted'])->assertUnprocessable()->assertJsonValidationErrors('request_type');
        $this->postJson('/swap/'.$legacy->id.'/respond', ['status' => 'declined'])->assertOk();
        $this->assertNull($legacy->fresh()->request_type);
    }
}
