<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_company_id')->constrained('payroll_companies')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('shortcut_key', 10);
            $table->string('color', 20);
            $table->unsignedTinyInteger('attendance_percentage');
            $table->enum('status_type', ['Paid', 'Unpaid'])->default('Paid');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['payroll_company_id', 'shortcut_key']);
            $table->unique(['payroll_company_id', 'color']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_statuses');
    }
};
