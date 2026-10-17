<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('study_sessions', fn (Blueprint $t) => $t->unsignedBigInteger('community_id')->nullable()->change());
        Schema::table('conversations', function (Blueprint $t) {
            $t->foreignId('swap_id')->nullable()->unique()->constrained()->nullOnDelete();
            $t->json('session_context')->nullable();
            $t->foreignId('study_session_id')->nullable()->unique()->constrained()->nullOnDelete();
        });
        Schema::table('study_participants', function (Blueprint $t) {
            $t->unsignedSmallInteger('learning_minutes')->nullable();
            $t->unsignedSmallInteger('teaching_minutes')->nullable();
        });
    }
    public function down(): void {
        Schema::table('conversations', function (Blueprint $t) { $t->dropConstrainedForeignId('swap_id'); $t->dropConstrainedForeignId('study_session_id'); $t->dropColumn('session_context'); });
        Schema::table('study_participants', fn (Blueprint $t) => $t->dropColumn(['learning_minutes', 'teaching_minutes']));
    }
};
