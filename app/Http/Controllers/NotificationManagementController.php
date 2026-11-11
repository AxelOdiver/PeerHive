<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Users can manage their own bell alerts, without changing related domain records. */
class NotificationManagementController extends Controller
{
    public function readState(Request $request, int $notification)
    {
        $data = $request->validate(['read' => ['required', 'boolean']]);
        $item = $this->ownedNotification($request, $notification);
        abort_if($this->dismissed($request->user()->id, $item->event_key), 404);
        DB::table('bell_notifications')->where('id', $notification)
            ->where('user_id', $request->user()->id)
            ->update(['read_at' => $data['read'] ? now() : null]);
        return response()->json(['message' => $data['read'] ? 'Marked as read.' : 'Marked as unread.']);
    }

    public function dismiss(Request $request, int $notification)
    {
        $item = $this->ownedNotification($request, $notification);
        $this->dismissEvent($request->user()->id, (string) $item->event_key);
        return response()->json(['message' => 'Notification deleted for you.']);
    }

    public function clearAll(Request $request)
    {
        $userId = (int) $request->user()->id;
        DB::table('bell_notifications')->where('user_id', $userId)
            ->orderBy('id')->chunkById(200, function ($items) use ($userId) {
                $time = now();
                $rows = $items->map(fn ($item) => [
                    'user_id' => $userId,
                    'event_key_hash' => hash('sha256', (string) $item->event_key),
                    'event_key' => (string) $item->event_key,
                    'created_at' => $time,
                    'updated_at' => $time,
                ])->all();
                if ($rows) DB::table('bell_notification_dismissals')->insertOrIgnore($rows);
            }, 'id');
        return response()->json(['message' => 'Notifications cleared for you.']);
    }

    private function ownedNotification(Request $request, int $id): object
    {
        $row = DB::table('bell_notifications')->where('id', $id)
            ->where('user_id', $request->user()->id)->first(['id', 'event_key']);
        abort_unless($row, 404);
        return $row;
    }

    private function dismissed(int $userId, string $eventKey): bool
    {
        return DB::table('bell_notification_dismissals')->where('user_id', $userId)
            ->where('event_key_hash', hash('sha256', $eventKey))->exists();
    }

    private function dismissEvent(int $userId, string $eventKey): void
    {
        DB::table('bell_notification_dismissals')->insertOrIgnore([
            'user_id' => $userId,
            'event_key_hash' => hash('sha256', $eventKey),
            'event_key' => $eventKey,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
