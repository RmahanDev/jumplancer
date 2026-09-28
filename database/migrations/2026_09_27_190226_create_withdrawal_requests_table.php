<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payouts from a wallet to a bank card, paid by staff with a tracking number and a receipt.
     */
    public function up(): void
    {
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('bank_card_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount')->comment('Toman');
            $table->string('card_number', 16)->comment('Snapshot at request time');
            $table->string('holder_name', 100);
            $table->string('bank_name', 50)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('tracking_code', 100)->nullable();
            $table->string('receipt_path')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
    }
};
