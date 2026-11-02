<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('login_otps', function (Blueprint $table) {
            $table->string('code_hash')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
        });
    }
    public function down(): void
    {
        Schema::table('login_otps', fn (Blueprint $table) => $table->dropColumn(['code_hash', 'attempts']));
    }
};
