<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Who takes meals at Pallav Food stops being one on/off switch on the staff
 * record and becomes dated records: this person, from this day, to that day.
 * Starting or stopping someone then adds or closes a record and leaves every
 * other month exactly as it was, instead of rewriting them all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_meal_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_company_id')->constrained('payroll_companies')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('starts_on');
            // Null while the meals carry on
            $table->date('ends_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'starts_on']);
            $table->index(['payroll_company_id', 'starts_on']);
        });

        // Whoever was switched on has been charged from their joining date, so that
        // is where their record begins: every bill already produced stays the same
        foreach (DB::table('employees')->where('eats_at_pallav_food', true)->get(['id', 'payroll_company_id', 'joining_date', 'created_at']) as $employee) {
            DB::table('employee_meal_periods')->insert([
                'payroll_company_id' => $employee->payroll_company_id,
                'employee_id' => $employee->id,
                'starts_on' => $employee->joining_date ?: substr((string) $employee->created_at, 0, 10),
                'ends_on' => null,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('eats_at_pallav_food');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('eats_at_pallav_food')->default(false);
        });

        // Anyone on meals today is switched back on
        $today = now()->toDateString();
        $ids = DB::table('employee_meal_periods')
            ->where('starts_on', '<=', $today)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $today))
            ->pluck('employee_id');

        DB::table('employees')->whereIn('id', $ids)->update(['eats_at_pallav_food' => true]);

        Schema::dropIfExists('employee_meal_periods');
    }
};
