<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mentors are no longer attached to projects: a freelancer asks for one in the proposal
     * and the mentor joins the contract through a mentoring ticket.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mentor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('mentor_id')->nullable()->after('category_id')->constrained('users')->nullOnDelete();
        });
    }
};
