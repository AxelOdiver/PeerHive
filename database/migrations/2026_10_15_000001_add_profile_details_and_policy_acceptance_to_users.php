<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('course', 120)->nullable();
            $table->json('expertise')->nullable();
            $table->string('school', 160)->nullable();
            $table->string('school_location', 160)->nullable();
            foreach (['course', 'expertise', 'school', 'school_location'] as $field) {
                $table->boolean('show_'.$field)->default(false);
            }
            $table->timestamp('policies_accepted_at')->nullable();
            $table->string('terms_version', 30)->nullable();
            $table->string('privacy_version', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'course', 'expertise', 'school', 'school_location', 'show_course', 'show_expertise',
            'show_school', 'show_school_location', 'policies_accepted_at', 'terms_version', 'privacy_version',
        ]));
    }
};
