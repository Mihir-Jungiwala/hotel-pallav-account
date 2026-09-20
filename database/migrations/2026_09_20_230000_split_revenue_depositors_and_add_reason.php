<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revenue: each cash book now has its own list of depositors in Master Data
 * (Hotel Pallav and Pallav Food are picked from separately), and a deposit
 * records why the cash was handed in instead of picking a "source".
 *
 * Names already on past deposits seed each book's list. A past deposit's
 * source is carried into its reason so nothing is lost; the source column
 * stays in the table, unused.
 */
return new class extends Migration
{
    private const BOOKS = [
        'hotel_cash_deposits' => ['revenue_depositor_hotel', 'Hotel Pallav Depositors', 'Who hands cash in to the Hotel Pallav book'],
        'food_cash_deposits' => ['revenue_depositor_food', 'Pallav Food Depositors', 'Who hands cash in to the Pallav Food book'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::BOOKS as $table => [$key, $name, $description]) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('reason', 500)->nullable()->after('depositor');
            });

            DB::table($table)->whereNotNull('revenue_source')->where('revenue_source', '!=', '')->update(['reason' => DB::raw('revenue_source')]);

            $setId = DB::table('option_sets')->where('key', $key)->value('id') ?? DB::table('option_sets')->insertGetId([
                'key' => $key, 'name' => $name, 'description' => $description,
                'icon' => 'bi-person-vcard', 'input' => 'select', 'is_system' => true,
                'sort_order' => (int) DB::table('option_sets')->max('sort_order') + 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            $names = DB::table($table)->whereNotNull('depositor')->pluck('depositor')
                ->map(fn ($n) => trim((string) $n))->filter()
                ->unique(fn ($n) => mb_strtolower($n))->sort()->values();

            foreach ($names as $position => $label) {
                DB::table('option_items')->insertOrIgnore([
                    'option_set_id' => $setId, 'label' => $label, 'value' => $label, 'is_active' => true,
                    'is_default' => false, 'sort_order' => $position + 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::BOOKS as $table => [$key]) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropColumn('reason'));
            DB::table('option_sets')->where('key', $key)->delete();
        }
    }
};
