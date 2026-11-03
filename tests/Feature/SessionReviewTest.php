<?php

namespace Tests\Feature;

use App\Models\{Conversation, SessionRating, StudySession, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionReviewTest extends TestCase
{
    use RefreshDatabase;

    private function reviewSession(?User $teacher = null, bool $completed = true): array
    {
        $this->withoutVite();
        $learner = User::factory()->create();
        $teacher ??= User::factory()->create();
        $session = StudySession::create([
            'host_id' => $teacher->id, 'title' => 'Private lesson title', 'subject' => 'Python Basics', 'format' => 'tutor',
            'starts_at' => now()->subHours(2), 'ends_at' => now()->subHour(), 'timezone' => 'UTC', 'capacity' => 2,
            'meeting_method' => 'online', 'meeting_details' => 'https://meet.google.com/private-meeting',
            'status' => $completed ? 'completed' : 'scheduled',
        ]);
        $chat = Conversation::create([
            'created_by' => $teacher->id, 'is_group' => false, 'study_session_id' => $session->id,
            'session_context' => [
                'learner' => $learner->id, 'teacher' => $teacher->id, 'type' => 'learning', 'subject' => 'Python Basics',
                'state' => $completed ? 'completed' : 'scheduled', 'revision' => 0,
                'confirmed' => $completed ? [$learner->id, $teacher->id] : [],
            ],
        ]);
        $chat->users()->attach([$learner->id, $teacher->id]);
        $session->participants()->create(['user_id' => $learner->id, 'learning_minutes' => 60, 'teaching_minutes' => 0, 'confirmed_at' => $completed ? now() : null]);
        $session->participants()->create(['user_id' => $teacher->id, 'learning_minutes' => 0, 'teaching_minutes' => 60, 'confirmed_at' => $completed ? now() : null]);
        return [$learner, $teacher, $chat];
    }

    public function test_comments_are_optional_and_existing_star_only_ratings_still_work(): void
    {
        foreach ([[], ['comment' => null], ['comment' => '   ']] as $optional) {
            [$learner, $teacher, $chat] = $this->reviewSession();
            $this->actingAs($learner)->postJson('/messages/conversations/'.$chat->id.'/rating', ['stars' => 4] + $optional)->assertOk();
            $this->assertDatabaseHas('session_ratings', ['study_session_id' => $chat->study_session_id, 'teacher_id' => $teacher->id, 'stars' => 4, 'comment' => null]);
            $this->get('/users/'.$teacher->id.'/profile')->assertOk()->assertSee('4.0 / 5')->assertSee('(1 rating)')->assertSee('Rated without a written review.');
        }
    }

    public function test_review_validation_is_enforced_on_the_server_and_does_not_save_invalid_attempts(): void
    {
        [$learner, $teacher, $chat] = $this->reviewSession();
        $url = '/messages/conversations/'.$chat->id.'/rating';
        $this->actingAs($learner)->postJson($url, ['stars' => 5, 'comment' => str_repeat('é', 501)])
            ->assertUnprocessable()->assertJsonValidationErrors('comment');
        $this->postJson($url, ['stars' => 5, 'comment' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors('comment');
        $this->postJson($url, ['comment' => 'A comment cannot replace stars.'])->assertUnprocessable()->assertJsonValidationErrors('stars');
        $this->assertDatabaseCount('session_ratings', 0);
        $this->postJson($url, ['stars' => 5, 'comment' => str_repeat('é', 500), 'teacher_id' => $learner->id])->assertOk();
        $this->assertDatabaseHas('session_ratings', ['teacher_id' => $teacher->id, 'comment' => str_repeat('é', 500)]);
    }

    public function test_other_members_can_read_escaped_reviews_and_the_correct_average_without_private_session_data(): void
    {
        [$learner, $teacher, $chat] = $this->reviewSession();
        $comment = '<img src=x onerror=alert(1)> Helpful examples.';
        $this->actingAs($learner)->postJson('/messages/conversations/'.$chat->id.'/rating', ['stars' => 5, 'comment' => $comment])->assertOk();
        [$secondLearner, , $secondChat] = $this->reviewSession($teacher);
        $this->actingAs($secondLearner)->postJson('/messages/conversations/'.$secondChat->id.'/rating', ['stars' => 4])->assertOk();
        [$unrelatedLearner, , $unrelatedChat] = $this->reviewSession();
        $this->actingAs($unrelatedLearner)->postJson('/messages/conversations/'.$unrelatedChat->id.'/rating', ['stars' => 1, 'comment' => 'Another teacher only'])->assertOk();

        $viewer = User::factory()->create();
        $response = $this->actingAs($viewer)->get('/users/'.$teacher->id.'/profile')->assertOk()
            ->assertSee('4.5 / 5')->assertSee('(2 ratings)')->assertSee('View learner reviews')->assertSee('Learner reviews')
            ->assertSee($learner->first_name.' '.$learner->last_name)->assertSee($comment)
            ->assertDontSee($comment, false)->assertDontSee('Another teacher only')->assertDontSee($learner->email)
            ->assertDontSee('Private lesson title')->assertDontSee('private-meeting');
        $this->assertStringContainsString('aria-label="5 out of 5 stars"', $response->getContent());
        $this->get('/messages')->assertOk()->assertSee('Write a review')->assertSee('maxlength="500"', false)
            ->assertSee('visible to other signed-in members');
    }

    public function test_reviews_require_a_completed_session_and_confirmed_learning_and_cannot_be_replaced(): void
    {
        [$learner, $teacher, $chat] = $this->reviewSession(completed: false);
        $url = '/messages/conversations/'.$chat->id.'/rating';
        $payload = ['stars' => 4, 'comment' => 'Clear explanations.'];
        $this->actingAs($learner)->postJson($url, $payload)->assertStatus(409);
        $chat->studySession->update(['status' => 'completed']);
        $this->postJson($url, $payload)->assertForbidden();
        $chat->studySession->participants()->update(['confirmed_at' => now()]);
        $this->actingAs($teacher)->postJson($url, $payload)->assertForbidden();
        $this->actingAs(User::factory()->create())->postJson($url, $payload)->assertForbidden();
        $this->actingAs($learner)->postJson($url, $payload)->assertOk();
        $this->postJson($url, ['stars' => 1, 'comment' => 'Replace the original'])->assertStatus(409);
        $this->assertDatabaseCount('session_ratings', 1);
        $this->assertDatabaseHas('session_ratings', ['learner_id' => $learner->id, 'stars' => 4, 'comment' => $payload['comment']]);
    }

    public function test_reviews_are_paginated_newest_first_and_favorite_wording_matches_the_saved_state(): void
    {
        [$learner, $teacher, $chat] = $this->reviewSession();
        for ($number = 1; $number <= 7; $number++) {
            if ($number > 1) [$learner, , $chat] = $this->reviewSession($teacher);
            SessionRating::create(['study_session_id' => $chat->study_session_id, 'learner_id' => $learner->id,
                'teacher_id' => $teacher->id, 'stars' => 4, 'comment' => 'Unique review number '.$number]);
        }
        $viewer = User::factory()->create();
        $url = '/users/'.$teacher->id.'/profile';
        $this->actingAs($viewer)->get($url)->assertOk()->assertSee('(7 ratings)')->assertSee('4.0 / 5')->assertSee('Add to favorites')
            ->assertSeeInOrder(['Unique review number 7', 'Unique review number 6', 'Unique review number 5', 'Unique review number 4', 'Unique review number 3'])
            ->assertDontSee('Unique review number 2')->assertSee('reviews_page=2#learner-reviews', false);
        $this->get($url.'?reviews_page=2')->assertOk()->assertSee('Unique review number 2')->assertSee('Unique review number 1')->assertDontSee('Unique review number 7');
        $this->postJson('/favorite/toggle', ['item_id' => $teacher->id])->assertOk();
        $this->get($url)->assertOk()->assertSee('Favorited');
        $this->postJson('/favorite/toggle', ['item_id' => $teacher->id])->assertOk();
        $this->get($url)->assertOk()->assertSee('Add to favorites');
    }

    public function test_guests_cannot_read_profiles_or_submit_reviews_and_unrated_profiles_have_an_empty_state(): void
    {
        [$learner, $teacher, $chat] = $this->reviewSession();
        $this->get('/users/'.$teacher->id.'/profile')->assertRedirect('/login');
        $this->postJson('/messages/conversations/'.$chat->id.'/rating', ['stars' => 5, 'comment' => 'Guest review'])->assertUnauthorized();
        $this->actingAs($learner)->get('/users/'.$teacher->id.'/profile')->assertOk()->assertSee('No learner ratings yet')->assertSee('No learner reviews yet.');
    }
}
