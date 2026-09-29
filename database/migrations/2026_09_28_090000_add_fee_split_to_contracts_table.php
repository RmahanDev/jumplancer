<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fees become editable percentages with decimals (e.g. 3.5), frozen on each contract when hiring:
     * fee_percent is what the freelancer pays, mentor_share_percent the part of the amount paid to the mentor.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->decimal('fee_percent', 5, 2)->default(20)->change();
            $table->decimal('mentor_share_percent', 5, 2)->default(0)->after('fee_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('mentor_share_percent');
            $table->unsignedTinyInteger('fee_percent')->default(20)->change();
        });
    }
};
