<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('community_banners', function (Blueprint $table) {
            $table->decimal('position_x', 5, 2)->default(50);
            $table->decimal('position_y', 5, 2)->default(50);
        });
    }

    public function down(): void
    {
        Schema::table('community_banners', function (Blueprint $table) {
            $table->dropColumn(['position_x', 'position_y']);
        });
    }
};
