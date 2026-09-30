<?php
namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MessageDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_individual_delete_for_me_is_private_and_excluded_from_search_previews_and_unread(): void
    {
        [$chat, $me, $peer] = $this->chat();
        $message = Message::create(['conversation_id' => $chat->id, 'sender_id' => $peer->id, 'body' => 'Hidden text']);
        $url = '/messages/conversations/'.$chat->id;
        $delete = '/messages/'.$message->id.'/for-me';
        $this->actingAs(User::factory()->create())->deleteJson($delete)->assertForbidden();
        $this->actingAs($me)->deleteJson($delete)->assertOk();
        $this->deleteJson($delete)->assertOk();
        $this->getJson($url)->assertJsonCount(0, 'messages');
        $this->getJson($url.'?q=Hidden')->assertJsonPath('total_results', 0);
        $this->getJson($url.'?around_id='.$message->id)->assertNotFound();
        $this->getJson('/messages/conversations')->assertJsonPath('conversations.0.last_message', null)->assertJsonPath('conversations.0.unread_count', 0);
        $this->getJson('/messages/unread-count')->assertJsonPath('count', 0);
        $this->assertDatabaseHas('messages', ['id' => $message->id, 'body' => 'Hidden text', 'is_unsent' => false]);
        $this->actingAs($peer)->getJson($url)->assertJsonCount(1, 'messages');
        $this->postJson($url, ['body' => 'Reply', 'reply_to_id' => $message->id])->assertOk();
        $this->actingAs($me)->getJson($url)->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.reply_to', null);
        $this->actingAs($peer)->deleteJson($delete)->assertOk();
        $this->getJson($url)->assertJsonCount(1, 'messages');
    }

    private function chat(): array
    {
        $users = User::factory()->count(2)->create();
        $chat = Conversation::create(['is_group' => false, 'created_by' => $users[0]->id]);
        $chat->users()->attach($users->modelKeys());
        return [$chat, $users[0], $users[1]];
    }

    public function test_delete_for_me_preserves_the_other_persons_history_and_new_messages_restore_chat(): void
    {
        [$chat, $me, $peer] = $this->chat();
        $old = Message::create(['conversation_id' => $chat->id, 'sender_id' => $peer->id, 'body' => 'Old private view']);
        $url = '/messages/conversations/'.$chat->id;
        $this->actingAs($me)->deleteJson($url)->assertOk();
        $this->assertDatabaseHas('messages', ['id' => $old->id]);
        $this->assertSame(2, $chat->users()->count());
        $this->getJson('/messages/conversations')->assertJsonCount(0, 'conversations');
        $this->getJson('/messages/unread-count')->assertJsonPath('count', 0);
        $this->getJson($url)->assertJsonCount(0, 'messages');
        $this->getJson($url.'?q=Old')->assertJsonPath('total_results', 0);
        $this->getJson($url.'?around_id='.$old->id)->assertNotFound();
        $this->actingAs($peer)->getJson($url)->assertJsonCount(1, 'messages');
        $this->postJson($url, ['body' => 'New message', 'reply_to_id' => $old->id])->assertOk();
        $this->actingAs($me)->getJson('/messages/conversations')->assertJsonCount(1, 'conversations')->assertJsonPath('conversations.0.unread_count', 1);
        $this->getJson($url)->assertJsonCount(1, 'messages')->assertJsonPath('messages.0.body', 'New message')->assertJsonPath('messages.0.reply_to', null);
    }

    public function test_reopening_keeps_cleared_history_hidden_and_outsiders_cannot_delete(): void
    {
        [$chat, $me, $peer] = $this->chat();
        $url = '/messages/conversations/'.$chat->id;
        $this->actingAs(User::factory()->create())->deleteJson($url)->assertForbidden();
        $this->actingAs($me)->deleteJson($url)->assertOk();
        $this->getJson('/messages/conversations')->assertJsonCount(0, 'conversations');
        $this->postJson('/messages/conversations', ['user_id' => $peer->id])->assertJsonPath('conversation_id', $chat->id);
        $this->getJson('/messages/conversations')->assertJsonCount(1, 'conversations')->assertJsonPath('conversations.0.last_message', null);
    }

    public function test_retrying_the_same_send_creates_only_one_message_but_new_sends_are_allowed(): void
    {
        [$chat, $me] = $this->chat();
        $url = '/messages/conversations/'.$chat->id;
        $payload = ['body' => 'Hello', 'client_message_id' => (string) Str::uuid()];
        $first = $this->actingAs($me)->postJson($url, $payload)->assertOk()->json('message.id');
        $this->postJson($url, $payload)->assertOk()->assertJsonPath('message.id', $first);
        $this->assertSame(1, $chat->messages()->count());
        $this->postJson($url, array_merge($payload, ['body' => 'Different']))->assertConflict();
        $this->postJson($url, ['body' => 'Hello', 'client_message_id' => (string) Str::uuid()])->assertOk();
        $this->assertSame(2, $chat->messages()->count());
    }

    public function test_attachment_retries_store_one_file_and_delete_for_me_keeps_it_for_peer(): void
    {
        Storage::fake('public');
        [$chat, $me, $peer] = $this->chat();
        $url = '/messages/conversations/'.$chat->id;
        $key = (string) Str::uuid();
        $send = fn () => ['client_message_id' => $key, 'attachment' => UploadedFile::fake()->createWithContent('notes.txt', 'Study notes')];
        $this->actingAs($me)->postJson($url, $send())->assertOk();
        $this->postJson($url, $send())->assertOk();
        $this->assertCount(1, Storage::disk('public')->allFiles('message_attachments'));
        $path = $chat->messages()->first()->attachment_path;
        $this->deleteJson($url)->assertOk();
        Storage::disk('public')->assertExists($path);
        $this->actingAs($peer)->getJson($url)->assertJsonCount(1, 'messages');
    }
}
