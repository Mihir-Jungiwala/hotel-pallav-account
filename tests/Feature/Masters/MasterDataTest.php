<?php

namespace Tests\Feature\Masters;

use App\Models\HotelCashDeposit;
use App\Models\OptionItem;
use App\Models\OptionSet;
use App\Models\User;
use App\Support\Masters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Masters::flush();
    }

    private function paymentModes(): OptionSet
    {
        return OptionSet::where('key', 'payment_mode')->firstOrFail();
    }

    public function test_only_the_superadmin_can_open_master_data(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('masters.index'))->assertForbidden();
        $this->actingAs(User::factory()->editor()->create())->get(route('masters.index'))->assertForbidden();
        $this->actingAs(User::factory()->superAdmin()->create())->get(route('masters.index'))->assertOk();
    }

    public function test_the_shipped_lists_are_there(): void
    {
        $this->assertContains('Cash', Masters::values('payment_mode'));
        $this->assertContains('MAP', Masters::values('hotel_plan'));
        $this->assertContains('Night', Masters::values('shift'));
    }

    public function test_an_option_can_be_added_and_reaches_the_forms(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->post(route('masters.items.store', $this->paymentModes()), ['label' => 'Wallet'])
            ->assertSessionHasNoErrors();

        Masters::flush();
        $this->assertContains('Wallet', Masters::values('payment_mode'));

        $this->get(route('bill-master.bills'))->assertSee('Wallet');
    }

    public function test_hiding_an_option_takes_it_off_forms_but_keeps_old_records(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $item = $this->paymentModes()->items()->where('value', 'Cheque')->firstOrFail();

        HotelCashDeposit::create([
            'date' => now()->toDateString(), 'time' => '10:00', 'user_id' => auth()->id(),
            'depositor' => 'Paid by Cheque', 'amount' => 100,
        ]);

        $this->post(route('masters.items.toggle', $item))->assertSessionHasNoErrors();

        Masters::flush();
        $this->assertNotContains('Cheque', Masters::values('payment_mode'));
        $this->get(route('revenue.index'))->assertSee('Paid by Cheque');
    }

    public function test_a_shipped_list_cannot_be_deleted_but_its_options_can(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->delete(route('masters.sets.destroy', $this->paymentModes()))->assertSessionHas('error');
        $this->assertModelExists($this->paymentModes());

        $item = $this->paymentModes()->items()->where('value', 'UPI')->firstOrFail();
        $this->delete(route('masters.items.destroy', $item))->assertSessionHasNoErrors();
        $this->assertModelMissing($item);
    }

    public function test_a_new_list_can_be_created_and_removed(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->post(route('masters.sets.store'), ['name' => 'Room Types', 'input' => 'select'])
            ->assertSessionHasNoErrors();

        $set = OptionSet::where('key', 'room_types')->firstOrFail();
        $this->assertFalse($set->is_system);

        $this->delete(route('masters.sets.destroy', $set))->assertSessionHasNoErrors();
        $this->assertModelMissing($set);
    }

    public function test_two_options_in_one_list_cannot_share_a_value(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->post(route('masters.items.store', $this->paymentModes()), ['label' => 'Cash'])
            ->assertSessionHasErrors('value');
    }

    public function test_only_one_option_stays_the_default(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $set = $this->paymentModes();

        $this->post(route('masters.items.store', $set), ['label' => 'Voucher', 'is_default' => '1']);

        $defaults = OptionItem::where('option_set_id', $set->id)->where('is_default', true)->get();
        $this->assertCount(1, $defaults);
        $this->assertSame('Voucher', $defaults->first()->label);
    }

    public function test_options_can_be_reordered(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $set = $this->paymentModes();
        $second = $set->items()->get()[1];

        $this->post(route('masters.items.move', $second), ['direction' => 'up']);

        Masters::flush();
        $this->assertSame($second->value, Masters::items('payment_mode')->first()->value);
    }
}
