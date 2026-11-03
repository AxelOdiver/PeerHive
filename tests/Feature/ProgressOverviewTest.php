<?php

namespace Tests\Feature;

use App\Models\{CatalogEntry, Conversation, SessionActivity, StudySession, TopicLearningPlan, User, UserSubjectQualification};
use App\Support\TopicProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_replaces_goal_card_and_timezone_settings_preserve_saved_goals(): void
    {
        $this->withoutVite();
        $user=User::factory()->create(['learning_interests'=>['Python Basics'],'learning_goals'=>['learning'=>2,'teaching'=>1,'hours'=>6]]);
        $this->actingAs($user)->get('/progress')->assertOk()->assertSee('My Skill Tree')->assertSee('Open Skill Tree')
            ->assertSee('Python Basics')->assertSee('Not started')->assertSee('Progress settings')
            ->assertSee('This week’s activity')->assertDontSee('Your weekly goals')->assertDontSee('learningGoalsModal');
        $this->put(route('progress.settings'),['timezone'=>'Asia/Tokyo','learning'=>99])->assertRedirect();
        $this->assertSame('Asia/Tokyo',$user->fresh()->learning_timezone);
        $this->assertSame(['learning'=>2,'teaching'=>1,'hours'=>6],$user->fresh()->learning_goals);
        $this->putJson(route('progress.settings'),['timezone'=>'invalid'])->assertUnprocessable();
        $this->assertSame('Asia/Tokyo',$user->fresh()->learning_timezone);
    }

    public function test_new_approved_subjects_and_topics_appear_without_tree_configuration_or_profile_changes(): void
    {
        $this->withoutVite();
        $user=User::factory()->create();$admin=User::factory()->create(['role'=>'admin']);
        $this->actingAs($user)->post('/catalog/suggestions',['kind'=>'topic','name'=>'Introduction to Photography','subject_name'=>'Photography'])->assertRedirect();
        $subject=CatalogEntry::where('kind','subject')->where('name','Photography')->firstOrFail();
        $topic=CatalogEntry::where('kind','topic')->where('name','Introduction to Photography')->firstOrFail();
        $this->get(route('progress.skill-tree'))->assertDontSee('Photography');
        $this->actingAs($admin)->post('/admin/catalog/'.$subject->id,['status'=>'approved'])->assertRedirect();
        $this->actingAs($user)->get(route('progress.skill-tree',['subject'=>$subject->id]))->assertOk()->assertSee('Photography')
            ->assertSee('No approved topics in this subject yet.')->assertDontSee('Introduction to Photography');
        $this->actingAs($admin)->post('/admin/catalog/'.$topic->id,['status'=>'approved'])->assertRedirect();
        $this->actingAs($user)->get(route('progress.skill-tree',['subject'=>$subject->id]))->assertOk()
            ->assertSee('Introduction to Photography')->assertSee('Not started')->assertSee('Not defined yet.');
        $this->assertNull(TopicLearningPlan::where('topic_id',$topic->id)->first());
    }

    public function test_retiring_placeholder_preserves_topic_ids_qualifications_and_activity_progress(): void
    {
        $this->withoutVite();
        $legacy=CatalogEntry::create(['kind'=>'subject','name'=>'Existing teaching subjects','name_key'=>'existing teaching subjects']);
        $legacy->forceFill(['status'=>'approved'])->save();
        $topic=CatalogEntry::create(['kind'=>'topic','name'=>'Coding','name_key'=>'coding','parent_id'=>$legacy->id]);
        $topic->forceFill(['status'=>'approved'])->save();
        $learner=User::factory()->create(['learning_interests'=>['Coding']]);$teacher=User::factory()->create();
        $qualification=UserSubjectQualification::create(['user_id'=>$teacher->id,'subject_name'=>'Coding','status'=>'approved','proof_file_path'=>'proof.pdf']);
        $outcome=['key'=>TopicLearningPlan::outcomeKey('Apply a coding concept'),'text'=>'Apply a coding concept'];
        $plan=TopicLearningPlan::create(['topic_id'=>$topic->id,'outcomes'=>[$outcome]]);
        $session=StudySession::create(['host_id'=>$teacher->id,'title'=>'Coding session','subject'=>'Coding','format'=>'tutor','starts_at'=>now()->subHour(),'ends_at'=>now(),'timezone'=>'UTC','capacity'=>2,'meeting_method'=>'online','meeting_details'=>'https://meet.google.com/abc-defg-hij','status'=>'completed']);
        $chat=Conversation::create(['created_by'=>$teacher->id,'is_group'=>false,'name'=>'Coding','study_session_id'=>$session->id,
            'session_context'=>['type'=>'learning','learner'=>$learner->id,'teacher'=>$teacher->id,'subject'=>'Coding']]);
        $chat->users()->attach([$learner->id,$teacher->id]);
        $session->participants()->create(['user_id'=>$learner->id,'learning_minutes'=>60,'teaching_minutes'=>0,'confirmed_at'=>now()]);
        $session->participants()->create(['user_id'=>$teacher->id,'learning_minutes'=>0,'teaching_minutes'=>60,'confirmed_at'=>now()]);
        $activity=SessionActivity::create(['conversation_id'=>$chat->id,'study_session_id'=>$session->id,'teacher_id'=>$teacher->id,'learner_id'=>$learner->id,'topic_id'=>$topic->id,'topic_name'=>'Coding','title'=>'Coding practice','instructions'=>'Apply the concept','passing_criteria'=>'Concept applied','covered_outcomes'=>[$outcome],'status'=>'passed']);
        $migration=require base_path('database/migrations/2026_11_04_000002_retire_legacy_teaching_category.php');
        $migration->up();
        $this->assertDatabaseMissing('catalog_entries',['id'=>$legacy->id]);
        $this->assertSame('Programming',$topic->fresh()->parent->name);
        $this->assertSame($topic->id,$activity->fresh()->topic_id);
        $this->assertDatabaseHas('user_subject_qualifications',['id'=>$qualification->id,'subject_name'=>'Coding','status'=>'approved']);
        $this->assertSame($plan->id,TopicLearningPlan::where('topic_id',$topic->id)->first()->id);
        $progress=collect(TopicProgress::forUser($learner))->first(fn($node)=>$node['topic']->id===$topic->id);
        $this->assertSame('Completed',$progress['status']);
        $this->assertSame(60,$progress['minutes']);
        $this->actingAs($learner)->get(route('progress.skill-tree',['topic'=>$topic->id]))->assertOk()->assertSee('Coding')->assertDontSee('Existing teaching subjects');
        $this->get('/profile')->assertOk()->assertDontSee('Existing teaching subjects');
        $this->post('/catalog/suggestions',['kind'=>'topic','name'=>'A topic','subject_name'=>'Existing teaching subjects'])->assertSessionHasErrors('subject_name');
        $this->actingAs(User::factory()->create(['role'=>'admin']))->get('/admin/catalog')->assertOk()->assertDontSee('Existing teaching subjects');
    }
}
