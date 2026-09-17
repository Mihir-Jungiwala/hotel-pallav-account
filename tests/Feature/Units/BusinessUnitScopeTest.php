<?php

namespace Tests\Feature\Units;

use App\Models\BusinessUnit;
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

class BusinessUnitScopeTest extends TestCase
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

    private function unit(string $slug): BusinessUnit
    {
        return BusinessUnit::where('slug', $slug)->firstOrFail();
    }

    private function employee(): Employee
    {
        $company = PayrollCompany::create(['name' => 'Pallav Food', 'code' => 'PF01', 'is_active' => true]);

        return Employee::create([
            'payroll_company_id' => $company->id, 'employee_code' => 'EMP900', 'name' => 'Test Cook',
            'salary' => 20000, 'is_active' => true,
        ]);
    }

    private function deposits(): void
    {
        HotelCashDeposit::create([
            'date' => now()->toDateString(), 'time' => '10:00', 'user_id' => $this->user->id,
            'depositor' => 'Front Desk', 'amount' => 500, 'full_name' => $this->user->name,
        ]);

        FoodCashDeposit::create([
            'date' => now()->toDateString(), 'time' => '11:00', 'user_id' => $this->user->id,
            'depositor' => 'Restaurant Till', 'amount' => 900, 'full_name' => $this->user->name,
        ]);
    }

    public function test_both_units_are_seeded_and_switchable(): void
    {
        $this->assertSame(['hotel', 'food'], UnitContext::units()->pluck('slug')->all());

        $this->post(route('unit.switch'), ['unit' => 'food'])->assertSessionHasNoErrors();
        $this->assertSame('food', UnitContext::currentKey());

        $this->post(route('unit.switch'), ['unit' => UnitContext::BOTH]);
        $this->assertTrue(UnitContext::isBoth());
    }

    public function test_an_unknown_unit_is_ignored(): void
    {
        $this->post(route('unit.switch'), ['unit' => 'casino']);

        $this->assertTrue(UnitContext::isBoth());
    }

    public function test_revenue_shows_only_the_selected_business(): void
    {
        $this->deposits();

        $this->get(route('revenue.index'))->assertSee('Front Desk')->assertSee('Restaurant Till');

        $this->post(route('unit.switch'), ['unit' => 'hotel']);
        $this->get(route('revenue.index'))->assertSee('Front Desk')->assertDontSee('Restaurant Till');

        $this->post(route('unit.switch'), ['unit' => 'food']);
        $this->get(route('revenue.index'))->assertSee('Restaurant Till')->assertDontSee('Front Desk');
    }

    public function test_a_new_handover_belongs_to_the_selected_business(): void
    {
        $this->post(route('unit.switch'), ['unit' => 'food']);

        $this->post(route('shift-handover.store'), [
            'date' => now()->toDateString(), 'time' => '09:00', 'shift' => 'Morning',
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->unit('food')->id, ShiftHandover::first()->business_unit_id);
    }

    public function test_while_both_are_shown_the_form_picks_the_business(): void
    {
        $this->post(route('shift-handover.store'), [
            'date' => now()->toDateString(), 'time' => '09:00', 'shift' => 'Night',
            'business_unit' => 'food',
        ]);

        $this->assertSame($this->unit('food')->id, ShiftHandover::first()->business_unit_id);
    }

    public function test_handovers_and_advances_are_filtered_by_business(): void
    {
        foreach (['hotel', 'food'] as $slug) {
            ShiftHandover::create([
                'business_unit_id' => $this->unit($slug)->id, 'date' => now()->toDateString(),
                'time' => '08:00', 'shift' => ucfirst($slug).' shift', 'user_id' => $this->user->id,
                'full_name' => $this->user->name,
            ]);
        }

        $this->post(route('unit.switch'), ['unit' => 'hotel']);
        $this->get(route('shift-handover.index'))->assertSee('Hotel shift')->assertDontSee('Food shift');

        $this->post(route('unit.switch'), ['unit' => 'food']);
        $this->get(route('shift-handover.index'))->assertSee('Food shift')->assertDontSee('Hotel shift');
    }

    public function test_a_staff_advance_is_recorded_against_the_selected_business(): void
    {
        $this->post(route('unit.switch'), ['unit' => 'food']);

        $this->post(route('expense.staff-advance.store'), [
            'date' => now()->toDateString(), 'time' => '12:00',
            'employee_id' => $this->employee()->id,
            'year_month' => now()->format('Y-m'), 'amount' => 1500,
        ]);

        $this->assertSame($this->unit('food')->id, StaffAdvance::first()->business_unit_id);
    }

    public function test_the_switch_is_open_to_read_only_accounts(): void
    {
        $this->actingAs(User::factory()->viewer()->create())
            ->post(route('unit.switch'), ['unit' => 'food'])
            ->assertSessionHasNoErrors();

        $this->assertSame('food', UnitContext::currentKey());
    }
}
