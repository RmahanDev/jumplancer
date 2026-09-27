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
        Schema::create('freelancer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('headline', 150)->nullable();
            $table->string('level', 20)->default('beginner');
            $table->unsignedTinyInteger('readiness_score')->default(0);
            $table->unsignedBigInteger('hourly_rate')->nullable()->comment('Toman');
            $table->string('availability', 20)->default('available');
            $table->boolean('onboarding_completed')->default(false);
            $table->unsignedTinyInteger('free_mentorships_used')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('freelancer_profiles');
    }
};
