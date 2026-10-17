<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('study_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->string('subject', 255);
            $table->string('format', 10);
            $table->dateTime('starts_at')->index();
            $table->dateTime('ends_at');
            $table->string('timezone', 64);
            $table->unsignedSmallInteger('capacity');
            $table->string('meeting_method', 10);
            $table->text('meeting_details');
            $table->string('status', 12)->default('scheduled');
            $table->timestamps();
        });
        Schema::create('study_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('study_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('attended_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['study_session_id', 'user_id']);
        });
        Schema::create('point_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_key')->unique();
            $table->integer('points');
            $table->string('description');
            $table->timestamps();
        });
        Schema::create('user_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reward_key', 40);
            $table->timestamps();
            $table->unique(['user_id', 'reward_key']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->string('profile_frame', 40)->nullable();
            $table->string('progress_theme', 40)->nullable();
            $table->json('learning_goals')->nullable();
            $table->string('learning_timezone', 64)->default('Asia/Taipei');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_rewards');
        Schema::dropIfExists('point_entries');
        Schema::dropIfExists('study_participants');
        Schema::dropIfExists('study_sessions');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['profile_frame', 'progress_theme', 'learning_goals', 'learning_timezone']));
    }
};
