<?php

namespace Tests\Feature\Units;

use App\Models\Employee;
use App\Models\FoodCashDeposit;
use App\Models\HotelCashDeposit;
use App\Models\PayrollCompany;
use App\Models\ShiftHandover;
use App\Models\StaffAdvance;
use App\Models\User;
use App\Support\UnitContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hotel Pallav and Pallav Food are one business under two names. Outside
 * Payroll nothing is filtered or split by them: Hotel and Food are simply
 * the business's two cash books, always shown side by side.
 */
class OneBusinessTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        UnitContext::flush();
        $this->user = User::factory()->admin()->create();
        $this->actingAs($this->user);
    }

    private function deposit(string $model, string $who): void
    {
        $model::create([
            'date' => now()->toDateString(), 'time' => '10:00', 'user_id' => $this->user->id,
            'full_name' => $this->user->name, 'depositor' => $who, 'amount' => 100, 'amount_in_words' => 'One Hundred Rupees Only',
        ]);
    }

    public function test_both_cash_books_are_always_shown_side_by_side(): void
    {
        $this->deposit(HotelCashDeposit::class, 'Reception Till Alpha');
        $this->deposit(FoodCashDeposit::class, 'Kitchen Till Beta');

        $this->get(route('revenue.index'))->assertOk()
            ->assertSee('Reception Till Alpha')->assertSee('Kitchen Till Beta')
            ->assertSee('cb-book hotel', false)->assertSee('cb-book food', false);
    }

    public function test_the_old_switch_cannot_hide_anything(): void
    {
        $this->deposit(HotelCashDeposit::class, 'Reception Till Alpha');
        $this->deposit(FoodCashDeposit::class, 'Kitchen Till Beta');

        $this->post(route('unit.switch'), ['unit' => 'hotel']);

        $this->assertTrue(UnitContext::isBoth());
        $this->get(route('revenue.index'))->assertSee('Kitchen Till Beta');
    }

    public function test_there_is_no_business_switcher_or_picker(): void
    {
        $this->get(route('dashboard'))->assertOk()->assertDontSee('unit-switch');
        $this->get(route('expense.index'))->assertOk()->assertDontSee('name="business_unit"', false);
        $this->get(route('shift-handover.index'))->assertOk()->assertDontSee('name="business_unit"', false);
    }

    public function test_a_hotel_and_a_food_deposit_go_to_their_own_books(): void
    {
        $this->offerDepositor('hotel', 'Front desk');
        $this->offerDepositor('food', 'Front desk');
        $row = ['date' => now()->toDateString(), 'time' => '09:00', 'depositor' => 'Front desk', 'reason' => 'Test', 'amount' => 250];

        $this->post(route('revenue.hotel.store'), $row)->assertSessionHasNoErrors();
        $this->post(route('revenue.food.store'), $row)->assertSessionHasNoErrors();

        $this->assertSame(1, HotelCashDeposit::count());
        $this->assertSame(1, FoodCashDeposit::count());
        $this->assertSame('Two Hundred Fifty Rupees Only', HotelCashDeposit::sole()->amount_in_words);
    }

    public function test_handovers_and_staff_advances_belong_to_the_whole_business(): void
    {
        $this->post(route('shift-handover.store'), [
            'date' => now()->toDateString(), 'time' => '09:00', 'shift' => 'Morning',
        ])->assertSessionHasNoErrors();
        $this->assertNull(ShiftHandover::sole()->business_unit_id);

        $company = PayrollCompany::create(['name' => 'Pallav', 'code' => 'P01', 'is_active' => true]);
        $employee = Employee::create([
            'payroll_company_id' => $company->id, 'employee_code' => 'E1', 'name' => 'Cook',
            'salary' => 20000, 'is_active' => true,
        ]);

        $this->post(route('expense.staff-advance.store'), [
            'date' => now()->toDateString(), 'time' => '12:00', 'employee_id' => $employee->id,
            'year_month' => now()->format('Y-m'), 'amount' => 1500,
        ])->assertSessionHasNoErrors();

        $this->assertNull(StaffAdvance::sole()->business_unit_id);
        $this->get(route('expense.index'))->assertSee('Cook');
    }

    public function test_someone_below_admin_cannot_choose_the_date_and_time(): void
    {
        $this->actingAs(User::factory()->editor()->create());
        $this->offerDepositor('hotel', 'Front desk');

        $this->post(route('revenue.hotel.store'), [
            'date' => '2020-01-01', 'time' => '03:00', 'depositor' => 'Front desk', 'reason' => 'Test', 'amount' => 10,
        ])->assertSessionHasNoErrors();

        $deposit = HotelCashDeposit::sole();
        $this->assertTrue($deposit->date->isToday());
        $this->assertNotSame('03:00:00', $deposit->time);

        $this->get(route('revenue.index'))->assertSee('Stamped automatically when you save.');
    }

    public function test_an_edit_below_admin_keeps_the_original_date_and_time(): void
    {
        $editor = User::factory()->editor()->create();
        $record = ShiftHandover::create([
            'date' => '2026-09-01', 'time' => '08:00', 'shift' => 'Morning',
            'user_id' => $editor->id, 'full_name' => $editor->name,
        ]);
        $this->actingAs($editor);

        $this->put(route('shift-handover.update', $record), [
            'date' => '2026-09-18', 'time' => '23:00', 'shift' => 'Night',
        ])->assertSessionHasNoErrors();

        $record->refresh();
        $this->assertSame('2026-09-01', $record->date->toDateString());
        $this->assertSame('08:00', substr((string) $record->time, 0, 5));
        $this->assertSame('Night', $record->shift);
    }

    public function test_an_admin_can_still_set_the_date_and_time(): void
    {
        $this->offerDepositor('hotel', 'Front desk');
        $this->post(route('revenue.hotel.store'), [
            'date' => '2026-09-01', 'time' => '09:15', 'depositor' => 'Front desk', 'reason' => 'Test', 'amount' => 10,
        ]);

        $this->assertSame('2026-09-01', HotelCashDeposit::sole()->date->toDateString());
    }

    public function test_handovers_page_with_the_payroll_pager(): void
    {
        foreach (range(1, 12) as $i) {
            ShiftHandover::create([
                'date' => now()->toDateString(), 'time' => sprintf('%02d:00', $i), 'shift' => 'Shift '.$i,
                'user_id' => $this->user->id, 'full_name' => $this->user->name,
            ]);
        }

        $this->get(route('shift-handover.index'))->assertOk()->assertSee('Showing 1-10 of 12');
        $this->get(route('shift-handover.index', ['page' => 2]))->assertSee('Showing 11-12 of 12');
        $this->get(route('shift-handover.index', ['per' => 25]))->assertSee('Showing 1-12 of 12');
    }
}
