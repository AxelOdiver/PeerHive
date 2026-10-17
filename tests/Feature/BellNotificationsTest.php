<?php
namespace Tests\Feature;

use App\Models\User;
use App\Support\BellNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BellNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_survives_reading_and_clearing_does_not_recreate_notifications(): void
    {
        $user = User::factory()->create(); $other = User::factory()->create();
        BellNotifications::record($user->id, 'test:1', 'swap', 'Accepted', 'Your request was accepted.', '/swap');
        $id = BellNotifications::query($user->id)->value('id');
        $this->actingAs($other)->postJson('/notifications/'.$id.'/read')->assertNotFound();
        $this->getJson('/messages/unread-count')->assertJsonCount(0, 'history');
        $this->actingAs($user)->getJson('/messages/unread-count')->assertJsonCount(1, 'history')->assertJsonPath('history_unread_count', 1);
        $this->postJson('/notifications/'.$id.'/read')->assertNoContent();
        $this->getJson('/messages/unread-count')->assertJsonCount(1, 'history')->assertJsonPath('history.0.read', true)->assertJsonPath('history_unread_count', 0);
        BellNotifications::record($user->id, 'test:2', 'session', 'Session', 'Started.', '/study-sessions');
        $this->postJson('/notifications/clear-read')->assertNoContent();
        BellNotifications::record($user->id, 'test:1', 'swap', 'Accepted', 'Your request was accepted.', '/swap');
        $this->getJson('/messages/unread-count')->assertJsonCount(1, 'history')->assertJsonPath('history.0.read', false);
        $this->postJson('/notifications/read-all')->assertNoContent();
        $this->getJson('/messages/unread-count')->assertJsonPath('history_unread_count', 0)->assertJsonCount(1, 'history');
        $this->postJson('/notifications/clear-read')->assertNoContent();
        $this->getJson('/messages/unread-count')->assertJsonCount(0, 'history');
    }
}
