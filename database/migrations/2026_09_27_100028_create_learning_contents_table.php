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
        Schema::create('learning_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('author_id')->constrained('users');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->string('content_type', 20)->default('article');
            $table->string('audience', 20)->default('freelancer');
            $table->string('purpose', 20)->default('technical');
            $table->text('body')->nullable();
            $table->string('media_url')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_contents');
    }
};
