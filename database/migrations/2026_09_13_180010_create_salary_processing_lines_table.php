<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_processing_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_processing_id')->constrained('salary_processings')->cascadeOnDelete();

            $table->enum('category', ['Deduction', 'Advance', 'Bonus', 'Incentive']);
            $table->string('label', 150);
            $table->string('deduction_type', 20)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('pending_amount', 12, 2)->default(0);

            // Lets Re-Generate Salary reverse exactly what the last run applied
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_processing_lines');
    }
};
