<?php

namespace Tests\Feature\Payroll;

use App\Models\Employee;
use App\Models\FoodChargeRate;
use App\Models\PayrollCompany;
use App\Models\SalaryProcessing;
use App\Models\User;
use App\Support\FoodCharges;
use App\Support\PayrollPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Hotel Pallav's staff eat at Pallav Food and the owner pays: a fixed monthly
 * amount per employee, counted by calendar days from the joining date, shown
 * as "Pay to Pallav Food" and never taken from salary.
 */
class FoodChargesTest extends TestCase
{
    use RefreshDatabase;

    private PayrollCompany $hotel;

    private PayrollCompany $food;

    protected function setUp(): void
    {
        parent::setUp();

        PayrollCompany::ensureFixed();
        $this->hotel = PayrollCompany::where('code', 'HP01')->firstOrFail();
        $this->food = PayrollCompany::where('code', 'PF01')->firstOrFail();
    }

    private function staff(PayrollCompany $company, string $code, string $name, string $joined, bool $eats = true): Employee
    {
        $employee = Employee::create([
            'payroll_company_id' => $company->id, 'employee_code' => $code, 'name' => $name,
            'designation' => 'Front Office', 'joining_date' => $joined, 'salary' => 20000,
            'daily_working_hours' => 8, 'payment_mode' => 'Cash', 'is_active' => true,
            'eats_at_pallav_food' => $eats,
        ]);

        SalaryProcessing::create([
            'payroll_company_id' => $company->id, 'employee_id' => $employee->id,
            'company_name' => $company->name, 'employee_name' => $name, 'employee_code' => $code,
            'designation' => 'Front Office', 'year' => 2026, 'month' => 9, 'total_days_in_month' => 30,
            'days_100' => 30, 'total_payable_days' => 30, 'daily_salary' => 666, 'hourly_rate' => 83,
            'monthly_salary' => 20000, 'daily_working_hours' => 8, 'attendance_salary' => 20000,
            'net_salary' => 20000, 'payment_mode' => 'Cash', 'payment_status' => 'Pending', 'processed_at' => now(),
        ]);

        return $employee;
    }

    private function rate(float $amount, string $from = '2026-01-01', ?PayrollCompany $company = null): void
    {
        FoodChargeRate::create(['payroll_company_id' => ($company ?? $this->hotel)->id, 'monthly_amount' => $amount, 'effective_from' => $from]);
    }

    public function test_days_count_from_the_joining_date_and_every_day_counts(): void
    {
        $sept = Carbon::create(2026, 9, 1);

        $this->assertSame(30, FoodCharges::daysCounted($sept, Carbon::create(2025, 1, 5)));   // long before: the whole month
        $this->assertSame(30, FoodCharges::daysCounted($sept, Carbon::create(2026, 9, 1)));   // joined on the 1st
        $this->assertSame(16, FoodCharges::daysCounted($sept, Carbon::create(2026, 9, 15)));  // 15th to 30th, both included
        $this->assertSame(1, FoodCharges::daysCounted($sept, Carbon::create(2026, 9, 30)));
        $this->assertSame(0, FoodCharges::daysCounted($sept, Carbon::create(2026, 10, 2)));   // not yet joined
    }

    public function test_a_full_month_is_the_full_amount_and_a_mid_month_joiner_is_prorated(): void
    {
        $this->rate(3000);
        $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');
        $this->staff($this->hotel, 'E-2', 'Bhavin Shah', '2026-09-16'); // 15 of 30 days

        $statement = FoodCharges::statement($this->hotel, 2026, 9);

        $this->assertSame('Pallav Food', $statement['payee']);
        $this->assertSame([3000.0, 1500.0], array_column($statement['rows'], 'amount'));
        $this->assertSame([30, 15], array_column($statement['rows'], 'days'));
        $this->assertSame(4500.0, $statement['total']);
    }

    public function test_only_staff_marked_as_eating_there_are_charged(): void
    {
        $this->rate(3000);
        $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');
        $this->staff($this->hotel, 'E-2', 'Own Lunch', '2025-06-01', eats: false);

        $rows = FoodCharges::statement($this->hotel, 2026, 9)['rows'];

        $this->assertSame(['Asha Menon'], array_column($rows, 'name'));
    }

    public function test_changing_the_amount_never_rewrites_an_earlier_month(): void
    {
        $this->rate(2000, '2026-01-01');
        $this->rate(2500, '2026-10-01');
        $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');

        $this->assertSame(2000.0, FoodCharges::statement($this->hotel, 2026, 9)['total']);
        $this->assertSame(2500.0, FoodCharges::rateFor($this->hotel, Carbon::create(2026, 10, 1)));
    }

    public function test_there_is_no_statement_without_a_rate_and_none_for_pallav_food_itself(): void
    {
        $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');
        $this->assertNull(FoodCharges::statement($this->hotel, 2026, 9)); // no rate yet

        $this->rate(3000);
        $this->rate(3000, '2026-01-01', $this->food);
        $this->staff($this->food, 'F-1', 'Cook', '2025-06-01');
        $this->assertNull(FoodCharges::statement($this->food, 2026, 9));
    }

    public function test_the_monthly_report_shows_what_is_payable_and_both_pdfs_render(): void
    {
        $this->rate(3000);
        $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');
        $this->staff($this->hotel, 'E-2', 'Bhavin Shah', '2026-09-16');

        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('payroll.index', ['current_company' => $this->hotel->id]));

