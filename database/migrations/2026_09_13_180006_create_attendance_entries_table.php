<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_month_id')->constrained('attendance_months')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedTinyInteger('day');

            $table->foreignId('attendance_status_id')->nullable()->constrained('attendance_statuses')->nullOnDelete();
            // Snapshot so historical rows survive master edits
            $table->string('shortcut_key', 10)->nullable();
            $table->unsignedTinyInteger('attendance_percentage')->default(0);
            $table->decimal('overtime_hours', 6, 2)->default(0);

            $table->timestamps();

            $table->unique(['attendance_month_id', 'employee_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_entries');
    }
};
