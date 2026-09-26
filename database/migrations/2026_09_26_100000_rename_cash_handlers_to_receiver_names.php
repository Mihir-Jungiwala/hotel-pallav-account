<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Cash Handlers" is what the list was called when Revenue used it too. It is
 * now only the people cash is handed to on the Expense form, so it is named
 * for what it holds: receiver names, and nothing but names.
 *
 * The names on the list, and every entry that used them, are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('option_sets')->where('key', 'cash_person')->update([
            'name' => 'Receiver Names',
            'description' => 'Who cash is handed to when it leaves the till. Names only.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('option_sets')->where('key', 'cash_person')->update([
            'name' => 'Cash Handlers',
            'description' => 'People who hand cash in (Revenue) or take it out (Expense)',
            'updated_at' => now(),
        ]);
    }
};
