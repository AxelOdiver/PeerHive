<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('users', fn (Blueprint $t) => $t->json('learning_interests')->nullable());
        Schema::create('session_ratings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('study_session_id')->constrained()->cascadeOnDelete();
            $t->foreignId('learner_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedTinyInteger('stars');
            $t->timestamps();
            $t->unique(['study_session_id', 'learner_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('session_ratings'); Schema::table('users', fn (Blueprint $t) => $t->dropColumn('learning_interests')); }
};
