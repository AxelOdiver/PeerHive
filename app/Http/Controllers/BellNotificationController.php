<?php
namespace App\Http\Controllers;

use App\Support\BellNotifications;
use Illuminate\Http\Request;

class BellNotificationController extends Controller
{
    public function read(Request $request, int $notification)
    {
        $query = BellNotifications::query($request->user()->id)->where('id', $notification);
        abort_unless((clone $query)->exists(), 404);
        $query->whereNull('read_at')->update(['read_at' => now()]);
        return response()->noContent();
    }
    public function readAll(Request $request)
    {
        BellNotifications::query($request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);
        return response()->noContent();
    }
    public function clearRead(Request $request)
    {
        BellNotifications::query($request->user()->id)->whereNotNull('read_at')->update(['cleared_at' => now()]);
        return response()->noContent();
    }
}
