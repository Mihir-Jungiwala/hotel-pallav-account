<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll's own lists leave the general Master Data and live in Payroll
 * Master. What was already configured for the free ones (gender, exit types)
 * comes across as entries for every company, ID proof types become plain
 * entries too, and an exit type can now be any text rather than one of four.
 */
return new class extends Migration
{
    private const MOVED = ['gender', 'separation_type', 'salary_payment_mode', 'advance_deduction_type', 'bonus_type', 'attendance_status_type'];

    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('id_proof_type', 100)->nullable()->after('id_proof_type_id');
        });

        Schema::table('employee_separations', function (Blueprint $table) {
            $table->string('separation_type', 60)->default('Resignation')->change();
        });

        $now = now();
        $copy = function (string $list, $labels) use ($now) {
            foreach ($labels->values() as $i => $label) {
                DB::table('payroll_master_items')->insert([
                    'payroll_company_id' => null, 'list' => $list, 'label' => $label, 'value' => null,
                    'is_active' => true, 'sort_order' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        };

        foreach (['gender', 'separation_type'] as $key) {
            $setId = DB::table('option_sets')->where('key', $key)->value('id');

            if ($setId) {
                $copy($key, DB::table('option_items')->where('option_set_id', $setId)->where('is_active', true)
                    ->orderBy('sort_order')->pluck('label'));
            }
        }

        $copy('id_proofs', DB::table('id_proof_types')->where('is_active', true)->orderBy('name')->pluck('name'));

        // Keep each person's existing ID proof type
        foreach (DB::table('id_proof_types')->get() as $type) {
            DB::table('employees')->where('id_proof_type_id', $type->id)->update(['id_proof_type' => $type->name]);
        }

        $ids = DB::table('option_sets')->whereIn('key', self::MOVED)->pluck('id');
        DB::table('option_items')->whereIn('option_set_id', $ids)->delete();
        DB::table('option_sets')->whereIn('id', $ids)->delete();
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('id_proof_type');
        });
    }
};
