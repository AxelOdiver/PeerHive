<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL's schedules.user_id foreign key needs a supporting index.
        // Add the non-unique replacement FIRST, in its own ALTER TABLE statement.
        // hasIndex guards are intentional: MySQL DDL is not generally transactional.
        if (!Schema::hasIndex('schedules', 'schedules_user_day_start_idx')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->index(['user_id', 'day_index', 'start_time'], 'schedules_user_day_start_idx');
            });
        }

        // This safely permits multiple slots for the same user and weekday.
        // Do not alter the foreign key or remove existing schedule rows.
        if (Schema::hasIndex('schedules', 'user_day_unique')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropUnique('user_day_unique');
            });
        }
    }

    public function down(): void
    {
        // Restoring one slot per day is unsafe once users have multiple slots.
        $multiple = DB::table('schedules')
            ->select('user_id', 'day_index')
            ->groupBy('user_id', 'day_index')
            ->havingRaw('COUNT(*) > 1')
            ->exists();
        if ($multiple) {
            throw new RuntimeException(
                'Cannot revert: users have multiple slots per day. Export or consolidate them first.'
            );
        }

        // Recreate the unique FK-supporting index BEFORE removing its replacement.
        if (!Schema::hasIndex('schedules', 'user_day_unique')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->unique(['user_id', 'day_index'], 'user_day_unique');
            });
        }
        if (Schema::hasIndex('schedules', 'schedules_user_day_start_idx')) {
            Schema::table('schedules', function (Blueprint $table) {
                $table->dropIndex('schedules_user_day_start_idx');
            });
        }
    }
};
