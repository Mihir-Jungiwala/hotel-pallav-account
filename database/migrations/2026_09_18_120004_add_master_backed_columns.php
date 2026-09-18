<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Two fields that now come from Master Data: where cash came from, and what an expense was for. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['hotel_cash_deposits', 'food_cash_deposits'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('revenue_source', 60)->nullable()->after('depositor');
            });
        }

        foreach (['hotel_misc_expenses', 'food_misc_expenses'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('expense_head', 60)->nullable()->after('expense_name');
            });
        }
    }

    public function down(): void
    {
        foreach (['hotel_cash_deposits', 'food_cash_deposits'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('revenue_source'));
        }

        foreach (['hotel_misc_expenses', 'food_misc_expenses'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('expense_head'));
        }
    }
};
