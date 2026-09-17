<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_processings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_company_id')->constrained('payroll_companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');

            // Snapshots taken at processing time so later master edits never
            // change an already-generated slip or report.
            $table->string('company_name', 150);
            $table->string('employee_code', 50);
            $table->string('employee_name', 150);
            $table->string('designation', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('payment_mode', 10)->nullable();
            $table->decimal('monthly_salary', 12, 2)->default(0);
            $table->decimal('daily_working_hours', 5, 2)->default(8);

            // Attendance
            $table->unsignedTinyInteger('total_days_in_month')->default(0);
            $table->decimal('daily_salary', 12, 2)->default(0);
            $table->decimal('days_0', 6, 2)->default(0);
            $table->decimal('days_25', 6, 2)->default(0);
            $table->decimal('days_50', 6, 2)->default(0);
            $table->decimal('days_75', 6, 2)->default(0);
            $table->decimal('days_100', 6, 2)->default(0);
            $table->decimal('total_payable_days', 6, 2)->default(0);
            $table->decimal('attendance_salary', 12, 2)->default(0);

            // Earnings
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->decimal('hourly_rate', 12, 2)->default(0);
            $table->decimal('overtime_amount', 12, 2)->default(0);
            $table->decimal('bonus_amount', 12, 2)->default(0);
            $table->decimal('incentive_amount', 12, 2)->default(0);

            // Deductions
            $table->decimal('deduction_amount', 12, 2)->default(0);
            $table->decimal('advance_deduction', 12, 2)->default(0);
            $table->decimal('pending_advance_amount', 12, 2)->default(0);

            $table->decimal('net_salary', 12, 2)->default(0);
            $table->enum('payment_status', ['Pending', 'Paid'])->default('Pending');

            $table->unsignedInteger('generation_count')->default(1);
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('processed_at');
            $table->timestamps();

            $table->unique(['payroll_company_id', 'employee_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_processings');
    }
};
