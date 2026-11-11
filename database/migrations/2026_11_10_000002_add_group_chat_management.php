<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('conversation_user', 'group_role')) {
            Schema::table('conversation_user', function (Blueprint $table) {
                $table->string('group_role', 12)->default('member');
            });
        }
        if (!Schema::hasColumn('conversations', 'group_photo_path')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->string('group_photo_path')->nullable();
            });
        }
        if (!Schema::hasTable('group_invitations')) Schema::create('group_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('invited');
            $table->timestamps();
            $table->unique(['conversation_id', 'target_user_id'], 'group_invitation_unique');
            $table->index(['target_user_id', 'status'], 'group_invitation_recipient_status');
        });

        // Preserve all existing groups and members. The creator remains owner when still a member.
        // If they previously left, give ownership to the earliest remaining member.
        DB::table('conversations')->where('is_group', true)->orderBy('id')->chunkById(100, function ($groups) {
            foreach ($groups as $group) {
                $members = DB::table('conversation_user')->where('conversation_id', $group->id)->orderBy('id')->get(['user_id']);
                if ($members->isEmpty()) continue;
                $owner = $members->contains('user_id', $group->created_by) ? $group->created_by : $members->first()->user_id;
                DB::table('conversation_user')->where('conversation_id', $group->id)->where('user_id', $owner)->update(['group_role' => 'owner']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_invitations');
        Schema::table('conversations', fn (Blueprint $table) => $table->dropColumn('group_photo_path'));
        Schema::table('conversation_user', fn (Blueprint $table) => $table->dropColumn('group_role'));
    }
};
