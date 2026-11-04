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
            ->assertSee('4.5 / 5')->assertSee('(2 ratings)')->assertSee('View all reviews (2)')->assertSee('Learner reviews')->assertDontSee('View learner reviews')
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

    public function test_all_reviews_filter_sort_counts_pagination_and_validation(): void
    {
        $teacher = User::factory()->create();
        foreach ([5, 4, 5, 1, 5, 3, 4, 5] as $index => $stars) {
            [$learner, , $chat] = $this->reviewSession($teacher);
            SessionRating::create(['study_session_id' => $chat->study_session_id, 'learner_id' => $learner->id, 'teacher_id' => $teacher->id,
                'stars' => $stars, 'comment' => 'Review '.$index.' <script>alert(1)</script>']);
        }
        $url = route('users.reviews', $teacher);
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs($teacher);
        $html = $this->getJson($url)->assertOk()->json('html');
        $this->assertStringContainsString('Based on all 8 reviews.', $html);
        $this->assertStringNotContainsString('@else', $html);
        $this->assertStringContainsString('Showing 1–5 of 8 reviews', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('Review 7', $html);
        $this->assertStringNotContainsString('Review 0 ', $html);
        $filtered = $this->getJson($url.'?stars=5&sort=lowest')->assertOk()->json('html');
        $this->assertStringContainsString('Showing 1–4 of 4 reviews', $filtered);
        $this->assertStringContainsString('Based on all 8 reviews.', $filtered);
        $this->assertStringNotContainsString('Review 3 ', $filtered);
        $this->assertStringContainsString('No reviews with this rating yet.', $this->getJson($url.'?stars=2')->assertOk()->json('html'));
        $low = $this->getJson($url.'?sort=lowest')->assertOk()->json('html');
        $this->assertLessThan(strpos($low, 'Review 5 '), strpos($low, 'Review 3 '));
        $high = $this->getJson($url.'?sort=highest')->assertOk()->json('html');
        $this->assertLessThan(strpos($high, 'Review 6 '), strpos($high, 'Review 7 '));
        $page = $this->getJson($url.'?page=2')->assertOk()->json('html');
        $this->assertStringContainsString('Showing 6–8 of 8 reviews', $page);
        $other = User::factory()->create();
        $this->assertStringContainsString('No learner reviews yet.', $this->getJson(route('users.reviews', $other))->assertOk()->json('html'));
        foreach (['stars=0', 'stars=6', 'stars[]=5', 'sort=invalid', 'page=0'] as $invalid) $this->getJson($url.'?'.$invalid)->assertUnprocessable();
    }

    public function test_only_the_author_can_edit_within_24_hours_and_stale_updates_are_rejected(): void
    {
        [$learner, $teacher, $chat] = $this->reviewSession();
        $review = SessionRating::create(['study_session_id' => $chat->study_session_id, 'learner_id' => $learner->id, 'teacher_id' => $teacher->id, 'stars' => 2, 'comment' => 'Original']);
        $url = route('reviews.update', $review);
        $payload = ['stars' => 4, 'comment' => 'Corrected feedback', 'version' => $review->reviewVersion(), 'teacher_id' => $learner->id];
        $this->actingAs($teacher)->patchJson($url, $payload)->assertForbidden();
        $this->actingAs($learner)->getJson(route('users.reviews', $teacher))->assertOk()->assertSee('Edit your review');
        $this->travel(1)->minutes();
        $this->patchJson($url, $payload)->assertOk();
        $this->assertDatabaseHas('session_ratings', ['id' => $review->id, 'stars' => 4, 'comment' => 'Corrected feedback', 'teacher_id' => $teacher->id]);
        $this->patchJson($url, $payload)->assertStatus(409);
        $this->getJson(route('users.reviews', $teacher))->assertOk()->assertSee('Edited');
        $payload['version'] = $review->fresh()->reviewVersion();
        $this->patchJson($url, array_replace($payload, ['stars' => 0]))->assertUnprocessable();
        $this->patchJson($url, array_replace($payload, ['comment' => str_repeat('x', 501)]))->assertUnprocessable();
        $this->travel(24)->hours();
        $this->patchJson($url, $payload)->assertForbidden();
        $this->assertDatabaseCount('session_ratings', 1);
    }

    public function test_recipient_reports_preserve_a_snapshot_and_only_admins_can_remove_reviews(): void
    {
        [$learner, $teacher, $chat] = $this->reviewSession();
        $review = SessionRating::create(['study_session_id' => $chat->study_session_id, 'learner_id' => $learner->id, 'teacher_id' => $teacher->id, 'stars' => 1, 'comment' => 'Reported original']);
        $payload = ['reportable_id' => $review->id, 'reportable_type' => SessionRating::class, 'reason' => 'Harassment or threats', 'details' => 'Please review.'];
        $this->actingAs($learner)->postJson('/reports', $payload)->assertUnprocessable();
        $this->actingAs(User::factory()->create())->postJson('/reports', $payload)->assertForbidden();
        $this->actingAs($teacher)->postJson('/reports', array_replace($payload, ['reason' => 'I disagree']))->assertUnprocessable();
        $this->postJson('/reports', $payload)->assertOk();
        $this->postJson('/reports', $payload)->assertUnprocessable();
        $this->assertDatabaseHas('session_ratings', ['id' => $review->id]);
        $report = \App\Models\Report::where('reportable_type', SessionRating::class)->firstOrFail();
        $this->assertStringContainsString('Reported original', $report->details);
        $this->actingAs($learner)->patchJson(route('reviews.update', $review), ['stars' => 5, 'comment' => 'Changed after report', 'version' => $review->reviewVersion()])->assertOk();
        $this->assertStringContainsString('Reported original', $report->fresh()->details);
        $this->deleteJson(route('admin.reports.destroyContent', $report))->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.reports.index'))->assertOk()->assertSee('Learner review')->assertSee('Changed after report');
        $this->delete(route('admin.reports.destroyContent', $report))->assertRedirect();
        $this->assertDatabaseHas('session_ratings', ['id' => $review->id]);
        $this->assertDatabaseHas('reports', ['id' => $report->id, 'status' => 'resolved']);
        $this->assertSame(0, $teacher->teachingRatings()->count());
        $this->patchJson(route('admin.reports.dismiss', $report))->assertStatus(409);
        $this->actingAs($learner)->postJson('/messages/conversations/'.$chat->id.'/rating', ['stars' => 1])->assertStatus(409);
        $this->patchJson(route('reviews.update', $review), ['stars' => 1, 'version' => $review->fresh()->reviewVersion()])->assertForbidden();
    }
}
