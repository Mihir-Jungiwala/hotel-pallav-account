<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_company_id')->constrained('payroll_companies')->cascadeOnDelete();

            $table->string('employee_code', 50);
            $table->string('name', 150);
            $table->string('photo_path')->nullable();
            $table->string('designation', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->text('responsibilities')->nullable();
            $table->date('joining_date')->nullable();
            $table->decimal('salary', 12, 2)->default(0);
            $table->decimal('daily_working_hours', 5, 2)->default(8);
            $table->string('contact_number', 15)->nullable();
            $table->string('address', 255)->nullable();
            $table->enum('payment_mode', ['Cash', 'Bank'])->default('Cash');

            // Bank details (used when payment_mode = Bank)
            $table->string('bank_name', 150)->nullable();
            $table->string('account_holder_name', 150)->nullable();
            $table->string('account_number', 50)->nullable();
            $table->string('ifsc_code', 20)->nullable();
            $table->string('branch_name', 150)->nullable();

            // ID proof
            $table->foreignId('id_proof_type_id')->nullable()->constrained('id_proof_types')->nullOnDelete();
            $table->string('id_proof_number', 50)->nullable();
            $table->string('id_proof_image_path')->nullable();

            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['payroll_company_id', 'employee_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
