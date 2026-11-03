<?php

namespace Tests\Feature;

use App\Models\{CatalogEntry, Conversation, SessionActivity, StudySession, TopicLearningPlan, User};
use App\Support\TopicProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionActivityTest extends TestCase
{
    use RefreshDatabase;

    private function chat(string $type = 'learning', bool $complete = true): array
    {
        $this->withoutVite();
        $learner = User::factory()->create(); $teacher = User::factory()->create();
        $session = StudySession::create(['host_id'=>$teacher->id, 'title'=>'Python learning', 'subject'=>'Python Basics', 'format'=>$type === 'learning' ? 'tutor' : 'swap',
            'starts_at'=>now()->addDay(), 'ends_at'=>now()->addDay()->addHour(), 'timezone'=>'UTC', 'capacity'=>2, 'meeting_method'=>'online', 'meeting_details'=>'https://meet.google.com/abc-defg-hij', 'status'=>$complete ? 'completed' : 'scheduled']);
        $chat = Conversation::create(['created_by'=>$teacher->id, 'is_group'=>false, 'name'=>'Learn Python', 'study_session_id'=>$session->id,
            'session_context'=>['learner'=>$learner->id,'teacher'=>$teacher->id,'type'=>$type,'subject'=>'Python Basics','offered_subject'=>$type === 'learning' ? null : 'Java Basics', 'state'=>$complete ? 'completed' : 'scheduled', 'revision'=>0,
                'message'=>null, 'proposal'=>['duration'=>60,'teaching_minutes'=>30,'starts_at'=>now()->addDay()->toIso8601String(),'timezone'=>'UTC','meeting_method'=>'online','meeting_details'=>'https://meet.google.com/abc-defg-hij'], 'confirmed'=>$complete ? [$learner->id,$teacher->id] : []]]);
        $chat->users()->attach([$learner->id,$teacher->id]);
        $session->participants()->create(['user_id'=>$learner->id,'learning_minutes'=>$type === 'learning' ? 60 : 30,'teaching_minutes'=>$type === 'learning' ? 0 : 30,'confirmed_at'=>$complete ? now() : null]);
        $session->participants()->create(['user_id'=>$teacher->id,'learning_minutes'=>$type === 'learning' ? 0 : 30,'teaching_minutes'=>$type === 'learning' ? 60 : 30,'confirmed_at'=>$complete ? now() : null]);
        return [$learner,$teacher,$chat];
    }

    private function assign(User $teacher, Conversation $chat, array $overrides = []): SessionActivity
    {
        $this->actingAs($teacher)->postJson(route('session-activities.store',$chat), array_replace([
            'title'=>'Introduce yourself','instructions'=>'Read a name using input() and print an introduction.', 'passing_criteria'=>'Stores the input in a variable and prints it.',
        ],$overrides))->assertOk();
        return SessionActivity::where('conversation_id',$chat->id)->where('teacher_id',$teacher->id)->firstOrFail();
    }

    private function progress(User $user, string $topic = 'Python Basics'): array
    {
        return collect(TopicProgress::forUser($user))->first(fn($node)=>$node['topic']->name === $topic);
    }

    public function test_activity_lifecycle_requires_mutual_completion_and_preserves_revision_history(): void
    {
        [$learner,$teacher,$chat]=$this->chat('learning',false);
        $outcome=['key'=>TopicLearningPlan::outcomeKey('Uses input and variables'),'text'=>'Uses input and variables'];
        $topic=CatalogEntry::where('name','Python Basics')->firstOrFail();
        TopicLearningPlan::where('topic_id',$topic->id)->update(['outcomes'=>json_encode([$outcome])]);
        $activity=$this->assign($teacher,$chat,['outcomes'=>[$outcome['key']]]);
        $submit=route('session-activities.submit',[$chat,$activity]);$review=route('session-activities.review',[$chat,$activity]);
        $answer=['revision'=>0,'answer'=>'name = input(); print(name)'];
        $this->actingAs($learner)->postJson($submit,$answer)->assertStatus(409);
        $sessionUrl='/messages/conversations/'.$chat->id.'/session';
        $this->postJson($sessionUrl,['action'=>'finish','revision'=>0,'actual_minutes'=>60])->assertOk();
        $this->postJson($submit,$answer)->assertStatus(409);
        $this->actingAs($teacher)->postJson($sessionUrl,['action'=>'finish','revision'=>1])->assertOk()->assertJsonPath('session_finished',true);
        $points=\Illuminate\Support\Facades\DB::table('point_entries')->sum('points');
        $this->actingAs($learner)->postJson($submit,$answer)->assertOk();
        $this->postJson($submit,$answer)->assertStatus(409);
        $this->actingAs($teacher)->postJson($review,['revision'=>1,'decision'=>'revision','feedback'=>'Use a full sentence.'])->assertOk();
        $this->postJson($review,['revision'=>1,'decision'=>'passed','feedback'=>'Changed mind'])->assertStatus(409);
        $this->actingAs($learner)->postJson($submit,['revision'=>2,'answer'=>'name=input(); print("Hello " + name)','project_url'=>'https://example.com/project'])->assertOk();
        $this->actingAs($teacher)->postJson($review,['revision'=>3,'decision'=>'passed','feedback'=>'All criteria and the selected outcome are met.'])->assertOk();
        $this->assertSame('Completed',$this->progress($learner)['status']);
        $this->assertSame('Not started',$this->progress($teacher)['status']);
        $this->assertSame(2,$activity->submissions()->count());
        $this->assertDatabaseHas('activity_submissions',['attempt'=>1,'feedback'=>'Use a full sentence.','decision'=>'revision']);
        $this->assertEquals($points,\Illuminate\Support\Facades\DB::table('point_entries')->sum('points'));
        $this->actingAs($learner)->get(route('session-activities.index',$chat))->assertOk()->assertSee('Use a full sentence.')->assertSee('Activity passed');
        $this->postJson('/messages/conversations/'.$chat->id,['body'=>'Still cannot chat'])->assertStatus(409);
        $this->get(route('progress.skill-tree',['subject'=>$topic->parent_id,'topic'=>$topic->id]))->assertOk()->assertSee('Completed')->assertSee('Introduce yourself');
        $this->assertDatabaseHas('bell_notifications',['user_id'=>$teacher->id,'kind'=>'activity']);
        $this->assertDatabaseHas('bell_notifications',['user_id'=>$learner->id,'body'=>'Your activity passed. View your topic progress.']);
    }

    public function test_roles_cross_chat_access_duplicates_and_urls_are_enforced(): void
    {
        [$learner,$teacher,$chat]=$this->chat();
        $activity=$this->assign($teacher,$chat);$outsider=User::factory()->create();
        $base=route('session-activities.index',$chat);
        $this->actingAs($outsider)->getJson($base)->assertForbidden();
        $this->postJson($base,['title'=>'Bad','instructions'=>'Bad','passing_criteria'=>'Bad'])->assertForbidden();
        $this->actingAs($learner)->postJson($base,['title'=>'Bad','instructions'=>'Bad','passing_criteria'=>'Bad'])->assertForbidden();
        $this->postJson(route('session-activities.review',[$chat,$activity]),['revision'=>0,'decision'=>'passed','feedback'=>'Self pass'])->assertForbidden();
        $this->actingAs($teacher)->postJson($base,['title'=>'Duplicate','instructions'=>'Another','passing_criteria'=>'Another'])->assertStatus(409);
        $this->postJson(route('session-activities.submit',[$chat,$activity]),['revision'=>0,'answer'=>'Teacher answer'])->assertForbidden();
        [,,$other]=$this->chat();
        $this->actingAs($learner)->getJson(route('session-activities.index',$chat))->assertOk();
        $other->users()->attach($learner);
        $this->postJson(route('session-activities.submit',[$other,$activity]),['revision'=>0,'answer'=>'Wrong chat'])->assertNotFound();
        $this->postJson(route('session-activities.submit',[$chat,$activity]),['revision'=>0,'answer'=>'Answer','project_url'=>'javascript:alert(1)'])->assertUnprocessable();
        $this->actingAs($teacher)->postJson($base,['title'=>'Bad link','instructions'=>'Bad','passing_criteria'=>'Bad','reference_url'=>'file:///secret'])->assertUnprocessable();
        $this->assertSame('assigned',$activity->fresh()->status);
        $this->assertDatabaseCount('activity_submissions',0);
    }

    public function test_swap_tracks_each_teaching_direction_separately_and_never_auto_completes_unconfigured_topics(): void
    {
        [$learner,$teacher,$chat]=$this->chat('skill_swap');
        $python=$this->assign($teacher,$chat);$java=$this->assign($learner,$chat);
        $this->assertSame('Python Basics',$python->topic_name);$this->assertSame('Java Basics',$java->topic_name);
        $this->actingAs($learner)->postJson(route('session-activities.submit',[$chat,$python]),['revision'=>0,'answer'=>'Python solution'])->assertOk();
        $this->actingAs($teacher)->postJson(route('session-activities.review',[$chat,$python]),['revision'=>1,'decision'=>'passed','feedback'=>'Practice passed'])->assertOk();
        $this->assertSame('Activity passed',$this->progress($learner)['status']);
        $this->assertSame('Practicing',$this->progress($teacher,'Java Basics')['status']);
        $this->assertSame('Not started',$this->progress($learner,'Java Basics')['status']);
        $this->assertSame('assigned',$java->fresh()->status);
        $this->assertDatabaseCount('session_activities',2);
    }

    public function test_no_confirmed_learning_time_and_cancelled_sessions_cannot_credit_activities(): void
    {
        [$learner,$teacher,$chat]=$this->chat('skill_swap');
        $chat->studySession->participants()->where('user_id',$teacher->id)->update(['learning_minutes'=>0]);
        $this->actingAs($learner)->postJson(route('session-activities.store',$chat),['title'=>'No time','instructions'=>'No','passing_criteria'=>'No'])->assertStatus(409);
        $chat->studySession->update(['status'=>'cancelled']);
        $this->actingAs($teacher)->postJson(route('session-activities.store',$chat),['title'=>'Cancelled','instructions'=>'No','passing_criteria'=>'No'])->assertStatus(409);
        $this->assertDatabaseCount('session_activities',0);
    }

    public function test_outcome_coverage_is_server_checked_and_assignment_cannot_be_edited(): void
    {
        [$learner,$teacher,$chat]=$this->chat();
        $this->actingAs($teacher)->postJson(route('session-activities.store',$chat),[
            'title'=>'Forged outcome','instructions'=>'Test','passing_criteria'=>'Test','outcomes'=>[str_repeat('a',64)],
        ])->assertUnprocessable();
        $this->assertDatabaseCount('session_activities',0);
        $activity=$this->assign($teacher,$chat);
        $this->actingAs($learner)->postJson(route('session-activities.submit',[$chat,$activity]),[
            'revision'=>0,'answer'=>'<script>alert("test")</script>', 'title'=>'Changed', 'instructions'=>'Changed', 'outcomes'=>[str_repeat('a',64)],
        ])->assertOk();
        $this->assertSame('Read a name using input() and print an introduction.',$activity->fresh()->instructions);
        $this->assertSame([],$activity->fresh()->covered_outcomes);
        $this->actingAs($teacher)->get(route('session-activities.index',$chat))->assertOk()
            ->assertDontSee('<script>alert("test")</script>',false)->assertSee('&lt;script&gt;',false);
    }

    public function test_passed_work_preserves_its_outcome_snapshot_when_admin_changes_requirements(): void
    {
        [$learner,$teacher,$chat]=$this->chat();
        $topic=CatalogEntry::where('name','Python Basics')->firstOrFail();
        $plan=TopicLearningPlan::where('topic_id',$topic->id)->firstOrFail();
        $one=['key'=>TopicLearningPlan::outcomeKey('Read input'),'text'=>'Read input'];
        $plan->update(['outcomes'=>[$one]]);
        $activity=$this->assign($teacher,$chat,['outcomes'=>[$one['key']]]);
        $this->actingAs($learner)->postJson(route('session-activities.submit',[$chat,$activity]),['revision'=>0,'answer'=>'Solution'])->assertOk();
        $this->actingAs($teacher)->postJson(route('session-activities.review',[$chat,$activity]),['revision'=>1,'decision'=>'passed','feedback'=>'Good input'])->assertOk();
        $this->assertSame('Completed',$this->progress($learner)['status']);
        $admin=User::factory()->create(['role'=>'admin']);
        $this->actingAs($admin)->put(route('admin.learning-paths.update',$topic),['revision'=>$plan->revision,'sort_order'=>2,'description'=>'Updated description','outcomes_text'=>"Read input\nPrint a sentence"])->assertRedirect();
        $this->assertSame('Activity passed',$this->progress($learner)['status']);
        $this->assertSame([$one],$activity->fresh()->covered_outcomes);
        $this->assertSame('Good input',$activity->submissions()->first()->feedback);
        $this->actingAs($teacher)->postJson(route('session-activities.store',$chat),['title'=>'Overwrite','instructions'=>'Other','passing_criteria'=>'Other','outcomes'=>[$one['key']]])->assertStatus(409);
    }
}
