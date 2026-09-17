<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_company_id')->constrained('payroll_companies')->cascadeOnDelete();
            $table->string('name', 100);
            $table->decimal('amount', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['payroll_company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deductions');
    }
};
