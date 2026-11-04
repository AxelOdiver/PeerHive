<?php

namespace Tests\Feature;

use App\Models\{Conversation, Message, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DirectionalBlockingTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_blocker_cannot_send_and_blocked_period_messages_stay_hidden_after_unblock(): void
    {
        Storage::fake('local');
        $a = User::factory()->create(); $b = User::factory()->create();
        $chat = Conversation::create(['created_by' => $a->id]); $chat->users()->attach([$a->id, $b->id]);
        $url = '/messages/conversations/'.$chat->id;
        $old = Message::create(['conversation_id' => $chat->id, 'sender_id' => $b->id, 'body' => 'Earlier message']);
        $this->actingAs($a)->postJson('/users/'.$b->id.'/block')->assertOk();
        $this->postJson($url, ['body' => 'Do not send'])->assertForbidden();
        $this->getJson($url)->assertJsonPath('blocked_by_me', true)->assertJsonFragment(['body' => 'Earlier message']);
        $hidden = $this->actingAs($b)->postJson($url, ['body' => 'Hidden during block', 'attachment' => UploadedFile::fake()->createWithContent('notes.txt', 'hidden notes')])->assertOk()->json('message.id');
        $this->getJson($url)->assertJsonPath('blocked_by_me', false)->assertJsonFragment(['body' => 'Hidden during block']);
        $this->actingAs($a)->getJson($url)->assertJsonMissing(['body' => 'Hidden during block']);
        $this->getJson($url.'?q=Hidden')->assertJsonPath('total_results', 0);
        $this->getJson($url.'?around_id='.$hidden)->assertNotFound();
        $this->get('/private/messages/'.$hidden.'/attachment')->assertNotFound();
        $this->getJson('/messages/conversations')->assertJsonFragment(['last_message' => 'Earlier message']);
        $this->getJson('/messages/unread-count')->assertJsonMissing(['text' => 'Hidden during block']);
        $this->deleteJson('/users/'.$b->id.'/block')->assertOk();
        $this->getJson($url)->assertJsonPath('blocked_by_me', false)->assertJsonMissing(['body' => 'Hidden during block']);
        $this->postJson($url, ['body' => 'Can send again'])->assertOk();
        $this->actingAs($b)->postJson($url, ['body' => 'Visible after unblock'])->assertOk();
        $this->actingAs($a)->getJson($url)->assertJsonFragment(['body' => 'Visible after unblock'])->assertJsonMissing(['body' => 'Hidden during block']);
        $this->get('/private/messages/'.$hidden.'/attachment')->assertNotFound();
        $this->postJson($url, ['body' => 'Guess a hidden reply', 'reply_to_id' => $hidden])->assertUnprocessable();
    }

    public function test_group_messages_are_unaffected_and_unblock_only_removes_your_own_block(): void
    {
        $a = User::factory()->create(); $b = User::factory()->create(); $c = User::factory()->create();
        $dm = Conversation::create(['created_by' => $a->id]); $dm->users()->attach([$a->id, $b->id]);
        $group = Conversation::create(['created_by' => $a->id, 'is_group' => true]); $group->users()->attach([$a->id, $b->id, $c->id]);
        $this->actingAs($a)->postJson('/users/'.$b->id.'/block')->assertOk();
        $this->actingAs($b)->postJson('/users/'.$a->id.'/block')->assertOk();
        $this->postJson('/messages/conversations/'.$group->id, ['body' => 'Shared group message'])->assertOk();
        $this->actingAs($a)->getJson('/messages/conversations/'.$group->id)->assertJsonFragment(['body' => 'Shared group message']);
        $this->deleteJson('/users/'.$b->id.'/block')->assertOk();
        $this->postJson('/messages/conversations/'.$dm->id, ['body' => 'Allowed but hidden for B'])->assertOk();
        $this->actingAs($b)->postJson('/messages/conversations/'.$dm->id, ['body' => 'Still blocked by B'])->assertForbidden();
        $this->getJson('/messages/conversations/'.$dm->id)->assertJsonMissing(['body' => 'Allowed but hidden for B']);
    }

}
