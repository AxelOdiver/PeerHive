<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingActivityTest extends TestCase
{
    use RefreshDatabase;

    private function chat(): array
    {
        $users = User::factory()->count(3)->create();
        $chat = Conversation::create(['is_group' => true, 'name' => 'Study', 'created_by' => $users[0]->id]);
        $chat->users()->attach($users->modelKeys());
        return [$chat, $users[0], $users[1], $users[2]];
    }

    private function message($chat, $sender, $body = 'Hello'): Message
    {
        return Message::create(['conversation_id' => $chat->id, 'sender_id' => $sender->id, 'body' => $body]);
    }

    public function test_fetching_or_sending_does_not_mark_unseen_messages_read(): void
    {
        [$chat, $author, $reader] = $this->chat();
        $message = $this->message($chat, $author);
        $url = '/messages/conversations/'.$chat->id;
        $this->actingAs($reader)->getJson($url)->assertOk();
        $this->getJson('/messages/conversations')->assertJsonPath('conversations.0.unread_count', 1);
        $this->postJson($url, ['body' => '0'])->assertOk();
        $this->assertSame(0, (int) $chat->users()->find($reader->id)->pivot->last_read_message_id);
        $this->postJson($url.'/read', ['message_id' => $message->id])->assertNoContent();
        $this->getJson('/messages/conversations')->assertJsonPath('conversations.0.unread_count', 0);
    }

    public function test_receipts_are_per_recipient_monotonic_and_preserve_same_second_unreads(): void
    {
        [$chat, $author, $reader, $other] = $this->chat();
        $this->freezeTime();
        $first = $this->message($chat, $author, 'First');
        $second = $this->message($chat, $author, 'Second');
        $url = '/messages/conversations/'.$chat->id;
        $this->actingAs($reader)->postJson($url.'/read', ['message_id' => $first->id])->assertNoContent();
        $this->getJson('/messages/conversations')->assertJsonPath('conversations.0.unread_count', 1);
        $state = $this->actingAs($author)->getJson($url.'/state')->assertOk()->json('members');
        $this->assertEquals($first->id, collect($state)->firstWhere('id', $reader->id)['last_read_message_id']);
        $this->assertNull(collect($state)->firstWhere('id', $other->id)['last_read_message_id']);
        $this->actingAs($reader)->postJson($url.'/read', ['message_id' => $second->id])->assertNoContent();
        $this->postJson($url.'/read', ['message_id' => $first->id])->assertNoContent();
        $this->assertEquals($second->id, $chat->users()->find($reader->id)->pivot->last_read_message_id);
    }

    public function test_read_markers_and_activity_require_membership_and_same_chat(): void
    {
        [$chat, $author, $reader] = $this->chat();
        [$other, $outsider] = $this->chat();
        $message = $this->message($other, $outsider);
        $url = '/messages/conversations/'.$chat->id;
        $this->actingAs($reader)->postJson($url.'/read', ['message_id' => $message->id])->assertUnprocessable();
        $this->actingAs($outsider)->postJson($url.'/read', ['message_id' => $message->id])->assertForbidden();
        $this->postJson($url.'/typing', ['is_typing' => true])->assertForbidden();
        $this->getJson($url.'/state')->assertForbidden();
        $chat->users()->detach($reader->id);
        $this->actingAs($reader)->getJson($url.'/state')->assertForbidden();
        $this->postJson($url.'/typing', ['is_typing' => true])->assertForbidden();
    }

    public function test_typing_expires_stops_on_send_and_is_scoped_to_the_conversation(): void
    {
        [$chat, $author, $reader] = $this->chat();
        [$other] = $this->chat();
        $other->users()->attach([$author->id, $reader->id]);
        $url = '/messages/conversations/'.$chat->id;
        $this->actingAs($author)->postJson($url.'/typing', ['is_typing' => true])->assertNoContent();
        $this->actingAs($reader)->getJson($url.'/state')->assertJsonPath('members.0.is_typing', true);
        $state = $this->getJson('/messages/conversations/'.$other->id.'/state')->assertOk()->json('members');
        $this->assertFalse(collect($state)->firstWhere('id', $author->id)['is_typing']);
        $this->travel(9)->seconds();
        $this->getJson($url.'/state')->assertJsonPath('members.0.is_typing', false);
        $this->actingAs($author)->postJson($url.'/typing', ['is_typing' => true])->assertNoContent();
        $this->postJson($url, ['body' => 'Sent'])->assertOk();
        $this->actingAs($reader)->getJson($url.'/state')->assertJsonPath('members.0.is_typing', false);
        $this->actingAs($author)->postJson($url.'/typing', ['is_typing' => true])->assertNoContent();
        $this->postJson($url.'/typing', ['is_typing' => false])->assertNoContent();
        $this->actingAs($reader)->getJson($url.'/state')->assertJsonPath('members.0.is_typing', false);
    }

    public function test_presence_expires_but_last_seen_is_preserved_and_logout_clears_online(): void
    {
        [$chat, $author, $reader] = $this->chat();
        $url = '/messages/conversations/'.$chat->id.'/state';
        $this->actingAs($author)->postJson('/presence/heartbeat')->assertNoContent();
        $this->actingAs($reader)->getJson($url)->assertJsonPath('members.0.is_online', true);
        $lastSeen = $author->fresh()->last_seen_at->toIso8601String();
        $this->travel(76)->seconds();
        $this->getJson($url)->assertJsonPath('members.0.is_online', false)->assertJsonPath('members.0.last_seen_at', $lastSeen);
        $this->actingAs($author)->postJson('/presence/heartbeat')->assertNoContent();
        $this->post('/logout')->assertRedirect('/login');
        $this->actingAs($reader)->getJson($url)->assertJsonPath('members.0.is_online', false);
    }

    public function test_guests_cannot_publish_activity(): void
    {
        $this->postJson('/presence/heartbeat')->assertUnauthorized();
    }

    public function test_edit_and_unsend_preserve_ownership_and_unsent_messages_cannot_be_edited(): void
    {
        [$chat, $author, $reader] = $this->chat();
        $message = $this->message($chat, $author);
        $url = '/messages/'.$message->id;
        $this->actingAs($reader)->putJson($url, ['body' => 'No'])->assertForbidden();
        $this->deleteJson($url)->assertForbidden();
        $this->actingAs($author)->putJson($url, ['body' => 'Edited'])->assertOk();
        $this->assertNotNull($message->fresh()->edited_at);
        $this->deleteJson($url)->assertOk();
        $this->assertNull($message->fresh()->body);
        $this->putJson($url, ['body' => 'Restore'])->assertUnprocessable();
    }
}
