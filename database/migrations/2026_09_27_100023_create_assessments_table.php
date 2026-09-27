<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 10)->default('skill');
            $table->foreignId('skill_id')->nullable()->constrained();
            $table->foreignId('category_id')->nullable()->constrained();
            $table->string('target_level', 20)->nullable();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('pass_score')->default(70);
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
