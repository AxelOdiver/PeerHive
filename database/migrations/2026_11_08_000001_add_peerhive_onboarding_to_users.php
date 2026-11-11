<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'email_verified_at')) $table->timestamp('email_verified_at')->nullable();
            if (!Schema::hasColumn('users', 'onboarding_required')) $table->boolean('onboarding_required')->default(false);
            if (!Schema::hasColumn('users', 'onboarding_completed_at')) $table->timestamp('onboarding_completed_at')->nullable();
        });
    }

    public function down(): void
    {
        // Non-destructive rollback. Onboarding choices and verification records are preserved.
    }
};
