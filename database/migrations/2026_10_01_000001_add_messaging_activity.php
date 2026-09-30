<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->timestamp('last_seen_at')->nullable());
        Schema::table('conversation_user', fn (Blueprint $table) => $table->unsignedBigInteger('last_read_message_id')->default(0));
        // Preserve existing unread state when moving from timestamps to message IDs.
        DB::table('conversation_user')->whereNotNull('last_read_at')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $id = DB::table('messages')->where('conversation_id', $row->conversation_id)->where('created_at', '<=', $row->last_read_at)->max('id') ?? 0;
                DB::table('conversation_user')->where('id', $row->id)->update(['last_read_message_id' => $id]);
            }
        });
    }
    public function down(): void
    {
        Schema::table('conversation_user', fn (Blueprint $table) => $table->dropColumn('last_read_message_id'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('last_seen_at'));
    }
};
