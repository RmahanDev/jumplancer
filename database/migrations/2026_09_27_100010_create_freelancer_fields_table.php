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
        Schema::create('freelancer_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('freelancer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained();
            $table->string('claimed_level', 20)->default('beginner');
            $table->boolean('is_primary')->default(false);
            $table->boolean('exam_required')->default(false);
            $table->boolean('exam_fee_required')->default(false);
            $table->string('status', 20)->default('pending_exam');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['freelancer_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('freelancer_fields');
    }
};
