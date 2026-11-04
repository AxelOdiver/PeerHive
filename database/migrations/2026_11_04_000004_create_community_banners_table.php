<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('community_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('path');
            $table->string('mime_type', 50);
            $table->timestamp('removed_at')->nullable();
            $table->timestamps();
            $table->index(['community_id', 'removed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_banners');
    }
};
