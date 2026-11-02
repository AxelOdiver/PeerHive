<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('catalog_entries', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16);
            $table->string('name', 255);
            $table->string('name_key', 255);
            $table->foreignId('parent_id')->nullable()->constrained('catalog_entries')->nullOnDelete();
            $table->string('status', 16)->default('pending');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('review_note', 1000)->nullable();
            $table->timestamps();
            $table->unique(['kind', 'name_key']);
        });
        $add = function ($kind, $name, $parent = null) {
            $key = mb_strtolower(preg_replace('/\s+/u', ' ', trim($name)));
            DB::table('catalog_entries')->insertOrIgnore(['kind' => $kind, 'name' => $name, 'name_key' => $key, 'parent_id' => $parent, 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
            return DB::table('catalog_entries')->where('kind', $kind)->where('name_key', $key)->value('id');
        };
        foreach (['BSIT', 'BSCS', 'BSBA'] as $program) $add('program', $program);
        foreach (['Programming' => ['Python Basics', 'Python Functions', 'Java Basics', 'JavaScript Basics'], 'Databases' => ['SQL Basics', 'SQL Joins', 'Database Design'], 'Spreadsheets' => ['Excel Basics', 'Excel Formulas'], 'Languages' => ['Japanese Basics', 'English Writing'], 'Design' => ['Graphic Design']] as $subject => $topics) {
            $parent = $add('subject', $subject);
            foreach ($topics as $topic) $add('topic', $topic, $parent);
        }
        // Preserve existing qualification names; broad approvals never imply approval of child topics.
        $legacy = $add('subject', 'Existing teaching subjects');
        foreach (DB::table('user_subject_qualifications')->distinct()->pluck('subject_name') as $name) {
            if (trim($name) !== '') $add('topic', $name, $legacy);
        }
    }
    public function down(): void { Schema::dropIfExists('catalog_entries'); }
};
