<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('communities') || ! Schema::hasColumn('communities', 'description')) {
            return;
        }

        $description = collect(Schema::getColumns('communities'))->firstWhere('name', 'description');
        if ($description && ! $description['nullable']) {
            Schema::table('communities', function (Blueprint $table) {
                $table->text('description')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        // Keep descriptions nullable so existing communities remain editable.
    }
};
