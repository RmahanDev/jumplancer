<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the expert decided about the money held for the contract (App\Enums\DisputeOutcome).
     */
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->string('outcome', 30)->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn('outcome');
        });
    }
};
