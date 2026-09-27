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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained();
            $table->foreignId('proposal_id')->unique()->constrained();
            $table->foreignId('employer_id')->constrained('users');
            $table->foreignId('freelancer_id')->constrained('users');
            $table->foreignId('mentor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('amount')->comment('Toman');
            $table->boolean('mentorship_included')->default(false);
            $table->boolean('is_free_mentorship')->default(false);
            $table->unsignedTinyInteger('fee_percent')->default(20);
            $table->string('status', 20)->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
