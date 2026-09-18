<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two rules the accounts team relies on:
 *  1. An entry keeps its number for life. Deleting entry 5 leaves a gap,
 *     it never renumbers 6 into 5.
 *  2. Deleting a user never deletes what they recorded. The account is closed
 *     and its entries keep showing the name, marked as deleted.
 */
return new class extends Migration
{
    /** Every book that needs its own running number. */
    public const LEDGERS = [
        'hotel_cash_deposits', 'food_cash_deposits',
        'hotel_cash_withdrawals', 'food_cash_withdrawals',
        'hotel_misc_expenses', 'food_misc_expenses',
        'staff_advances', 'shift_handovers',
        'bill_master_bills', 'bill_master_advances',
        'payroll_advances', 'bonus_incentives', 'employee_separations',
    ];

    public function up(): void
    {
        foreach (self::LEDGERS as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'entry_no')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unsignedInteger('entry_no')->nullable()->after('id')->index();
            });

            // Existing rows keep the order they were created in
            $number = 0;
            foreach (DB::table($table)->orderBy('id')->pluck('id') as $id) {
                DB::table($table)->where('id', $id)->update(['entry_no' => ++$number]);
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        foreach (self::LEDGERS as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'entry_no')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('entry_no'));
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropSoftDeletes();
        });
    }
};
