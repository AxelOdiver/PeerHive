<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('swaps', function (Blueprint $table) {
            // Existing requests retain their original meaning; they are not silently reclassified.
            $table->string('request_type', 20)->nullable();
            $table->string('requested_subject')->nullable();
            $table->string('offered_subject')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('swaps', fn (Blueprint $table) => $table->dropColumn(['request_type', 'requested_subject', 'offered_subject']));
    }
};
