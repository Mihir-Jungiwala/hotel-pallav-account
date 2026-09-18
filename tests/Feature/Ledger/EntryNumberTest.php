<?php

namespace Tests\Feature\Ledger;

use App\Models\HotelCashDeposit;
use App\Models\ShiftHandover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntryNumberTest extends TestCase
{
    use RefreshDatabase;

    private function deposit(string $depositor): HotelCashDeposit
    {
        return HotelCashDeposit::create([
            'date' => now()->toDateString(), 'time' => '10:00', 'user_id' => auth()->id(),
            'depositor' => $depositor, 'amount' => 100,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_entries_are_numbered_in_order(): void
    {
        $first = $this->deposit('One');
        $second = $this->deposit('Two');

        $this->assertSame(1, $first->entry_no);
        $this->assertSame(2, $second->entry_no);
        $this->assertSame('0002', $second->entryNumber());
    }

    public function test_deleting_an_entry_in_the_middle_never_renumbers_the_rest(): void
    {
        [$one, $two, $three] = [$this->deposit('One'), $this->deposit('Two'), $this->deposit('Three')];

        $two->delete();

        $this->assertSame(1, $one->fresh()->entry_no);
        $this->assertSame(3, $three->fresh()->entry_no);

        // The next entry carries on from the highest number ever used
        $this->assertSame(4, $this->deposit('Four')->entry_no);
    }

    public function test_each_book_keeps_its_own_run_of_numbers(): void
    {
        $this->deposit('One');
        $this->deposit('Two');

        $handover = ShiftHandover::create([
            'date' => now()->toDateString(), 'time' => '09:00', 'shift' => 'Morning',
            'user_id' => auth()->id(), 'full_name' => 'Tester',
        ]);

        $this->assertSame(1, $handover->entry_no);
    }

    public function test_the_number_is_shown_on_the_screen_and_on_the_receipt(): void
    {
        $deposit = $this->deposit('Front desk');

        $this->get(route('revenue.index'))->assertSee('#'.$deposit->entryNumber());
        $this->get(route('revenue.hotel.view', $deposit))->assertOk();
    }
}
