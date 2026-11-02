<?php
namespace Tests\Feature;
use App\Models\{User, UserSubjectQualification, Community, Post, Schedule};
use App\Support\PeerMatching;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeerMatchingTest extends TestCase {
    use RefreshDatabase;
    public function test_only_relevant_approved_peers_are_ranked_and_limited_to_six(): void {
        $learner = User::factory()->create();
        $learner->forceFill(['learning_interests' => [' coding ']])->save();
        Schedule::create(['user_id'=>$learner->id,'day_index'=>1,'start_time'=>'09:00','end_time'=>'11:00']);
        $peers = User::factory()->count(8)->create();
        foreach ($peers as $peer) UserSubjectQualification::create(['user_id'=>$peer->id,'subject_name'=>'Coding','status'=>'approved','proof_file_path'=>'proof.pdf']);
        $preferred = $peers->last();
        Schedule::create(['user_id'=>$preferred->id,'day_index'=>1,'start_time'=>'10:00','end_time'=>'12:00']);
        $wrong = User::factory()->create();
        UserSubjectQualification::create(['user_id'=>$wrong->id,'subject_name'=>'Design','status'=>'approved','proof_file_path'=>'proof.pdf']);
        $pending = User::factory()->create();
        UserSubjectQualification::create(['user_id'=>$pending->id,'subject_name'=>'Coding','status'=>'pending','proof_file_path'=>'proof.pdf']);
        $matches = PeerMatching::recommend($learner);
        $this->assertCount(6, $matches);
        $this->assertEquals($preferred->id, $matches->first()->id);
        $this->assertFalse($matches->contains('id', $wrong->id));
        $this->assertFalse($matches->contains('id', $pending->id));
        $this->assertArrayNotHasKey('learning_interests', $learner->toArray());
        $this->withoutVite();
        $this->actingAs($learner)->get('/dashboard')->assertOk()
            ->assertSee('Matches your interests:')->assertSee('Your weekly availability overlaps.');
        $learner->forceFill(['learning_interests'=>[]])->save();
        $this->assertCount(0, PeerMatching::recommend($learner));
    }
    public function test_dashboard_feed_only_contains_member_or_owned_communities(): void {
        $this->withoutVite();
        $user=User::factory()->create();$owner=User::factory()->create();
        foreach (['Joined','Unjoined','Owned'] as $name) {
            $community=Community::create(['user_id'=>$name==='Owned'?$user->id:$owner->id,'name'=>$name,'subject'=>'Coding','visibility'=>'private','member_limit'=>20]);
            if ($name==='Joined') $community->members()->attach($user->id);
            Post::create(['community_id'=>$community->id,'user_id'=>$owner->id,'title'=>$name.' post','body'=>'Study discussion']);
        }
        $this->actingAs($user)->get('/dashboard')->assertOk()->assertSee('Joined post')->assertSee('Owned post')->assertDontSee('Unjoined post')->assertSee('Tell us what you want to learn');
    }
    public function test_learning_interests_save_from_profile_form(): void {
        $user=User::factory()->create();
        $this->actingAs($user)->putJson('/profile', ['first_name'=>$user->first_name,'last_name'=>$user->last_name,'email'=>$user->email,'learning_interests'=>'Python Basics, Graphic Design'])->assertOk();
        $this->assertSame(['Python Basics','Graphic Design'], $user->fresh()->learning_interests);
    }
}
