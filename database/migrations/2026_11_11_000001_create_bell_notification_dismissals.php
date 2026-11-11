<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bell_notification_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('event_key_hash', 64);
            $table->text('event_key');
            $table->timestamps();
            $table->unique(['user_id', 'event_key_hash'], 'bell_dismissal_user_event_unique');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('bell_notification_dismissals');
    }
};
