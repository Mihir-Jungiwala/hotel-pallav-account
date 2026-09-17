<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_separations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_company_id')->constrained('payroll_companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            $table->enum('separation_type', ['Resignation', 'Termination', 'Retirement', 'End of Contract'])
                ->default('Resignation');
            $table->date('resignation_date');
            $table->date('last_working_date');

            $table->text('reason');
            $table->text('remarks')->nullable();

            // Scanned acceptance / relieving proof (image or PDF)
            $table->string('document_path')->nullable();

            $table->enum('status', ['Pending', 'Accepted', 'Relieved'])->default('Pending');

            // Set when the same person is taken back on
            $table->date('rejoined_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_separations');
    }
};
