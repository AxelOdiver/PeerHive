<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Separate guarded schema steps allow a safe retry after an interrupted
        // MySQL migration (where DDL may commit even if a later step fails).
        if (!Schema::hasColumn('catalog_entries', 'merged_into_id')) {
            Schema::table('catalog_entries', function (Blueprint $table) {
                $table->foreignId('merged_into_id')->nullable()->constrained('catalog_entries')->nullOnDelete();
            });
        }
        if (!Schema::hasColumn('catalog_entries', 'reviewed_at')) {
            Schema::table('catalog_entries', function (Blueprint $table) {
                $table->timestamp('reviewed_at')->nullable();
            });
        }
        if (!Schema::hasTable('catalog_entry_requesters')) Schema::create('catalog_entry_requesters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalog_entry_id')->constrained('catalog_entries')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('purpose', 16)->nullable();
            $table->string('source', 32)->default('legacy');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique(['catalog_entry_id', 'user_id']);
            $table->index(['user_id', 'created_at']);
        });
        // Keep the original submitter represented once when upgrading an existing database.
        foreach (DB::table('catalog_entries')->whereNotNull('submitted_by')->select(['id', 'submitted_by'])->cursor() as $entry) {
            DB::table('catalog_entry_requesters')->insertOrIgnore([
                'catalog_entry_id' => $entry->id, 'user_id' => $entry->submitted_by,
                'source' => 'legacy', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_entry_requesters');
        Schema::table('catalog_entries', function (Blueprint $table) {
            $table->dropForeign(['merged_into_id']);
            $table->dropColumn(['merged_into_id', 'reviewed_at']);
        });
    }
};
