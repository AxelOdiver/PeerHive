<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('study_participants', fn (Blueprint $table) => $table->timestamp('session_start_read_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('study_participants', fn (Blueprint $table) => $table->dropColumn('session_start_read_at'));
    }
};
