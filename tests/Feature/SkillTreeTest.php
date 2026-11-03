<?php

namespace Tests\Feature;

use App\Models\{CatalogEntry, TopicLearningPlan, User, UserSubjectQualification};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_paths_require_authorization_and_reject_cycles_and_stale_updates(): void
    {
        $this->withoutVite();
        $user=User::factory()->create();$admin=User::factory()->create(['role'=>'admin']);
        $basics=CatalogEntry::where('name','Python Basics')->firstOrFail();$functions=CatalogEntry::where('name','Python Functions')->firstOrFail();
        $sql=CatalogEntry::where('name','SQL Basics')->firstOrFail();
        $url=route('admin.learning-paths.update',$basics);
        $payload=['revision'=>0,'sort_order'=>0,'outcomes_text'=>'Use variables'];
        $this->actingAs($user)->get(route('admin.learning-paths'))->assertForbidden();
        $this->putJson($url,$payload)->assertForbidden();
        $this->actingAs($admin)->get(route('admin.learning-paths'))->assertOk()->assertSee('Skill Tree learning paths');
        $this->putJson($url,$payload+['prerequisite_id'=>$basics->id])->assertUnprocessable();
        $this->putJson($url,$payload+['prerequisite_id'=>$functions->id])->assertUnprocessable();
        $this->putJson($url,$payload+['prerequisite_id'=>$sql->id])->assertUnprocessable();
        $this->put($url,$payload)->assertRedirect();
        $this->putJson($url,$payload)->assertStatus(409);
        $this->putJson($url,['revision'=>1,'sort_order'=>0,'outcomes_text'=>"Use variables\n use VARIABLES "])->assertUnprocessable();
        $this->assertSame('Use variables',TopicLearningPlan::where('topic_id',$basics->id)->first()->outcomes[0]['text']);
        $this->actingAs($user)->get(route('progress.skill-tree',['subject'=>$basics->parent_id,'topic'=>$basics->id]))->assertOk()->assertSee('Not started')->assertSee('Use variables');
        $this->get('/progress')->assertOk()->assertSee('Skill Tree');
    }

    public function test_find_partner_filters_by_topic_without_changing_profile_interests(): void
    {
        $this->withoutVite();
        $user=User::factory()->create(['learning_interests'=>['SQL Basics']]);
        $teacher=User::factory()->create();
        UserSubjectQualification::create(['user_id'=>$teacher->id,'subject_name'=>'Python Basics','status'=>'approved','proof_file_path'=>'proof.pdf']);
        $topic=CatalogEntry::where('name','Python Basics')->firstOrFail();
        $this->actingAs($user)->get(route('dashboard',['learn_topic'=>$topic->id]))->assertOk()
            ->assertViewHas('students',fn($students)=>$students->contains('id',$teacher->id))->assertSee('Teachers for:');
        $this->assertSame(['SQL Basics'],$user->fresh()->learning_interests);
        $this->get('/dashboard')->assertViewHas('students',fn($students)=>$students->isEmpty());
    }
}
