<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pallav Food decides the price, so the price belongs to Pallav Food. Staff of
 * both companies can eat there. A status can say food is not counted on that
 * day, and the day's entry keeps a copy of that, so editing the status later
 * never rewrites a bill that was already produced.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_statuses', function (Blueprint $table) {
            $table->boolean('skips_food')->default(false);
        });

        Schema::table('attendance_entries', function (Blueprint $table) {
            $table->boolean('skips_food')->default(false);
        });

        // Any price already set was entered on Hotel Pallav's side; it is Pallav Food's now
        if (DB::table('food_charge_rates')->exists()) {
            $provider = DB::table('payroll_companies')->where('code', 'PF01')->value('id')
                ?? DB::table('payroll_companies')->insertGetId([
                    'name' => 'Pallav Food', 'code' => 'PF01', 'is_active' => true,
                    'created_at' => now(), 'updated_at' => now(),
                ]);

            DB::table('food_charge_rates')->update(['payroll_company_id' => $provider]);
        }
    }

    public function down(): void
    {
        Schema::table('attendance_entries', function (Blueprint $table) {
            $table->dropColumn('skips_food');
        });

        Schema::table('attendance_statuses', function (Blueprint $table) {
            $table->dropColumn('skips_food');
        });
    }
};
