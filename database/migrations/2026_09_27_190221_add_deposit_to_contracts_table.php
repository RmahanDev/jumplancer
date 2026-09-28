<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Good-faith deposit held from the employer at hiring (a share of the contract amount).
     * deposit_balance is the part not yet used to fund milestones.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->unsignedBigInteger('deposit_amount')->default(0)->after('amount')->comment('Toman');
            $table->unsignedBigInteger('deposit_balance')->default(0)->after('deposit_amount')->comment('Toman, still held');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['deposit_amount', 'deposit_balance']);
        });
    }
};
