<?php

namespace Tests;

use App\Models\OptionItem;
use App\Models\OptionSet;
use App\Support\Access;
use App\Support\CashLedger;
use App\Support\Masters;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The role ladder is read once and kept for the request. A test rolls
        // its database back but not that, so each test starts with it empty.
        Access::flush();
    }

    /** Puts a name on a cash book's depositor list in Master Data, as the SuperAdmin would. */
    protected function offerDepositor(string $book, string $name): void
    {
        $set = OptionSet::where('key', CashLedger::DEPOSITOR_SETS[$book])->firstOrFail();

        OptionItem::firstOrCreate(['option_set_id' => $set->id, 'value' => $name], [
            'label' => $name, 'is_active' => true, 'sort_order' => (int) $set->items()->max('sort_order') + 1,
        ]);
        Masters::flush();
    }
}
