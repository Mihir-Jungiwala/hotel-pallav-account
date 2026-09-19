<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A salary payment mode is any entry in Payroll Master, not just Cash or Bank. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('payment_mode', 40)->default('Cash')->change();
        });

        Schema::table('salary_processings', function (Blueprint $table) {
            $table->string('payment_mode', 40)->nullable()->change();
        });

        if (! DB::table('payroll_master_items')->where('list', 'salary_payment_mode')->exists()) {
            foreach (['Cash', 'Bank'] as $i => $label) {
                DB::table('payroll_master_items')->insert([
                    'payroll_company_id' => null, 'list' => 'salary_payment_mode', 'label' => $label, 'value' => null,
                    'is_active' => true, 'sort_order' => $i + 1, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void {}
};
