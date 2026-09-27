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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained();
            $table->foreignId('contract_id')->nullable()->constrained();
            $table->foreignId('milestone_id')->nullable()->constrained();
            $table->foreignId('subscription_id')->nullable()->constrained('employer_subscriptions');
            $table->foreignId('assessment_attempt_id')->nullable()->constrained();
            $table->foreignId('mentorship_program_id')->nullable()->constrained();
            $table->string('type', 20);
            $table->bigInteger('amount')->comment('Toman');
            $table->string('gateway', 30)->nullable();
            $table->string('gateway_ref', 100)->nullable()->index();
            $table->string('status', 20)->default('pending');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
