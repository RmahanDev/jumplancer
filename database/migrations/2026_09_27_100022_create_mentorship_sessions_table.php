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
        Schema::create('mentorship_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained('mentorship_programs')->cascadeOnDelete();
            $table->timestamp('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('session_type', 20)->default('technical');
            $table->string('status', 20)->default('scheduled');
            $table->string('meeting_link')->nullable();
            $table->text('mentor_notes')->nullable();
            $table->unsignedTinyInteger('mentee_rating')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mentorship_sessions');
    }
};
