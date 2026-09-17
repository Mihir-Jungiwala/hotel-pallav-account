<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_misc_expenses', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->time('time');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('full_name', 20)->nullable();
            $table->string('expense_name', 100);
            $table->decimal('amount', 12, 2);
            $table->string('instruction', 500)->nullable();
            $table->string('amount_in_words', 250)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_misc_expenses');
    }
};
