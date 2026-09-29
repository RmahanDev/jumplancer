<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exams written by staff in the exam builder: a skill of a category, a time limit, a total score
     * with a pass mark, and any number of multiple-choice questions (2-8 options, one correct).
     * Attempts keep their answers, warnings for leaving the exam page and how they ended.
     */
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->unsignedSmallInteger('pass_score')->default(70)->change();
            $table->unsignedSmallInteger('total_score')->default(100)->after('description');
            $table->foreignId('created_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
        });

        Schema::create('assessment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->text('hint')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('assessment_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('assessment_questions')->cascadeOnDelete();
            $table->string('body', 500);
            $table->boolean('is_correct')->default(false);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->unsignedSmallInteger('score')->nullable()->change();
            $table->string('status', 12)->default('in_progress')->after('user_id');
            $table->unsignedTinyInteger('warnings')->default(0)->after('status');
            $table->unsignedSmallInteger('correct_count')->nullable()->after('score');
            $table->json('answers')->nullable()->after('correct_count');
            $table->string('void_reason', 100)->nullable()->after('answers');
            $table->timestamp('expires_at')->nullable()->after('started_at');
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessment_attempts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
            $table->dropColumn(['status', 'warnings', 'correct_count', 'answers', 'void_reason', 'expires_at']);
        });

        Schema::dropIfExists('assessment_options');
        Schema::dropIfExists('assessment_questions');

        Schema::table('assessments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('total_score');
        });
    }
};
