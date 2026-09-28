<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mentoring is the freelancer's choice: it is requested in the proposal, not by the employer.
     */
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->boolean('mentorship_requested')->default(false)->after('delivery_days');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn('mentorship_requested');
        });
    }
};
