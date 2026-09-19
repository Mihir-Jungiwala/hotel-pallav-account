<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The people who hand cash in or take it out. Revenue and Expense both pick
 * from this list; a name typed on a form that is not here yet is added to it.
 * Names already on past deposits and withdrawals are carried over.
 */
return new class extends Migration
{
    private const KEY = 'cash_person';

    public function up(): void
    {
        $now = now();

        $setId = DB::table('option_sets')->where('key', self::KEY)->value('id') ?? DB::table('option_sets')->insertGetId([
            'key' => self::KEY, 'name' => 'Cash Handlers',
            'description' => 'People who hand cash in (Revenue) or take it out (Expense)',
            'icon' => 'bi-person-vcard', 'input' => 'select', 'is_system' => true,
            'sort_order' => (int) DB::table('option_sets')->max('sort_order') + 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $names = collect([
            ['hotel_cash_deposits', 'depositor'], ['food_cash_deposits', 'depositor'],
            ['hotel_cash_withdrawals', 'withdrawer'], ['food_cash_withdrawals', 'withdrawer'],
        ])->filter(fn ($t) => Schema::hasTable($t[0]))
            ->flatMap(fn ($t) => DB::table($t[0])->whereNotNull($t[1])->pluck($t[1]))
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->unique(fn ($name) => mb_strtolower($name))
            ->sort()
            ->values();

        foreach ($names as $position => $name) {
            DB::table('option_items')->insert([
                'option_set_id' => $setId, 'label' => $name, 'value' => $name,
                'is_active' => true, 'is_default' => false, 'sort_order' => $position + 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('option_sets')->where('key', self::KEY)->delete();
    }
};
