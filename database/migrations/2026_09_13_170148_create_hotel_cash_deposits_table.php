<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_cash_deposits', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->time('time');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('full_name', 20)->nullable();
            $table->string('depositor', 100);
            $table->decimal('amount', 12, 2);
            $table->string('amount_in_words', 250)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_cash_deposits');
    }
};
