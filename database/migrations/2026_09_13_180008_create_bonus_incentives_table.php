<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bonus_incentives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_company_id')->constrained('payroll_companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            $table->dateTime('entry_date');
            $table->enum('type', ['Bonus', 'Incentive'])->default('Bonus');
            $table->decimal('amount', 12, 2);
            $table->text('remarks')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_incentives');
    }
};
