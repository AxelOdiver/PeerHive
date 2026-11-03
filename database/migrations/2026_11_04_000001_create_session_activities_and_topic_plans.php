<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('topic_learning_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->unique()->constrained('catalog_entries')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('prerequisite_id')->nullable()->constrained('catalog_entries')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('outcomes')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
        });
        Schema::create('session_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('learner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained('catalog_entries')->nullOnDelete();
            $table->string('topic_name');
            $table->string('title', 150);
            $table->text('instructions');
            $table->text('passing_criteria');
            $table->string('reference_url', 1000)->nullable();
            $table->json('covered_outcomes')->nullable();
            $table->string('status', 20)->default('assigned');
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
            $table->unique(['study_session_id', 'teacher_id']);
            $table->index(['learner_id', 'topic_id', 'status']);
        });
        Schema::create('activity_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_activity_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('attempt');
            $table->text('answer');
            $table->string('project_url', 1000)->nullable();
            $table->string('decision', 20)->nullable();
            $table->text('feedback')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->unique(['session_activity_id', 'attempt']);
        });
        // A small suggested path using existing approved topics; no invented completion requirements.
        $basics = DB::table('catalog_entries')->where('kind', 'topic')->where('status', 'approved')->where('name_key', 'python basics')->first();
        $functions = DB::table('catalog_entries')->where('kind', 'topic')->where('status', 'approved')->where('name_key', 'python functions')->first();
        if ($basics) DB::table('topic_learning_plans')->insert(['topic_id' => $basics->id, 'description' => 'Explore Python variables, basic data types, input, and output.', 'sort_order' => 0, 'revision' => 0, 'created_at' => now(), 'updated_at' => now()]);
        if ($functions && $basics && $functions->parent_id === $basics->parent_id) DB::table('topic_learning_plans')->insert(['topic_id' => $functions->id, 'prerequisite_id' => $basics->id, 'description' => 'Organize reusable code with functions, parameters, and return values.', 'sort_order' => 1, 'revision' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_submissions');
        Schema::dropIfExists('session_activities');
        Schema::dropIfExists('topic_learning_plans');
    }
};
