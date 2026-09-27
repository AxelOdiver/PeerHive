<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('conversation_user', function (Blueprint $table) {
            $table->boolean('is_muted')->default(false);
        });
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->unique(['message_id', 'user_id', 'emoji']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('message_reactions');
        Schema::table('conversation_user', fn (Blueprint $table) => $table->dropColumn('is_muted'));
    }
};
