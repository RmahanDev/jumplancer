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
        Schema::create('employer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('company_name', 150)->nullable();
            $table->string('company_size', 20)->nullable();
            $table->string('industry', 100)->nullable();
            $table->string('website')->nullable();
            $table->boolean('open_to_beginners')->default(true);
            $table->unsignedTinyInteger('free_projects_used')->default(0);
            $table->timestamp('first_free_project_at')->nullable();
            $table->timestamp('second_free_until')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employer_profiles');
    }
};
