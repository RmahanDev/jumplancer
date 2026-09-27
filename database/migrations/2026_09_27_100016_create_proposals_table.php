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
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('freelancer_id')->constrained('users');
            $table->text('cover_letter');
            $table->unsignedBigInteger('proposed_price')->comment('Toman');
            $table->unsignedSmallInteger('delivery_days');
            $table->string('status', 20)->default('pending');
            $table->foreignId('mentor_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('mentor_feedback')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'freelancer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
