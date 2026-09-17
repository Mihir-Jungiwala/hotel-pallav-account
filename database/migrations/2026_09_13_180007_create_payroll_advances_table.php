<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_company_id')->constrained('payroll_companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            $table->dateTime('advance_date');
            $table->decimal('amount', 12, 2);
            $table->enum('deduction_type', ['One Time', 'Monthly'])->default('One Time');
            $table->decimal('deduction_amount', 12, 2)->default(0);
            $table->text('remarks')->nullable();

            $table->decimal('recovered_amount', 12, 2)->default(0);
            $table->boolean('is_settled')->default(false);

            // System-created carry-forward rows are read-only in the UI
            $table->boolean('is_carry_forward')->default(false);
            $table->foreignId('parent_advance_id')->nullable()->constrained('payroll_advances')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_advances');
    }
};
