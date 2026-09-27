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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employer_id')->constrained('users');
            $table->foreignId('category_id')->constrained();
            $table->foreignId('mentor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description');
            $table->string('budget_type', 10)->default('fixed');
            $table->unsignedBigInteger('budget_min')->nullable()->comment('Toman');
            $table->unsignedBigInteger('budget_max')->nullable()->comment('Toman');
            $table->string('status', 20)->default('draft');
            $table->boolean('is_beginner_friendly')->default(true);
            $table->date('deadline')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('posting_type', 20)->default('free_first');
            $table->foreignId('subscription_id')->nullable()->constrained('employer_subscriptions');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'published_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
