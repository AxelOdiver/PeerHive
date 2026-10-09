<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); 
            $table->morphs('reportable'); 
            $table->string('reason'); 
            $table->text('details')->nullable();
            $table->string('status')->default('pending'); 
            $table->timestamps();
            
            // Prevent a user from spam-reporting the exact same item multiple times
            $table->unique(['user_id', 'reportable_id', 'reportable_type'], 'user_report_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
