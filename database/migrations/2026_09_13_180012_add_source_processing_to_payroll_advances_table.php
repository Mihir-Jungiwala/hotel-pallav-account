<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_advances', function (Blueprint $table) {
            // Carry-forward rows are removed again when the month is re-generated
            $table->foreignId('source_salary_processing_id')
                ->nullable()
                ->after('parent_advance_id')
                ->constrained('salary_processings')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_advances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_salary_processing_id');
        });
    }
};
