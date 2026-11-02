<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('communities')) {
            return;
        }

        if (! Schema::hasColumn('communities', 'subject')) {
            Schema::table('communities', function (Blueprint $table) {
                // Existing rows need a valid value when the column is introduced.
                $table->string('subject')->default('Coding')->after('name');
            });
        }

        if (! Schema::hasColumn('communities', 'tags')) {
            Schema::table('communities', function (Blueprint $table) {
                $table->json('tags')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        // Keep these repaired columns on rollback so existing community data remains usable.
    }
};