        $this->get(route('payroll.monthly-report.index', ['year' => 2026, 'month' => 9]))
            ->assertOk()->assertSee('Pay to Pallav Food')->assertSee('4,500.00')->assertSee('15 / 30')
            ->assertSee('never taken from salary');

        foreach ([route('payroll.report.food-charges', ['year' => 2026, 'month' => 9]), route('payroll.report.monthly', ['year' => 2026, 'month' => 9])] as $url) {
            $body = $this->get($url)->assertOk()->getContent();
            $this->assertStringStartsWith('%PDF', $body);
            $this->assertGreaterThanOrEqual(1, PayrollPdf::pageCount($body));
        }
    }

    public function test_pallav_food_sees_no_food_section_and_a_month_with_no_charge_has_no_statement(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('payroll.index', ['current_company' => $this->food->id]));
        $this->staff($this->food, 'F-1', 'Cook', '2025-06-01');

        $this->get(route('payroll.monthly-report.index', ['year' => 2026, 'month' => 9]))->assertOk()->assertDontSee('Pay to Pallav Food');
        $this->get(route('payroll.report.food-charges', ['year' => 2026, 'month' => 9]))->assertNotFound();
    }

    public function test_the_meals_switch_and_the_amount_are_only_offered_to_hotel_pallav(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->get(route('payroll.index', ['current_company' => $this->hotel->id]));
        $this->get(route('payroll.staff.index'))->assertSee('Meals from Pallav Food');

        $this->get(route('payroll.index', ['current_company' => $this->food->id]));
        $this->get(route('payroll.staff.index'))->assertDontSee('Meals from Pallav Food');
    }

    public function test_the_amount_is_saved_from_the_food_charges_page_and_the_same_month_is_not_duplicated(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('payroll.index', ['current_company' => $this->hotel->id]));

        $this->post(route('payroll.food-charge.rate'), ['monthly_amount' => 2400, 'effective_from' => '2026-09'])->assertSessionHas('success');
        $this->post(route('payroll.food-charge.rate'), ['monthly_amount' => 2600, 'effective_from' => '2026-09']);

        $this->assertSame(1, FoodChargeRate::count());
        $this->assertSame(2600.0, FoodCharges::rateFor($this->hotel, Carbon::create(2026, 9, 1)));

        // A later month is a new amount beside it, not a replacement
        $this->post(route('payroll.food-charge.rate'), ['monthly_amount' => 3000, 'effective_from' => '2026-10']);
        $this->assertSame(2, FoodChargeRate::count());
        $this->assertSame(2600.0, FoodCharges::rateFor($this->hotel, Carbon::create(2026, 9, 1)));
        $this->assertSame(3000.0, FoodCharges::rateFor($this->hotel, Carbon::create(2026, 10, 1)));
    }

    public function test_the_food_charges_page_belongs_to_hotel_pallav_alone(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('payroll.index', ['current_company' => $this->hotel->id]));
        $this->get(route('payroll.food-charge.index'))->assertOk()->assertSee('Who eats at Pallav Food');
        $this->get(route('payroll.staff.index'))->assertSee('Food Charges');

        $this->get(route('payroll.index', ['current_company' => $this->food->id]));
        $this->get(route('payroll.food-charge.index'))->assertNotFound();
        $this->get(route('payroll.staff.index'))->assertDontSee('Food Charges');
    }

    public function test_the_switch_on_the_page_includes_and_excludes_someone(): void
    {
        $this->rate(3000);
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', eats: false);

        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('payroll.index', ['current_company' => $this->hotel->id]));

        $this->post(route('payroll.food-charge.toggle', $employee))->assertSessionHas('success');
        $this->assertTrue($employee->fresh()->eats_at_pallav_food);

        $this->post(route('payroll.food-charge.toggle', $employee));
        $this->assertFalse($employee->fresh()->eats_at_pallav_food);
    }

    public function test_someone_who_has_left_is_charged_only_up_to_their_last_day(): void
    {
        $this->rate(3000);
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');

        \App\Models\EmployeeSeparation::create([
            'payroll_company_id' => $this->hotel->id, 'employee_id' => $employee->id,
            'separation_type' => 'Resignation', 'resignation_date' => '2026-09-01', 'reason' => 'Moving city',
            'last_working_date' => '2026-09-10', 'status' => 'Relieved',
        ]);

        $statement = FoodCharges::statement($this->hotel, 2026, 9);
        $this->assertSame(10, $statement['rows'][0]['days']);
        $this->assertSame(1000.0, $statement['total']);

        // and nothing at all the month after
        $this->assertNull(FoodCharges::statement($this->hotel, 2026, 10));
    }

    public function test_the_meals_flag_is_saved_only_for_hotel_pallav_staff(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $employee = $this->staff($this->food, 'F-1', 'Cook', '2025-06-01', eats: false);
        $this->get(route('payroll.index', ['current_company' => $this->food->id]));

        $this->put(route('payroll.employee.update', $employee), [
            'employee_code' => 'F-1', 'name' => 'Cook', 'designation' => 'Cook', 'joining_date' => '2025-06-01', 'salary' => 20000,
            'daily_working_hours' => 8, 'payment_mode' => 'Cash', 'contact_country' => '91', 'contact_number' => '9000000000',
            'email' => 'cook@example.com', 'eats_at_pallav_food' => '1',
        ]);

        $this->assertFalse($employee->fresh()->eats_at_pallav_food);
    }
}
