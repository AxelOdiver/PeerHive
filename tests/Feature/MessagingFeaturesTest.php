<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessagingFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private function chat(): array
    {
        $users = User::factory()->count(3)->create();
        $chat = Conversation::create(['is_group' => true, 'name' => 'Study group', 'created_by' => $users[0]->id]);
        $chat->users()->attach($users->modelKeys());
        return [$chat, $users[0], $users[1]];
    }

    public function test_messaging_page_renders_advanced_controls(): void
    {
        $this->actingAs(User::factory()->create())->get('/messages')->assertOk()
            ->assertSee('chatSearchInput')->assertSee('newChatSearchInput')->assertSee('groupMembersModal');
    }

    public function test_reactions_toggle_and_reject_non_members_and_unsent_messages(): void
    {
        [$chat, $author, $peer] = $this->chat();
        $message = Message::create(['conversation_id' => $chat->id, 'sender_id' => $author->id, 'body' => 'Hello']);
        $url = '/messages/'.$message->id.'/reactions';
        $this->actingAs($peer)->postJson($url, ['emoji' => '👍'])->assertOk()->assertJsonPath('reactions.0.count', 1);
        $this->postJson($url, ['emoji' => '👍'])->assertOk()->assertJsonCount(0, 'reactions');
        $this->postJson($url, ['emoji' => 'invalid'])->assertUnprocessable();
        $this->actingAs(User::factory()->create())->postJson($url, ['emoji' => '👍'])->assertForbidden();
        $message->update(['is_unsent' => true]);
        $this->actingAs($peer)->postJson($url, ['emoji' => '👍'])->assertUnprocessable();
    }

    public function test_replies_must_target_an_available_message_in_the_same_chat(): void
    {
        [$chat, $author] = $this->chat();
        [$other] = $this->chat();
        $foreign = Message::create(['conversation_id' => $other->id, 'sender_id' => $author->id, 'body' => 'Private']);
        $url = '/messages/conversations/'.$chat->id;
        $this->actingAs($author)->postJson($url, ['body' => 'Reply', 'reply_to_id' => $foreign->id])->assertUnprocessable();
        $local = Message::create(['conversation_id' => $chat->id, 'sender_id' => $author->id, 'body' => 'Local']);
        $this->postJson($url, ['body' => 'Reply', 'reply_to_id' => $local->id])->assertOk()->assertJsonPath('message.reply_to.body', 'Local');
        $local->update(['is_unsent' => true]);
        $this->postJson($url, ['body' => 'Reply', 'reply_to_id' => $local->id])->assertUnprocessable();
    }

    public function test_search_pages_through_older_matches_without_marking_them_read(): void
    {
        [$chat, $author, $peer] = $this->chat();
        for ($i = 0; $i < 35; $i++) {
            Message::create(['conversation_id' => $chat->id, 'sender_id' => $author->id, 'body' => 'homework '.$i]);
        }
        Message::create(['conversation_id' => $chat->id, 'sender_id' => $author->id, 'body' => 'unrelated']);
        $url = '/messages/conversations/'.$chat->id;
        $page = $this->actingAs($peer)->getJson($url.'?q=homework')->assertOk()->assertJsonCount(30, 'messages')->assertJsonPath('has_more', true)->assertJsonPath('total_results', 35);
        $this->getJson($url.'?q=homework&before_id='.$page->json('messages.0.id'))->assertOk()->assertJsonCount(5, 'messages')->assertJsonPath('has_more', false);
        $this->assertNull($chat->users()->find($peer->id)->pivot->last_read_at);
        $this->actingAs(User::factory()->create())->getJson($url.'?q=homework')->assertForbidden();
    }

    public function test_search_result_opens_surrounding_history_and_can_page_forward(): void
    {
        [$chat, $author, $peer] = $this->chat();
        $ids = [];
        for ($i = 0; $i < 80; $i++) {
            $ids[] = Message::create(['conversation_id' => $chat->id, 'sender_id' => $author->id, 'body' => 'Message '.$i])->id;
        }
        $url = '/messages/conversations/'.$chat->id;
        $page = $this->actingAs($peer)->getJson($url.'?around_id='.$ids[40])->assertOk()
            ->assertJsonCount(30, 'messages')->assertJsonPath('has_more', true)->assertJsonPath('has_newer', true);
        $this->assertContains($ids[40], array_column($page->json('messages'), 'id'));
        $this->assertSame($ids[25], $page->json('messages.0.id'));
        $this->getJson($url.'?after_id='.$ids[54])->assertOk()->assertJsonCount(25, 'messages')->assertJsonPath('has_newer', false);
        $this->assertNull($chat->users()->find($peer->id)->pivot->last_read_at);
        [$other] = $this->chat();
        $foreign = Message::create(['conversation_id' => $other->id, 'sender_id' => $author->id, 'body' => 'Private']);
        $this->getJson($url.'?around_id='.$foreign->id)->assertNotFound();
        $this->actingAs(User::factory()->create())->getJson($url.'?around_id='.$ids[40])->assertForbidden();
    }

    public function test_direct_chat_timed_mute_expires_without_affecting_other_members(): void
    {
        [$chat, $author, $peer] = $this->chat();
        $chat->update(['is_group' => false]);
        Message::create(['conversation_id' => $chat->id, 'sender_id' => $author->id, 'body' => 'Hello']);
        $url = '/messages/conversations/'.$chat->id;
        $this->actingAs($peer)->putJson($url.'/mute', ['is_muted' => true, 'duration' => '15'])->assertOk();
        $this->getJson('/messages/unread-count')->assertJsonPath('count', 0)->assertJsonCount(0, 'notifications');
        $this->getJson($url.'/info')->assertOk()->assertJsonPath('is_muted', true);
        $this->assertFalse($chat->fresh()->isMutedFor($author->id));
        $this->travel(16)->minutes();
        $this->getJson('/messages/unread-count')->assertJsonPath('count', 1)->assertJsonCount(1, 'notifications');
        $this->getJson('/messages/conversations')->assertJsonPath('conversations.0.is_muted', false);
        $this->getJson($url.'/info')->assertJsonPath('is_muted', false);
        $this->putJson($url.'/mute', ['is_muted' => true, 'duration' => 'forever'])->assertOk();
        $this->travel(2)->days();
        $this->getJson('/messages/unread-count')->assertJsonPath('count', 0);
        $this->putJson($url.'/mute', ['is_muted' => false])->assertOk()->assertJsonPath('muted_until', null);
        $this->getJson('/messages/unread-count')->assertJsonPath('count', 1);
        $this->putJson($url.'/mute', ['is_muted' => true, 'duration' => '-5'])->assertUnprocessable();
    }

    public function test_info_does_not_mark_messages_read_and_requires_membership(): void
    {
        [$chat, $author, $peer] = $this->chat();
        $url = '/messages/conversations/'.$chat->id;
        $this->actingAs($peer)->getJson($url.'/info')->assertOk()->assertJsonCount(3, 'members');
        $this->assertNull($chat->users()->find($peer->id)->pivot->last_read_at);
        $this->actingAs(User::factory()->create())->getJson($url.'/info')->assertForbidden();
        $this->putJson($url.'/mute', ['is_muted' => true, 'duration' => '60'])->assertForbidden();
    }

    public function test_mute_suppresses_alerts_and_leave_preserves_other_members_and_messages(): void
    {
        [$chat, $author, $peer] = $this->chat();
        Message::create(['conversation_id' => $chat->id, 'sender_id' => $author->id, 'body' => 'Hello']);
        $url = '/messages/conversations/'.$chat->id;
        $this->actingAs($peer)->getJson('/messages/unread-count')->assertJsonPath('count', 1);
        $this->putJson($url.'/mute', ['is_muted' => true])->assertOk();
        $this->getJson('/messages/unread-count')->assertJsonPath('count', 0);
        $this->getJson('/messages/conversations')->assertJsonPath('conversations.0.is_group', true)->assertJsonPath('conversations.0.unread_count', 1)->assertJsonPath('conversations.0.is_muted', true);
        $this->putJson($url.'/mute', ['is_muted' => false])->assertOk();
        $this->getJson('/messages/unread-count')->assertJsonPath('count', 1);
        $this->deleteJson($url.'/leave')->assertOk();
        $this->assertSame(2, $chat->users()->count());
        $this->assertSame(1, $chat->messages()->count());
        $this->getJson($url)->assertForbidden();
        $this->postJson($url, ['body' => 'No longer a member'])->assertForbidden();
        $this->putJson($url.'/mute', ['is_muted' => true])->assertForbidden();
    }
}
