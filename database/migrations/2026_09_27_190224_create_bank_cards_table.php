<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bank cards members get paid to. A card must be in the member's name and tied to their mobile.
     */
    public function up(): void
    {
        Schema::create('bank_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('card_number', 16);
            $table->string('holder_name', 100);
            $table->string('phone', 20)->comment('Mobile the card is registered to');
            $table->string('bank_name', 50)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'card_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_cards');
    }
};
