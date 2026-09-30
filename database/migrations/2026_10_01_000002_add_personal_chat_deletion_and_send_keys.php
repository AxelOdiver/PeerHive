<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('conversation_user', function (Blueprint $table) {
            $table->unsignedBigInteger('cleared_through_message_id')->default(0);
            $table->boolean('is_hidden')->default(false);
        });
        Schema::table('messages', function (Blueprint $table) {
            $table->uuid('client_message_id')->nullable();
            $table->string('request_hash', 64)->nullable();
            $table->unique(['sender_id', 'client_message_id']);
        });
    }
    public function down(): void {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropUnique(['sender_id', 'client_message_id']);
            $table->dropColumn(['client_message_id', 'request_hash']);
        });
        Schema::table('conversation_user', fn (Blueprint $table) => $table->dropColumn(['cleared_through_message_id', 'is_hidden']));
    }
};
