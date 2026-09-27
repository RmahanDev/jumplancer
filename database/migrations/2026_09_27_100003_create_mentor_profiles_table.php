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
        Schema::create('mentor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('expertise_summary')->nullable();
            $table->unsignedTinyInteger('years_experience')->default(0);
            $table->string('mentoring_style', 20)->default('both');
            $table->unsignedSmallInteger('max_mentees')->default(5);
            $table->boolean('is_verified')->default(false);
            $table->boolean('is_volunteer')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mentor_profiles');
    }
};
