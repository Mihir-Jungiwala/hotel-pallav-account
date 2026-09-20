<?php

namespace Tests\Feature\Masters;

use App\Models\OptionSet;
use App\Models\User;
use App\Support\Masters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The lists of people (the depositors of each cash book) keep only a name:
 * nothing else to fill in, no hidden state, and they read alphabetically.
 */
class DepositorListTest extends TestCase
{
    use RefreshDatabase;

    private OptionSet $hotel;

    protected function setUp(): void
    {
        parent::setUp();
        Masters::flush();
        $this->actingAs(User::factory()->superAdmin()->create());
        $this->hotel = OptionSet::where('key', 'revenue_depositor_hotel')->firstOrFail();
    }

    public function test_a_name_is_all_the_form_asks_for(): void
    {
        $html = $this->get(route('masters.index', ['set' => $this->hotel->key]))->assertOk()->getContent();

        $this->assertStringContainsString('Add name', $html);
        $this->assertStringContainsString('name="label"', $html);
        foreach (['name="value"', 'name="is_active"', 'name="is_default"', 'Saved value', 'Pre-select', 'List settings', 'Order'] as $gone) {
            $this->assertStringNotContainsString($gone, $html, "$gone should not be on a list of names");
        }
    }

    public function test_an_ordinary_list_still_shows_everything(): void
    {
        $html = $this->get(route('masters.index', ['set' => 'shift']))->assertOk()->getContent();

        foreach (['name="value"', 'name="is_active"', 'name="is_default"', 'Saved value', 'List settings'] as $kept) {
            $this->assertStringContainsString($kept, $html);
        }
    }

    public function test_saving_a_name_stores_just_the_name_and_puts_it_on_the_form(): void
    {
        $this->post(route('masters.items.store', $this->hotel), ['label' => '  Meera Shah  '])->assertSessionHasNoErrors();

        $item = $this->hotel->items()->sole();
        $this->assertSame('Meera Shah', $item->label);
        $this->assertSame('Meera Shah', $item->value);
        $this->assertTrue($item->is_active);
        $this->assertFalse($item->is_default);

        Masters::flush();
        $this->assertSame(['Meera Shah'], Masters::values('revenue_depositor_hotel'));
    }

    public function test_the_same_name_cannot_be_added_twice_to_one_list_but_can_be_on_the_other(): void
    {
        $this->post(route('masters.items.store', $this->hotel), ['label' => 'Meera Shah'])->assertSessionHasNoErrors();
        $this->post(route('masters.items.store', $this->hotel), ['label' => 'Meera Shah'])->assertSessionHasErrors('value');

        $food = OptionSet::where('key', 'revenue_depositor_food')->firstOrFail();
        $this->post(route('masters.items.store', $food), ['label' => 'Meera Shah'])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->hotel->items()->count());
        $this->assertSame(1, $food->items()->count());
    }

    public function test_renaming_a_name_changes_only_the_name(): void
    {
        $this->post(route('masters.items.store', $this->hotel), ['label' => 'Old Name']);
        $item = $this->hotel->items()->sole();

        $this->put(route('masters.items.update', $item), ['label' => 'New Name'])->assertSessionHasNoErrors();

        $item->refresh();
        $this->assertSame('New Name', $item->label);
        $this->assertSame('New Name', $item->value);
    }

    public function test_a_blank_name_is_refused(): void
    {
        $this->post(route('masters.items.store', $this->hotel), ['label' => '   '])->assertSessionHasErrors('label');
        $this->assertSame(0, $this->hotel->items()->count());
    }

    public function test_the_names_read_alphabetically_on_the_screen_and_in_the_form(): void
    {
        foreach (['Zoya Khan', 'anita desai', 'Meera Shah'] as $name) {
            $this->post(route('masters.items.store', $this->hotel), ['label' => $name]);
        }

        Masters::flush();
        $this->assertSame(['anita desai', 'Meera Shah', 'Zoya Khan'], Masters::values('revenue_depositor_hotel'));

        $html = $this->get(route('masters.index', ['set' => $this->hotel->key]))->getContent();
        // Look at the table cells, not the flash message that also names the last one added
        $at = fn (string $name) => strpos($html, 'fw-semibold">'.$name);
        $this->assertLessThan($at('Meera Shah'), $at('anita desai'));
        $this->assertLessThan($at('Zoya Khan'), $at('Meera Shah'));
    }
}
