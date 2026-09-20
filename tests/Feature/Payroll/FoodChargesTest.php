<?php

namespace Tests\Feature\Payroll;

use App\Models\AttendanceEntry;
use App\Models\AttendanceMonth;
use App\Models\AttendanceStatus;
use App\Models\Employee;
use App\Models\EmployeeSeparation;
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
 * Pallav Food cooks for both companies and alone decides the price. Hotel
 * Pallav owes it for its staff's meals; Pallav Food's own staff are a cost of
 * its own. The price is per employee per month, charged by the day at the
 * price in force that day, and a month is closed once salary is generated.
 */
class FoodChargesTest extends TestCase
{
    use RefreshDatabase;

    private PayrollCompany $hotel;

    private PayrollCompany $food;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 9, 20, 10));

        PayrollCompany::ensureFixed();
        $this->hotel = PayrollCompany::where('code', 'HP01')->firstOrFail();
        $this->food = PayrollCompany::where('code', 'PF01')->firstOrFail();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function staff(PayrollCompany $company, string $code, string $name, string $joined, bool $eats = true, bool $slip = true): Employee
    {
        $employee = Employee::create([
            'payroll_company_id' => $company->id, 'employee_code' => $code, 'name' => $name,
            'designation' => 'Front Office', 'joining_date' => $joined, 'salary' => 20000,
            'daily_working_hours' => 8, 'payment_mode' => 'Cash', 'is_active' => true,
            'eats_at_pallav_food' => $eats,
        ]);

        if ($slip) {
            $this->slip($company, $employee, 2026, 9);
        }

        return $employee;
    }

    private function slip(PayrollCompany $company, Employee $employee, int $year, int $month): SalaryProcessing
    {
        return SalaryProcessing::create([
            'payroll_company_id' => $company->id, 'employee_id' => $employee->id,
            'company_name' => $company->name, 'employee_name' => $employee->name, 'employee_code' => $employee->employee_code,
            'designation' => 'Front Office', 'year' => $year, 'month' => $month, 'total_days_in_month' => 30,
            'days_100' => 30, 'total_payable_days' => 30, 'daily_salary' => 666, 'hourly_rate' => 83,
            'monthly_salary' => 20000, 'daily_working_hours' => 8, 'attendance_salary' => 20000,
            'net_salary' => 20000, 'payment_mode' => 'Cash', 'payment_status' => 'Pending', 'processed_at' => now(),
        ]);
    }

    /** A price is Pallav Food's, whichever company is being billed. */
    private function price(float $amount, string $from = '2026-01-01'): FoodChargeRate
    {
        return FoodChargeRate::create(['payroll_company_id' => $this->food->id, 'monthly_amount' => $amount, 'effective_from' => $from]);
    }

    private function open(PayrollCompany $company): void
    {
        $this->get(route('payroll.index', ['current_company' => $company->id]));
    }

    /* ---------------------------------------------------------------- days */

    public function test_days_count_from_joining_to_last_day_with_skipped_days_left_out(): void
    {
        $sept = Carbon::create(2026, 9, 1);

        $this->assertCount(30, FoodCharges::countedDays($sept, Carbon::create(2025, 1, 5)));
        $this->assertCount(16, FoodCharges::countedDays($sept, Carbon::create(2026, 9, 15)));   // 15th to 30th
        $this->assertCount(0, FoodCharges::countedDays($sept, Carbon::create(2026, 10, 2)));    // not yet joined

        // Left on the 10th: the first ten days
        $this->assertCount(10, FoodCharges::countedDays($sept, Carbon::create(2025, 1, 5), Carbon::create(2026, 9, 10)));

        // Days marked with a status that leaves food out are not counted
        $this->assertCount(27, FoodCharges::countedDays($sept, null, null, [3, 4, 20]));
        $this->assertNotContains(4, FoodCharges::countedDays($sept, null, null, [3, 4, 20]));
    }

    /* ---------------------------------------------------------------- price */

    public function test_the_price_is_pallav_foods_and_both_companies_bill_at_it(): void
    {
        $this->price(3000);
        $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');
        $this->staff($this->food, 'F-1', 'Cook', '2025-06-01');

        $hotel = FoodCharges::statement($this->hotel, 2026, 9);
        $food = FoodCharges::statement($this->food, 2026, 9);

        $this->assertSame(3000.0, $hotel['total']);
        $this->assertSame(3000.0, $food['total']);

        // Hotel Pallav owes it; Pallav Food's own staff are just a cost
        $this->assertTrue($hotel['owed']);
        $this->assertSame('Pay to Pallav Food', $hotel['title']);
        $this->assertFalse($food['owed']);
        $this->assertSame('Staff meals', $food['title']);
    }

    public function test_only_pallav_foods_payroll_can_set_the_price(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->open($this->hotel);
        $this->get(route('payroll.food-price.index'))->assertNotFound();
        $this->post(route('payroll.food-price.store'), ['monthly_amount' => 9999, 'effective_from' => '2026-10-01'])->assertNotFound();
        $this->get(route('payroll.staff.index'))->assertDontSee('Meal Price');

        $this->open($this->food);
        $this->get(route('payroll.food-price.index'))->assertOk()->assertSee('Set a new price');
        $this->get(route('payroll.staff.index'))->assertSee('Meal Price')->assertSee('Staff Meals');

        $this->assertSame(0, FoodChargeRate::count());
    }

    public function test_a_price_change_starts_on_its_day_and_the_old_price_stays_on_record(): void
    {
        $this->price(3000, '2026-01-01');
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', slip: false);

        $this->actingAs(User::factory()->admin()->create());
        $this->open($this->food);

        // Changed on the 15th of the current, still open, month (today is the 20th)
        $this->post(route('payroll.food-price.store'), ['monthly_amount' => 3300, 'effective_from' => '2026-09-15'])->assertSessionHasNoErrors();

        // Both prices are still there
        $this->assertSame([3000.0, 3300.0], FoodCharges::rates()->pluck('monthly_amount')->map(fn ($a) => (float) $a)->all());

        // Salary generated for August and September once the prices were set
        $this->slip($this->hotel, $employee, 2026, 8);
        $this->slip($this->hotel, $employee, 2026, 9);

        // September has 30 days: 14 at 3000 and 16 at 3300, each worth a day's share of the month
        $statement = FoodCharges::statement($this->hotel, 2026, 9);

        $this->assertSame(round(3000 * 14 / 30 + 3300 * 16 / 30, 2), $statement['rows'][0]['amount']);
        $this->assertCount(2, $statement['prices']);
        $this->assertSame(3000.0, $statement['prices'][0]['amount']);
        $this->assertSame(3300.0, $statement['prices'][1]['amount']);

        // August, before the change, is untouched
        $this->assertSame(3000.0, FoodCharges::statement($this->hotel, 2026, 8)['total']);
    }

    public function test_a_second_price_on_the_same_day_replaces_the_first_rather_than_stacking(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->open($this->food);

        $this->post(route('payroll.food-price.store'), ['monthly_amount' => 2400, 'effective_from' => '2026-09-10']);
        $this->post(route('payroll.food-price.store'), ['monthly_amount' => 2600, 'effective_from' => '2026-09-10']);

        $this->assertSame(1, FoodChargeRate::count());
        $this->assertSame(2600.0, (float) FoodChargeRate::first()->monthly_amount);
    }

    /* -------------------------------------------------------------- closing */

    public function test_a_month_is_closed_to_price_changes_once_salary_is_generated_in_either_company(): void
    {
        $this->price(3000, '2026-01-01');

        // Hotel Pallav generated August; Pallav Food has not
        $hotelStaff = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', slip: false);
        $this->slip($this->hotel, $hotelStaff, 2026, 8);

        $this->actingAs(User::factory()->admin()->create());
        $this->open($this->food);

        // August (and anything before it) is closed, even though Pallav Food itself has no salary for it
        $this->post(route('payroll.food-price.store'), ['monthly_amount' => 9000, 'effective_from' => '2026-08-15'])
            ->assertSessionHasErrors('effective_from');
        $this->post(route('payroll.food-price.store'), ['monthly_amount' => 9000, 'effective_from' => '2026-03-01'])
            ->assertSessionHasErrors('effective_from');

        $this->assertSame(1, FoodChargeRate::count());

        // September is open
        $this->post(route('payroll.food-price.store'), ['monthly_amount' => 3300, 'effective_from' => '2026-09-01'])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, FoodChargeRate::count());
    }

    public function test_a_price_inside_a_closed_month_cannot_be_removed_but_an_open_one_can(): void
    {
        $old = $this->price(3000, '2026-01-01');
        $new = $this->price(3300, '2026-09-01');
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', slip: false);
        $this->slip($this->hotel, $employee, 2026, 8);

        $this->actingAs(User::factory()->admin()->create());
        $this->open($this->food);

        $this->delete(route('payroll.food-price.destroy', $old))->assertSessionHas('error');
        $this->assertDatabaseHas('food_charge_rates', ['id' => $old->id]);

        $this->delete(route('payroll.food-price.destroy', $new))->assertSessionHas('success');
        $this->assertDatabaseMissing('food_charge_rates', ['id' => $new->id]);
    }

    public function test_the_price_page_says_which_months_are_closed(): void
    {
        $this->price(3000, '2026-01-01');
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', slip: false);
        $this->slip($this->hotel, $employee, 2026, 8);

        $this->actingAs(User::factory()->admin()->create());
        $this->open($this->food);

        $this->get(route('payroll.food-price.index'))->assertOk()
            ->assertSee('Up to Aug 2026')->assertSee('Closed - salary generated')->assertSee('September 2026');
    }

    /* ------------------------------------------------------ status: no food */

    public function test_days_with_a_status_that_leaves_food_out_are_not_charged(): void
    {
        $this->price(3000);
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');

        $month = AttendanceMonth::create(['payroll_company_id' => $this->hotel->id, 'year' => 2026, 'month' => 9]);
        $leave = AttendanceStatus::create([
            'payroll_company_id' => $this->hotel->id, 'name' => 'Leave', 'shortcut_key' => 'L', 'color' => '#ff0000',
            'attendance_percentage' => 0, 'status_type' => 'Unpaid', 'skips_food' => true,
        ]);

        // Three days on leave (food skipped), and one absent day whose status does not skip food
        foreach ([3, 4, 5] as $day) {
            AttendanceEntry::create(['attendance_month_id' => $month->id, 'employee_id' => $employee->id, 'day' => $day,
                'attendance_status_id' => $leave->id, 'shortcut_key' => 'L', 'attendance_percentage' => 0, 'skips_food' => true]);
        }
        AttendanceEntry::create(['attendance_month_id' => $month->id, 'employee_id' => $employee->id, 'day' => 9,
            'shortcut_key' => 'A', 'attendance_percentage' => 0, 'skips_food' => false]);

        $row = FoodCharges::statement($this->hotel, 2026, 9)['rows'][0];

        $this->assertSame(27, $row['days']);
        $this->assertSame(3, $row['left_out']);
        $this->assertSame(2700.0, $row['amount']);
        $this->assertStringContainsString('3 days not counted', $row['note']);
    }

    public function test_the_status_option_is_offered_to_both_companies_and_saved_and_copied_onto_the_day(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        foreach ([$this->hotel, $this->food] as $company) {
            $this->open($company);

            $this->get(route('payroll.attendance-status.index'))->assertOk()->assertSee('Meals are not counted on this status');

            $this->post(route('payroll.attendance-status.store'), [
                'name' => 'Leave', 'shortcut_key' => 'L', 'color' => '#aa0000',
                'attendance_percentage' => 0, 'status_type' => 'Unpaid', 'skips_food' => '1',
            ])->assertSessionHasNoErrors();

            $status = AttendanceStatus::where('payroll_company_id', $company->id)->firstOrFail();
            $this->assertTrue($status->skips_food);

            // Saving attendance copies the option onto the day it is used on
            $employee = $this->staff($company, 'X-'.$company->id, 'Someone', '2025-01-01', slip: false);
            $month = AttendanceMonth::create(['payroll_company_id' => $company->id, 'year' => 2026, 'month' => 9]);

            $this->post(route('payroll.attendance.save', $month), ['attendance' => [$employee->id => [5 => 'L']]]);
            $this->assertTrue((bool) AttendanceEntry::where('employee_id', $employee->id)->where('day', 5)->value('skips_food'));

            // Turning the option off reaches a month that is still open...
            $this->put(route('payroll.attendance-status.update', $status), [
                'name' => 'Leave', 'shortcut_key' => 'L', 'color' => '#aa0000',
                'attendance_percentage' => 0, 'status_type' => 'Unpaid', 'skips_food' => '0',
            ])->assertSessionHasNoErrors();
            $this->assertFalse((bool) AttendanceEntry::where('employee_id', $employee->id)->where('day', 5)->value('skips_food'));
        }
    }

    public function test_a_month_whose_salary_is_generated_keeps_its_food_days_when_the_status_changes(): void
    {
        $this->price(3000);
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');

        $month = AttendanceMonth::create(['payroll_company_id' => $this->hotel->id, 'year' => 2026, 'month' => 9, 'is_locked' => true]);
        $leave = AttendanceStatus::create([
            'payroll_company_id' => $this->hotel->id, 'name' => 'Leave', 'shortcut_key' => 'L', 'color' => '#ff0000',
            'attendance_percentage' => 0, 'status_type' => 'Unpaid', 'skips_food' => true,
        ]);
        AttendanceEntry::create(['attendance_month_id' => $month->id, 'employee_id' => $employee->id, 'day' => 5,
            'attendance_status_id' => $leave->id, 'shortcut_key' => 'L', 'attendance_percentage' => 0, 'skips_food' => true]);

        $this->actingAs(User::factory()->admin()->create());
        $this->open($this->hotel);

        $this->put(route('payroll.attendance-status.update', $leave), [
            'name' => 'Leave', 'shortcut_key' => 'L', 'color' => '#ff0000',
            'attendance_percentage' => 0, 'status_type' => 'Unpaid', 'skips_food' => '0',
        ]);

        // The locked month's bill is exactly what it was
        $this->assertTrue((bool) AttendanceEntry::first()->skips_food);
        $this->assertSame(29, FoodCharges::statement($this->hotel, 2026, 9)['rows'][0]['days']);
    }

    /* ------------------------------------------------------------ who eats */

    public function test_someone_who_has_left_is_charged_only_up_to_their_last_day(): void
    {
        $this->price(3000);
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');

        EmployeeSeparation::create([
            'payroll_company_id' => $this->hotel->id, 'employee_id' => $employee->id,
            'separation_type' => 'Resignation', 'resignation_date' => '2026-09-01', 'reason' => 'Moving city',
            'last_working_date' => '2026-09-10', 'status' => 'Relieved',
        ]);

        $statement = FoodCharges::statement($this->hotel, 2026, 9);
        $this->assertSame(10, $statement['rows'][0]['days']);
        $this->assertSame(1000.0, $statement['total']);
        $this->assertNull(FoodCharges::statement($this->hotel, 2026, 10));
    }

    public function test_only_staff_marked_as_eating_there_are_charged(): void
    {
        $this->price(3000);
        $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');
        $this->staff($this->hotel, 'E-2', 'Own Lunch', '2025-06-01', eats: false);

        $this->assertSame(['Asha Menon'], array_column(FoodCharges::statement($this->hotel, 2026, 9)['rows'], 'name'));
    }

    public function test_the_meals_switch_is_offered_in_both_companies(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        foreach ([$this->hotel, $this->food] as $company) {
            $this->open($company);
            $this->get(route('payroll.staff.index'))->assertSee('Takes meals at Pallav Food');
            $this->get(route('payroll.food-charge.index'))->assertOk()->assertSee('Who eats at Pallav Food');
        }
    }

    public function test_the_switch_on_the_page_includes_and_excludes_someone(): void
    {
        $this->price(3000);
        $employee = $this->staff($this->food, 'F-1', 'Cook', '2025-06-01', eats: false);

        $this->actingAs(User::factory()->admin()->create());
        $this->open($this->food);

        $this->post(route('payroll.food-charge.toggle', $employee))->assertSessionHas('success');
        $this->assertTrue($employee->fresh()->eats_at_pallav_food);

        $this->post(route('payroll.food-charge.toggle', $employee));
        $this->assertFalse($employee->fresh()->eats_at_pallav_food);
    }

    public function test_there_is_no_statement_until_pallav_food_has_set_a_price(): void
    {
        $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');
        $this->assertNull(FoodCharges::statement($this->hotel, 2026, 9));

        $this->price(3000);
        $this->assertNotNull(FoodCharges::statement($this->hotel, 2026, 9));
    }

    /* -------------------------------------------------------------- reports */

    public function test_the_monthly_report_carries_salary_and_food_together_with_one_total(): void
    {
        $this->price(3000);
        $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');
        $this->staff($this->hotel, 'E-2', 'Bhavin Shah', '2026-09-16'); // 15 of 30 days

        $this->actingAs(User::factory()->admin()->create());
        $this->open($this->hotel);

        // Two staff on Rs 20,000 net each = 40,000, plus Rs 4,500 for Pallav Food = 44,500
        $this->get(route('payroll.monthly-report.index', ['year' => 2026, 'month' => 9]))
            ->assertOk()->assertSee('Pay to Pallav Food')->assertSee('4,500.00')->assertSee('15 / 30')
            ->assertSee('never taken from salary')
            ->assertSee('Net salary payout')->assertSee('40,000.00')->assertSee('44,500.00');

        $body = $this->get(route('payroll.report.monthly', ['year' => 2026, 'month' => 9]))->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF', $body);
        $this->assertSame(1, PayrollPdf::pageCount($body));
    }

    public function test_pallav_foods_own_report_shows_staff_meals_as_a_cost_not_a_payable(): void
    {
        $this->price(3000);
        $this->staff($this->food, 'F-1', 'Cook', '2025-06-01');

        $this->actingAs(User::factory()->admin()->create());
        $this->open($this->food);

        $this->get(route('payroll.monthly-report.index', ['year' => 2026, 'month' => 9]))
            ->assertOk()->assertSee('Staff meals')->assertSee('A cost of Pallav Food')
            ->assertDontSee('Pay to Pallav Food')->assertDontSee('Payable to');

        $body = $this->get(route('payroll.report.monthly', ['year' => 2026, 'month' => 9]))->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF', $body);
    }

    public function test_the_price_change_within_a_month_is_explained_on_the_bill(): void
    {
        $this->price(3000, '2026-01-01');
        $this->price(3300, '2026-09-15');
        $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');

        $this->actingAs(User::factory()->admin()->create());
        $this->open($this->hotel);

        $this->get(route('payroll.food-charge.index', ['year' => 2026, 'month' => 9]))
            ->assertOk()->assertSee('The price changed during September')->assertSee('from 15 Sep');
    }

    /* ------------------------------------------- only after salary is generated */

    public function test_nothing_is_worked_out_until_salary_has_been_generated_for_the_month(): void
    {
        $this->price(3000);
        $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', slip: false);
        $this->staff($this->food, 'F-1', 'Cook', '2025-06-01', slip: false);

        // Price set, people switched on - and still no bill for either company
        $this->assertNull(FoodCharges::statement($this->hotel, 2026, 9));
        $this->assertNull(FoodCharges::statement($this->food, 2026, 9));

        $this->actingAs(User::factory()->admin()->create());
        $this->open($this->hotel);

        $this->get(route('payroll.food-charge.index', ['year' => 2026, 'month' => 9]))
            ->assertOk()->assertSee('Not worked out yet for September 2026')
            ->assertSee('calculated once salary has been generated');

        $this->get(route('payroll.monthly-report.index', ['year' => 2026, 'month' => 9]))
            ->assertOk()->assertDontSee('Pay to Pallav Food');
    }

    public function test_the_bill_appears_when_salary_is_generated_and_only_for_people_in_that_salary_run(): void
    {
        $this->price(3000);
        $asha = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', slip: false);
        $this->staff($this->hotel, 'E-2', 'Bhavin Shah', '2025-06-01', slip: false);

        // Salary is generated for Asha only (Bhavin was not in that run)
        $this->slip($this->hotel, $asha, 2026, 9);

        $statement = FoodCharges::statement($this->hotel, 2026, 9);

        $this->assertSame(['Asha Menon'], array_column($statement['rows'], 'name'));
        $this->assertSame(3000.0, $statement['total']);
    }

    public function test_generating_salary_in_one_company_does_not_produce_a_bill_in_the_other(): void
    {
        $this->price(3000);
        $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');                       // Hotel Pallav generated September
        $this->staff($this->food, 'F-1', 'Cook', '2025-06-01', slip: false);                  // Pallav Food has not

        $this->assertNotNull(FoodCharges::statement($this->hotel, 2026, 9));
        $this->assertNull(FoodCharges::statement($this->food, 2026, 9));
    }

    /* ------------------------------------------------ not ahead of today */

    public function test_a_price_cannot_start_after_today(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->open($this->food);

        // "Today" is 20 September 2026
        $this->post(route('payroll.food-price.store'), ['monthly_amount' => 3300, 'effective_from' => '2026-09-21'])
            ->assertSessionHasErrors('effective_from');
        $this->post(route('payroll.food-price.store'), ['monthly_amount' => 3300, 'effective_from' => '2026-10-01'])
            ->assertSessionHasErrors('effective_from');
        $this->assertSame(0, FoodChargeRate::count());

        // Today is fine, and so is any earlier day in an open month
        $this->post(route('payroll.food-price.store'), ['monthly_amount' => 3300, 'effective_from' => '2026-09-20'])
            ->assertSessionHasNoErrors();
        $this->post(route('payroll.food-price.store'), ['monthly_amount' => 3100, 'effective_from' => '2026-09-05'])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, FoodChargeRate::count());
    }

    public function test_the_date_field_stops_at_today_and_starts_at_the_first_open_month(): void
    {
        $this->price(3000);
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', slip: false);
        $this->slip($this->hotel, $employee, 2026, 8);

        $this->actingAs(User::factory()->admin()->create());
        $this->open($this->food);

        $this->get(route('payroll.food-price.index'))->assertOk()
            ->assertSee('max="2026-09-20"', false)->assertSee('min="2026-09-01"', false)
            ->assertSee('value="2026-09-20"', false)
            ->assertSee('cannot be later than today');
    }

    public function test_the_meal_price_and_staff_meals_are_named_in_the_menu_and_the_old_names_are_gone(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->open($this->food);

        $this->get(route('payroll.staff.index'))
            ->assertSee('Meal Price')->assertSee('Staff Meals')
            ->assertDontSee('Food Price')->assertDontSee('Food Charges');
    }
}
