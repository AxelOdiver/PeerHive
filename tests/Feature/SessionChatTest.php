<?php
namespace Tests\Feature;

use App\Models\{User, UserSubjectQualification, Swap, Conversation};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionChatTest extends TestCase
{
    use RefreshDatabase;

    private function setupChat(string $type = 'learning'): array {
        $this->withoutVite();
        $learner = User::factory()->create(); $teacher = User::factory()->create();
        foreach ([[$teacher, 'Coding'], [$learner, 'Design']] as [$user, $subject]) UserSubjectQualification::create(['user_id' => $user->id, 'subject_name' => $subject, 'status' => 'approved', 'proof_file_path' => 'proof.pdf']);
        $dm = $this->actingAs($learner)->postJson('/messages/conversations', ['user_id' => $teacher->id])->json('conversation_id');
        $this->postJson('/swap/add', array_filter(['user_id' => $teacher->id, 'request_type' => $type, 'requested_subject' => 'Coding', 'offered_subject' => $type === 'skill_swap' ? 'Design' : null]))->assertOk();
        $swap = Swap::firstOrFail();
        $response = $this->actingAs($teacher)->postJson('/swap/'.$swap->id.'/respond', ['status' => 'accepted'])->assertOk();
        $chat = Conversation::where('swap_id', $swap->id)->firstOrFail();
        $this->assertNotEquals($dm, $chat->id);
        $response->assertJsonPath('redirect', route('messages', ['conversation' => $chat->id]));
        $this->postJson('/messages/conversations', ['user_id' => $learner->id])->assertJsonPath('conversation_id', $dm);
        return [$learner, $teacher, $chat];
    }

    private function propose($user, $chat): void {
        $this->actingAs($user)->postJson('/messages/conversations/'.$chat->id.'/session', ['action' => 'propose', 'revision' => 0, 'starts_at' => now()->addDay()->format('Y-m-d\TH:i'), 'timezone' => 'UTC', 'duration' => '60', 'teaching_minutes' => '35', 'meeting_method' => 'online', 'meeting_details' => 'https://meet.google.com/abc-defg-hij'])->assertOk();
    }

    public function test_completed_session_chat_is_read_only_but_ratings_and_direct_messages_remain_available(): void {
        [$learner, $teacher, $chat] = $this->setupChat();
        $messages = '/messages/conversations/'.$chat->id;
        $path = $messages.'/session';
        $this->propose($learner, $chat);
        $this->actingAs($teacher)->postJson($path, ['action' => 'accept', 'revision' => 1])->assertOk();
        $this->travel(2)->days();
        $this->actingAs($learner)->postJson($path, ['action' => 'finish', 'revision' => 2])->assertOk()->assertJsonPath('session_finished', false);
        $message = $this->postJson($messages, ['body' => 'Please confirm our session.'])->assertOk()->json('message.id');
        $this->actingAs($teacher)->postJson($path, ['action' => 'finish', 'revision' => 3])->assertOk()->assertJsonPath('session_finished', true);
        foreach ([$learner, $teacher] as $user) {
            $this->actingAs($user)->getJson($messages)->assertOk()->assertJsonPath('session_finished', true)->assertJsonFragment(['body' => 'Please confirm our session.']);
            $this->postJson($messages, ['body' => 'Too late'])->assertStatus(409);
            $this->postJson($messages, ['attachment' => \Illuminate\Http\UploadedFile::fake()->create('notes.txt', 1, 'text/plain')])->assertStatus(409);
            $this->postJson($path, ['action' => 'propose', 'revision' => 4])->assertStatus(409);
        }
        $this->actingAs($learner)->putJson('/messages/'.$message, ['body' => 'Changed'])->assertStatus(409);
        $this->deleteJson('/messages/'.$message)->assertStatus(409);
        $this->postJson('/messages/'.$message.'/reactions', ['emoji' => '👍'])->assertStatus(409);
        $this->postJson($messages.'/rating', ['stars' => 5])->assertOk();
        $dm = $this->postJson('/messages/conversations', ['user_id' => $teacher->id])->assertOk()->json('conversation_id');
        $this->getJson('/messages/conversations/'.$dm)->assertOk()->assertJsonPath('session_finished', false);
        $this->postJson('/messages/conversations/'.$dm, ['body' => 'Thank you!'])->assertOk();
    }

    public function test_private_chat_schedule_and_mutual_completion_credit_correct_swap_minutes_once(): void {
        [$learner, $teacher, $chat] = $this->setupChat('skill_swap');
        $path = '/messages/conversations/'.$chat->id.'/session';
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->getJson($path)->assertForbidden();
        $this->postJson($path, ['action' => 'cancel', 'revision' => 0])->assertForbidden();
        $this->propose($learner, $chat);
        $this->postJson($path, ['action' => 'accept', 'revision' => 1])->assertStatus(409);
        $this->actingAs($teacher)->postJson($path, ['action' => 'accept', 'revision' => 0])->assertStatus(409);
        $this->postJson($path, ['action' => 'accept', 'revision' => 1])->assertOk();
        $session = $chat->fresh()->studySession;
        $this->assertNull($session->community_id);
        $this->assertEquals(2, $session->participants()->count());
        $this->get('/study-sessions?filter=mine')->assertOk()->assertSee('Swap');
        $this->get('/schedule')->assertOk()->assertSee('Swap');
        $this->get('/study-sessions/'.$session->id)->assertRedirect(route('messages', ['conversation' => $chat->id]));
        $this->postJson('/study-sessions/'.$session->id.'/complete')->assertForbidden();
        $this->postJson($path, ['action' => 'finish', 'revision' => 2])->assertUnprocessable();
        $this->actingAs($outsider)->get('/study-sessions/'.$session->id)->assertForbidden();
        $this->travel(2)->days();
        $this->actingAs($learner)->postJson($path, ['action' => 'finish', 'revision' => 2])->assertOk();
        $this->assertDatabaseCount('point_entries', 0);
        $this->actingAs($teacher)->postJson($path, ['action' => 'finish', 'revision' => 3])->assertOk();
        $this->assertDatabaseCount('point_entries', 2);
        $this->postJson($path, ['action' => 'finish', 'revision' => 4])->assertStatus(409);
        $this->assertDatabaseCount('point_entries', 2);
        $this->actingAs($learner)->get('/progress')->assertOk()->assertViewHas('stats', fn ($s) => $s['learning_minutes'] === 35 && $s['teaching_minutes'] === 25)->assertViewHas('records', fn ($r) => $r->count() === 1);
        $this->actingAs($teacher)->get('/progress')->assertOk()->assertViewHas('stats', fn ($s) => $s['learning_minutes'] === 25 && $s['teaching_minutes'] === 35);
        $this->get('/history')->assertOk();
    }

    public function test_attendance_problem_blocks_credit_and_cancellation_preserves_chat(): void {
        [$learner, $teacher, $chat] = $this->setupChat();
        $path = '/messages/conversations/'.$chat->id.'/session';
        $this->propose($learner, $chat);
        $this->actingAs($teacher)->postJson($path, ['action' => 'accept', 'revision' => 1])->assertOk();
        $this->travel(2)->days();
        $this->actingAs($learner)->postJson($path, ['action' => 'problem', 'revision' => 2, 'reason' => 'We did not meet.'])->assertOk();
        $this->actingAs($teacher)->postJson($path, ['action' => 'finish', 'revision' => 3])->assertStatus(409);
        $this->postJson($path, ['action' => 'cancel', 'revision' => 3])->assertOk();
        $this->assertDatabaseCount('point_entries', 0);
        $this->getJson($path)->assertOk();
        $this->assertEquals('cancelled', $chat->fresh()->studySession->status);
        $this->actingAs($learner)->postJson('/swap/add', ['user_id' => $teacher->id, 'request_type' => 'learning', 'requested_subject' => 'Coding'])->assertOk();
        $this->actingAs($teacher)->postJson('/swap/'.Swap::first()->id.'/respond', ['status' => 'accepted'])->assertOk();
        $this->assertNull($chat->fresh()->swap_id);
        $this->assertDatabaseCount('conversations', 3);
        $this->getJson($path)->assertOk();
    }

    public function test_early_finish_freezes_elapsed_minutes_until_both_confirm(): void {
        [$learner, $teacher, $chat] = $this->setupChat('skill_swap');
        $path = '/messages/conversations/'.$chat->id.'/session';
        $this->propose($learner, $chat);
        $this->actingAs($teacher)->postJson($path, ['action' => 'accept', 'revision' => 1])->assertOk();
        $session = $chat->fresh()->studySession;
        $this->travelTo($session->starts_at->copy()->addMinutes(45));
        $this->getJson($path)->assertJsonPath('finish', true);
        $this->postJson($path, ['action' => 'finish', 'revision' => 2])->assertOk()->assertJsonPath('finish', false);
        $this->assertDatabaseCount('point_entries', 0);
        $this->travel(20)->minutes();
        $this->actingAs($learner)->postJson($path, ['action' => 'finish', 'revision' => 3])->assertOk();
        $this->get('/progress')->assertViewHas('stats', fn ($s) => $s['learning_minutes'] === 35 && $s['teaching_minutes'] === 10);
        $this->assertEquals(45, (int) $session->fresh()->starts_at->diffInMinutes($session->fresh()->ends_at));
        $this->assertDatabaseCount('point_entries', 2);
    }

    public function test_finish_before_start_records_actual_time_and_session_proposals_are_messages(): void {
        [$learner, $teacher, $chat] = $this->setupChat();
        $path = '/messages/conversations/'.$chat->id.'/session';
        $this->propose($learner, $chat);
        $context = $chat->fresh()->session_context;
        $context['proposal']['duration'] = '60';
        $chat->update(['session_context' => $context]);
        $proposal = $chat->messages()->whereNotNull('session_event')->latest('id')->firstOrFail();
        $this->assertEquals('propose', $proposal->session_event['action']);
        $this->assertEquals($learner->id, $proposal->sender_id);
        $this->actingAs($teacher)->getJson('/messages/conversations/'.$chat->id)->assertOk()->assertJsonPath('messages.1.session_event.current', true);
        $this->postJson($path, ['action' => 'accept', 'revision' => 1])->assertOk()->assertJsonPath('finish', true)->assertJsonPath('before_start', true);
        $this->postJson($path, ['action' => 'finish', 'revision' => 2, 'actual_minutes' => '25'])->assertOk();
        $this->assertDatabaseCount('point_entries', 0);
        $this->travel(5)->minutes();
        $this->actingAs($learner)->postJson($path, ['action' => 'finish', 'revision' => 3])->assertOk();
        $this->get('/progress')->assertViewHas('stats', fn ($s) => $s['learning_minutes'] === 25);
        $this->assertDatabaseCount('point_entries', 2);
        $this->putJson('/messages/'.$proposal->id, ['body' => 'Changed schedule'])->assertUnprocessable();
        $this->deleteJson('/messages/'.$proposal->id)->assertUnprocessable();
    }

    public function test_only_confirmed_learners_can_rate_their_teacher_once(): void {
        [$learner, $teacher, $chat] = $this->setupChat();
        $path = '/messages/conversations/'.$chat->id.'/session';
        $rating = '/messages/conversations/'.$chat->id.'/rating';
        $this->propose($learner, $chat);
        $this->postJson($rating, ['stars'=>5])->assertStatus(409);
        $this->actingAs($teacher)->postJson($path, ['action'=>'accept','revision'=>1])->assertOk();
        $this->postJson($path, ['action'=>'finish','revision'=>2,'actual_minutes'=>'30'])->assertOk();
        $this->actingAs($learner)->postJson($path, ['action'=>'finish','revision'=>3])->assertOk()->assertJsonPath('rating.teacher_name', trim($teacher->first_name.' '.$teacher->last_name));
        $this->postJson($rating, ['stars'=>6])->assertUnprocessable();
        $this->postJson($rating, ['stars'=>4, 'teacher_id'=>$learner->id])->assertOk();
        $this->assertDatabaseHas('session_ratings',['learner_id'=>$learner->id,'teacher_id'=>$teacher->id,'stars'=>4]);
        $this->postJson($rating, ['stars'=>5])->assertStatus(409);
        $this->getJson($path)->assertJsonPath('rating', null);
        $this->actingAs($teacher)->postJson($rating, ['stars'=>5])->assertForbidden();
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->postJson($rating, ['stars'=>5])->assertForbidden();
        $learner->forceFill(['learning_interests'=>['Coding']])->save();
        $this->actingAs($learner)->get('/dashboard')->assertOk()->assertSee('4.0 / 5')
            ->assertViewHas('students', fn ($students) => $students->contains(fn ($student) => $student->id === $teacher->id
                && $student->teaching_ratings_count === 1 && (float) $student->teaching_ratings_avg_stars === 4.0));
    }

    public function test_schedule_acceptance_rechecks_qualification_and_busy_times(): void {
        [$learner, $teacher, $chat] = $this->setupChat();
        $path = '/messages/conversations/'.$chat->id.'/session';
        $this->propose($learner, $chat);
        UserSubjectQualification::where('user_id', $teacher->id)->update(['status' => 'rejected']);
        $this->actingAs($teacher)->postJson($path, ['action' => 'accept', 'revision' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('study_sessions', 0);
        UserSubjectQualification::where('user_id', $teacher->id)->update(['status' => 'approved']);
        $this->postJson($path, ['action' => 'accept', 'revision' => 1])->assertOk();
        $this->postJson($path, ['action' => 'accept', 'revision' => 2])->assertStatus(409);
        $this->assertDatabaseCount('study_sessions', 1);
        $admin = User::factory()->create(); $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin)->get('/study-sessions/'.$chat->fresh()->study_session_id)->assertForbidden();
    }
}
