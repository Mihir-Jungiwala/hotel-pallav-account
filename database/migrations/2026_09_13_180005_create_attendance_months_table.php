<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_company_id')->constrained('payroll_companies')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');

            $table->boolean('is_locked')->default(false);
            $table->dateTime('locked_at')->nullable();
            $table->dateTime('salary_generated_at')->nullable();
            $table->foreignId('salary_generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('generation_count')->default(0);

            // A temporary admin unlock reverts to locked if the session ends
            // before Re-Generate Salary is run.
            $table->foreignId('unlocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('unlocked_at')->nullable();
            $table->string('unlock_session_id')->nullable();

            $table->timestamps();

            $table->unique(['payroll_company_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_months');
    }
};
