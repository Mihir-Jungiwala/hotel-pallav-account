<?php

namespace Tests\Feature\Payroll;

use App\Models\AttendanceEntry;
use App\Models\AttendanceMonth;
use App\Models\AttendanceStatus;
use App\Models\Employee;
use App\Models\EmployeeMealPeriod;
use App\Models\EmployeeSeparation;
use App\Models\FoodChargeRate;
use App\Models\PayrollCompany;
use App\Models\PayrollLog;
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
 * price in force that day. Who takes meals is a set of dated records per
 * person, and a month is closed once its salary is generated.
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

    /** A person, who takes meals from the day they joined unless told otherwise. */
    private function staff(PayrollCompany $company, string $code, string $name, string $joined, bool $eats = true, bool $slip = true): Employee
    {
        $employee = Employee::create([
            'payroll_company_id' => $company->id, 'employee_code' => $code, 'name' => $name,
            'designation' => 'Front Office', 'joining_date' => $joined, 'salary' => 20000,
            'daily_working_hours' => 8, 'payment_mode' => 'Cash', 'is_active' => true,
        ]);

        if ($eats) {
            $this->meals($employee, $joined);
        }

        if ($slip) {
            $this->slip($company, $employee, 2026, 9);
        }

        return $employee;
    }

    private function meals(Employee $employee, string $from, ?string $to = null): EmployeeMealPeriod
    {
        return EmployeeMealPeriod::create([
            'payroll_company_id' => $employee->payroll_company_id, 'employee_id' => $employee->id,
            'starts_on' => $from, 'ends_on' => $to,
        ]);
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

    private function admin(): void
    {
        $this->actingAs(User::factory()->admin()->create());
    }

    private function billFor(PayrollCompany $company, int $month = 9): ?array
    {
        return FoodCharges::statement($company, 2026, $month);
    }

    /* ---------------------------------------------------------------- days */

    public function test_days_count_within_service_and_meals_records_with_skipped_days_left_out(): void
    {
        $sept = Carbon::create(2026, 9, 1);

        $this->assertCount(30, FoodCharges::countedDays($sept, Carbon::create(2025, 1, 5)));
        $this->assertCount(16, FoodCharges::countedDays($sept, Carbon::create(2026, 9, 15)));   // 15th to 30th
        $this->assertCount(0, FoodCharges::countedDays($sept, Carbon::create(2026, 10, 2)));    // not yet joined
        $this->assertCount(10, FoodCharges::countedDays($sept, Carbon::create(2025, 1, 5), Carbon::create(2026, 9, 10)));
        $this->assertCount(27, FoodCharges::countedDays($sept, null, null, [3, 4, 20]));

        // Only the days inside a meals record count
        $records = collect([
            new EmployeeMealPeriod(['starts_on' => '2026-09-05', 'ends_on' => '2026-09-09']),
            new EmployeeMealPeriod(['starts_on' => '2026-09-25', 'ends_on' => null]),
        ]);
        $days = FoodCharges::countedDays($sept, null, null, [], $records);

        $this->assertSame([5, 6, 7, 8, 9, 25, 26, 27, 28, 29, 30], $days);
    }

    /* --------------------------------------------------------------- price */

    public function test_the_price_is_pallav_foods_and_both_companies_bill_at_it(): void
    {
        $this->price(3000);
        $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');
        $this->staff($this->food, 'F-1', 'Cook', '2025-06-01');

        $hotel = $this->billFor($this->hotel);
        $food = $this->billFor($this->food);

        $this->assertSame(3000.0, $hotel['total']);
        $this->assertSame(3000.0, $food['total']);
        $this->assertTrue($hotel['owed']);
        $this->assertSame('Pay to Pallav Food', $hotel['title']);
        $this->assertFalse($food['owed']);
        $this->assertSame('Staff meals', $food['title']);
    }

    public function test_only_pallav_foods_payroll_can_set_the_price(): void
    {
        $this->admin();

        $this->open($this->hotel);
        $this->get(route('payroll.food-price.index'))->assertNotFound();
        $this->post(route('payroll.food-price.store'), ['monthly_amount' => 9999, 'effective_from' => '2026-09-10'])->assertNotFound();
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

        $this->admin();
        $this->open($this->food);

        $this->post(route('payroll.food-price.store'), ['monthly_amount' => 3300, 'effective_from' => '2026-09-15'])->assertSessionHasNoErrors();
        $this->assertSame([3000.0, 3300.0], FoodCharges::rates()->pluck('monthly_amount')->map(fn ($a) => (float) $a)->all());

        $this->slip($this->hotel, $employee, 2026, 8);
        $this->slip($this->hotel, $employee, 2026, 9);

        // September has 30 days: 14 at 3000 and 16 at 3300
        $statement = $this->billFor($this->hotel);
        $this->assertSame(round(3000 * 14 / 30 + 3300 * 16 / 30, 2), $statement['rows'][0]['amount']);
        $this->assertCount(2, $statement['prices']);

        $this->assertSame(3000.0, $this->billFor($this->hotel, 8)['total']);
    }

    public function test_a_second_price_on_the_same_day_replaces_the_first_rather_than_stacking(): void
    {
        $this->admin();
        $this->open($this->food);

        $this->post(route('payroll.food-price.store'), ['monthly_amount' => 2400, 'effective_from' => '2026-09-10']);
        $this->post(route('payroll.food-price.store'), ['monthly_amount' => 2600, 'effective_from' => '2026-09-10']);

        $this->assertSame(1, FoodChargeRate::count());
        $this->assertSame(2600.0, (float) FoodChargeRate::first()->monthly_amount);
    }

    public function test_a_month_is_closed_to_price_changes_once_salary_is_generated_in_either_company(): void
    {
        $this->price(3000, '2026-01-01');
        $hotelStaff = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', slip: false);
        $this->slip($this->hotel, $hotelStaff, 2026, 8);

        $this->admin();
        $this->open($this->food);

        $this->post(route('payroll.food-price.store'), ['monthly_amount' => 9000, 'effective_from' => '2026-08-15'])->assertSessionHasErrors('effective_from');
        $this->post(route('payroll.food-price.store'), ['monthly_amount' => 9000, 'effective_from' => '2026-03-01'])->assertSessionHasErrors('effective_from');
        $this->assertSame(1, FoodChargeRate::count());

        $this->post(route('payroll.food-price.store'), ['monthly_amount' => 3300, 'effective_from' => '2026-09-01'])->assertSessionHasNoErrors();
        $this->assertSame(2, FoodChargeRate::count());
    }

    public function test_a_price_inside_a_closed_month_cannot_be_removed_but_an_open_one_can(): void
    {
        $old = $this->price(3000, '2026-01-01');
        $new = $this->price(3300, '2026-09-01');
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', slip: false);
        $this->slip($this->hotel, $employee, 2026, 8);

        $this->admin();
        $this->open($this->food);

        $this->delete(route('payroll.food-price.destroy', $old))->assertSessionHas('error');
        $this->assertDatabaseHas('food_charge_rates', ['id' => $old->id]);

        $this->delete(route('payroll.food-price.destroy', $new))->assertSessionHas('success');
        $this->assertDatabaseMissing('food_charge_rates', ['id' => $new->id]);
    }

    public function test_a_price_cannot_start_after_today(): void
    {
        $this->admin();
        $this->open($this->food);

        $this->post(route('payroll.food-price.store'), ['monthly_amount' => 3300, 'effective_from' => '2026-09-21'])->assertSessionHasErrors('effective_from');
        $this->post(route('payroll.food-price.store'), ['monthly_amount' => 3300, 'effective_from' => '2026-10-01'])->assertSessionHasErrors('effective_from');
        $this->assertSame(0, FoodChargeRate::count());

        $this->post(route('payroll.food-price.store'), ['monthly_amount' => 3300, 'effective_from' => '2026-09-20'])->assertSessionHasNoErrors();
        $this->post(route('payroll.food-price.store'), ['monthly_amount' => 3100, 'effective_from' => '2026-09-05'])->assertSessionHasNoErrors();
        $this->assertSame(2, FoodChargeRate::count());
    }

    public function test_the_price_page_says_which_months_are_closed_and_bounds_the_date(): void
    {
        $this->price(3000, '2026-01-01');
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', slip: false);
        $this->slip($this->hotel, $employee, 2026, 8);

        $this->admin();
        $this->open($this->food);

        $this->get(route('payroll.food-price.index'))->assertOk()
            ->assertSee('Up to Aug 2026')->assertSee('Closed - salary generated')->assertSee('September 2026')
            ->assertSee('max="2026-09-20"', false)->assertSee('min="2026-09-01"', false)->assertSee('cannot be later than today');
    }

    /* ------------------------------------------------ who takes meals, and when */

    public function test_only_people_with_a_meals_record_are_charged(): void
    {
        $this->price(3000);
        $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');
        $this->staff($this->hotel, 'E-2', 'Own Lunch', '2025-06-01', eats: false);

        $this->assertSame(['Asha Menon'], array_column($this->billFor($this->hotel)['rows'], 'name'));
    }

    public function test_a_record_that_starts_part_way_through_the_month_charges_from_that_day(): void
    {
        $this->price(3000);
        $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', eats: false);
        $this->meals(Employee::first(), '2026-09-16');

        $row = $this->billFor($this->hotel)['rows'][0];

        $this->assertSame(15, $row['days']);
        $this->assertSame(1500.0, $row['amount']);
        $this->assertStringContainsString('meals from 16 Sep', $row['note']);
    }

    public function test_a_record_that_ends_charges_up_to_and_including_the_last_day(): void
    {
        $this->price(3000);
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', eats: false);
        $this->meals($employee, '2025-06-01', '2026-09-10');

        $row = $this->billFor($this->hotel)['rows'][0];

        $this->assertSame(10, $row['days']);
        $this->assertSame(1000.0, $row['amount']);
        $this->assertStringContainsString('meals until 10 Sep', $row['note']);
    }

    public function test_several_records_in_one_month_add_up_and_a_record_in_another_month_is_ignored(): void
    {
        $this->price(3000);
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', eats: false);

        $this->meals($employee, '2026-09-01', '2026-09-05');   // 5 days
        $this->meals($employee, '2026-09-20', null);           // 11 days (20th to 30th)
        $this->meals($employee, '2026-07-01', '2026-07-31');   // a different month

        $this->assertSame(16, $this->billFor($this->hotel)['rows'][0]['days']);
        $this->assertNull($this->billFor($this->hotel, 7));    // no salary generated for July here
    }

    public function test_someone_with_a_record_only_in_an_earlier_month_is_not_charged_now(): void
    {
        $this->price(3000);
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', eats: false);
        $this->meals($employee, '2026-06-01', '2026-08-31');

        $this->assertNull($this->billFor($this->hotel));
    }

    public function test_someone_who_has_left_is_charged_only_up_to_their_last_day_even_if_meals_carry_on(): void
    {
        $this->price(3000);
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');

        EmployeeSeparation::create([
            'payroll_company_id' => $this->hotel->id, 'employee_id' => $employee->id,
            'separation_type' => 'Resignation', 'resignation_date' => '2026-09-01', 'reason' => 'Moving city',
            'last_working_date' => '2026-09-10', 'status' => 'Relieved',
        ]);

        $this->assertSame(10, $this->billFor($this->hotel)['rows'][0]['days']);
    }

    public function test_there_is_no_statement_until_pallav_food_has_set_a_price(): void
    {
        $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');
        $this->assertNull($this->billFor($this->hotel));

        $this->price(3000);
        $this->assertNotNull($this->billFor($this->hotel));
    }

    /* ---------------------------------------------------------- starting a record */

    public function test_a_record_is_started_from_a_day_and_optionally_ended(): void
    {
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', eats: false, slip: false);

        $this->admin();
        $this->open($this->hotel);

        $this->post(route('payroll.food-charge.start', $employee), ['starts_on' => '2026-09-10'])
            ->assertSessionHasNoErrors()->assertSessionHas('success');

        $record = EmployeeMealPeriod::firstOrFail();
        $this->assertSame('2026-09-10', $record->starts_on->toDateString());
        $this->assertNull($record->ends_on);
        $this->assertSame($this->hotel->id, $record->payroll_company_id);

        // With an end date as well
        $other = $this->staff($this->hotel, 'E-2', 'Bhavin Shah', '2025-06-01', eats: false, slip: false);
        $this->post(route('payroll.food-charge.start', $other), ['starts_on' => '2026-09-02', 'ends_on' => '2026-09-08'])->assertSessionHasNoErrors();
        $this->assertSame('2026-09-08', EmployeeMealPeriod::where('employee_id', $other->id)->first()->ends_on->toDateString());
    }

    public function test_a_record_cannot_start_or_end_after_today_or_before_the_person_joined(): void
    {
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2026-09-05', eats: false, slip: false);

        $this->admin();
        $this->open($this->hotel);

        $this->post(route('payroll.food-charge.start', $employee), ['starts_on' => '2026-09-21'])->assertSessionHasErrors('starts_on');
        $this->post(route('payroll.food-charge.start', $employee), ['starts_on' => '2026-09-10', 'ends_on' => '2026-09-21'])->assertSessionHasErrors('ends_on');
        $this->post(route('payroll.food-charge.start', $employee), ['starts_on' => '2026-09-10', 'ends_on' => '2026-09-08'])->assertSessionHasErrors('ends_on');
        $this->post(route('payroll.food-charge.start', $employee), ['starts_on' => '2026-09-01'])->assertSessionHasErrors('starts_on'); // joined on the 5th

        $this->assertSame(0, EmployeeMealPeriod::count());
    }

    public function test_two_records_for_the_same_person_cannot_overlap(): void
    {
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', eats: false, slip: false);
        $this->meals($employee, '2026-09-01', '2026-09-10');

        $this->admin();
        $this->open($this->hotel);

        $this->post(route('payroll.food-charge.start', $employee), ['starts_on' => '2026-09-08'])->assertSessionHasErrors('starts_on');
        $this->post(route('payroll.food-charge.start', $employee), ['starts_on' => '2026-08-25', 'ends_on' => '2026-09-01'])->assertSessionHasErrors('starts_on');

        // The day after the first one ends is fine
        $this->post(route('payroll.food-charge.start', $employee), ['starts_on' => '2026-09-11'])->assertSessionHasNoErrors();
        $this->assertSame(2, EmployeeMealPeriod::count());
    }

    public function test_a_record_cannot_start_in_a_month_whose_salary_is_generated(): void
    {
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', eats: false, slip: false);
        $this->slip($this->hotel, $employee, 2026, 8);

        $this->admin();
        $this->open($this->hotel);

        $this->post(route('payroll.food-charge.start', $employee), ['starts_on' => '2026-08-20'])->assertSessionHasErrors('starts_on');
        $this->assertSame(0, EmployeeMealPeriod::count());

        $this->post(route('payroll.food-charge.start', $employee), ['starts_on' => '2026-09-01'])->assertSessionHasNoErrors();
        $this->assertSame(1, EmployeeMealPeriod::count());
    }

    /* ---------------------------------------------------------- stopping a record */

    public function test_stopping_closes_the_record_and_leaves_it_on_file(): void
    {
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', eats: false, slip: false);
        $record = $this->meals($employee, '2026-09-01');
        $this->price(3000);

        $this->admin();
        $this->open($this->hotel);

        $this->put(route('payroll.food-charge.stop', $record), ['ends_on' => '2026-09-12'])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertSame('2026-09-12', $record->fresh()->ends_on->toDateString());
        $this->assertSame(1, EmployeeMealPeriod::count());

        // Twelve days, charged once salary is generated
        $this->slip($this->hotel, $employee, 2026, 9);
        $this->assertSame(1200.0, $this->billFor($this->hotel)['total']);
    }

    public function test_stopping_someone_leaves_every_closed_month_exactly_as_it_was(): void
    {
        $this->price(3000);
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', eats: false, slip: false);
        $record = $this->meals($employee, '2025-06-01');
        $this->slip($this->hotel, $employee, 2026, 8);

        $augustBefore = $this->billFor($this->hotel, 8);
        $this->assertSame(3000.0, $augustBefore['total']);

        $this->admin();
        $this->open($this->hotel);

        // Meals stop at the end of August: the change is entirely in September and after
        $this->put(route('payroll.food-charge.stop', $record), ['ends_on' => '2026-08-31'])->assertSessionHasNoErrors();

        $this->assertSame($augustBefore['total'], $this->billFor($this->hotel, 8)['total']);
        $this->assertSame($augustBefore['rows'], $this->billFor($this->hotel, 8)['rows']);

        // ...and September has nothing
        $this->slip($this->hotel, $employee, 2026, 9);
        $this->assertNull($this->billFor($this->hotel, 9));
    }

    public function test_a_record_cannot_be_ended_so_that_it_changes_a_closed_month(): void
    {
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', eats: false, slip: false);
        $record = $this->meals($employee, '2026-06-01');
        $this->slip($this->hotel, $employee, 2026, 8);

        $this->admin();
        $this->open($this->hotel);

        // Ending it on the 15th of August would remove August's second half from a bill already generated
        $this->put(route('payroll.food-charge.stop', $record), ['ends_on' => '2026-08-15'])->assertSessionHasErrors('ends_on');
        $this->assertNull($record->fresh()->ends_on);
    }

    public function test_a_record_cannot_be_ended_after_today_or_before_it_began(): void
    {
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', eats: false, slip: false);
        $record = $this->meals($employee, '2026-09-05');

        $this->admin();
        $this->open($this->hotel);

        $this->put(route('payroll.food-charge.stop', $record), ['ends_on' => '2026-09-21'])->assertSessionHasErrors('ends_on');
        $this->put(route('payroll.food-charge.stop', $record), ['ends_on' => '2026-09-01'])->assertSessionHasErrors('ends_on');
        $this->assertNull($record->fresh()->ends_on);
    }

    public function test_a_record_in_an_open_month_can_be_removed_but_one_in_a_closed_month_cannot(): void
    {
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', eats: false, slip: false);
        $closed = $this->meals($employee, '2026-06-01', '2026-06-30');
        $open = $this->meals($employee, '2026-09-05');
        $this->slip($this->hotel, $employee, 2026, 8);

        $this->admin();
        $this->open($this->hotel);

        $this->delete(route('payroll.food-charge.destroy', $closed))->assertSessionHas('error');
        $this->assertDatabaseHas('employee_meal_periods', ['id' => $closed->id]);

        $this->delete(route('payroll.food-charge.destroy', $open))->assertSessionHas('success');
        $this->assertDatabaseMissing('employee_meal_periods', ['id' => $open->id]);
    }

    /* ------------------------------------------------------------- isolation and log */

    public function test_one_company_cannot_start_stop_or_remove_anothers_meals(): void
    {
        $foreign = $this->staff($this->food, 'F-1', 'Cook', '2025-06-01', eats: false, slip: false);
        $record = $this->meals($foreign, '2026-09-01');

        $this->admin();
        $this->open($this->hotel);

        $this->post(route('payroll.food-charge.start', $foreign), ['starts_on' => '2026-09-10'])->assertNotFound();
        $this->put(route('payroll.food-charge.stop', $record), ['ends_on' => '2026-09-10'])->assertNotFound();
        $this->delete(route('payroll.food-charge.destroy', $record))->assertNotFound();

        $this->assertNull($record->fresh()->ends_on);
    }

    public function test_starting_and_stopping_are_written_to_the_log(): void
    {
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', eats: false, slip: false);

        $this->actingAs(User::factory()->superAdmin()->create());
        $this->open($this->hotel);

        $this->post(route('payroll.food-charge.start', $employee), ['starts_on' => '2026-09-10']);
        $record = EmployeeMealPeriod::firstOrFail();
        $this->put(route('payroll.food-charge.stop', $record), ['ends_on' => '2026-09-14']);

        $entries = PayrollLog::where('entity', 'Meals record')->orderBy('id')->get();

        $this->assertSame(['created', 'updated'], $entries->pluck('action')->all());
        $this->assertStringContainsString('Asha Menon', $entries[0]->subject_label);
        $this->assertArrayHasKey('Meals end', $entries[1]->details);
        $this->assertSame($this->hotel->id, $entries[0]->payroll_company_id);
    }

    /* ------------------------------------------------------------------- the page */

    public function test_the_page_shows_each_persons_status_and_the_actions_for_it(): void
    {
        $this->price(3000);
        $on = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', slip: false);
        $stopped = $this->staff($this->hotel, 'E-2', 'Bhavin Shah', '2025-06-01', eats: false, slip: false);
        $this->meals($stopped, '2026-06-01', '2026-08-15');
        $this->staff($this->hotel, 'E-3', 'Chirag Dave', '2025-06-01', eats: false, slip: false);

        $this->admin();
        $this->open($this->hotel);

        $this->get(route('payroll.food-charge.index', ['year' => 2026, 'month' => 9]))->assertOk()
            ->assertSee('On meals')->assertSee('since 01 Jun 2025')          // Asha, ongoing
            ->assertSee('Stopped')->assertSee('01 Jun 2026 - 15 Aug 2026')    // Bhavin
            ->assertSee('Not on meals')                                       // Chirag
            ->assertSee('Stop meals for Asha Menon')                          // the dialog for the ongoing record
            ->assertSee('Start meals for Chirag Dave')
            ->assertSee('Meal records - Bhavin Shah');
    }

    public function test_the_staff_form_no_longer_carries_a_meals_switch(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        foreach ([$this->hotel, $this->food] as $company) {
            $this->open($company);
            $this->get(route('payroll.staff.index'))->assertOk()->assertDontSee('Takes meals at Pallav Food');
            $this->get(route('payroll.food-charge.index'))->assertOk()->assertSee('Who takes meals at Pallav Food');
        }
    }

    /* ----------------------------------------------------- only after salary is generated */

    public function test_nothing_is_worked_out_until_salary_has_been_generated_for_the_month(): void
    {
        $this->price(3000);
        $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', slip: false);
        $this->staff($this->food, 'F-1', 'Cook', '2025-06-01', slip: false);

        $this->assertNull($this->billFor($this->hotel));
        $this->assertNull($this->billFor($this->food));

        $this->admin();
        $this->open($this->hotel);

        $this->get(route('payroll.food-charge.index', ['year' => 2026, 'month' => 9]))
            ->assertOk()->assertSee('Not worked out yet for September 2026')->assertSee('calculated once salary has been generated');

        $this->get(route('payroll.monthly-report.index', ['year' => 2026, 'month' => 9]))->assertOk()->assertDontSee('Pay to Pallav Food');
    }

    public function test_the_bill_covers_only_the_people_in_the_salary_run(): void
    {
        $this->price(3000);
        $asha = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01', slip: false);
        $this->staff($this->hotel, 'E-2', 'Bhavin Shah', '2025-06-01', slip: false);

        $this->slip($this->hotel, $asha, 2026, 9);

        $this->assertSame(['Asha Menon'], array_column($this->billFor($this->hotel)['rows'], 'name'));
    }

    public function test_generating_salary_in_one_company_does_not_produce_a_bill_in_the_other(): void
    {
        $this->price(3000);
        $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');
        $this->staff($this->food, 'F-1', 'Cook', '2025-06-01', slip: false);

        $this->assertNotNull($this->billFor($this->hotel));
        $this->assertNull($this->billFor($this->food));
    }

    /* ------------------------------------------------------ status: meals not counted */

    public function test_days_with_a_status_that_leaves_meals_out_are_not_charged(): void
    {
        $this->price(3000);
        $employee = $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');

        $month = AttendanceMonth::create(['payroll_company_id' => $this->hotel->id, 'year' => 2026, 'month' => 9]);
        $leave = AttendanceStatus::create([
            'payroll_company_id' => $this->hotel->id, 'name' => 'Leave', 'shortcut_key' => 'L', 'color' => '#ff0000',
            'attendance_percentage' => 0, 'status_type' => 'Unpaid', 'skips_food' => true,
        ]);

        foreach ([3, 4, 5] as $day) {
            AttendanceEntry::create(['attendance_month_id' => $month->id, 'employee_id' => $employee->id, 'day' => $day,
                'attendance_status_id' => $leave->id, 'shortcut_key' => 'L', 'attendance_percentage' => 0, 'skips_food' => true]);
        }
        AttendanceEntry::create(['attendance_month_id' => $month->id, 'employee_id' => $employee->id, 'day' => 9,
            'shortcut_key' => 'A', 'attendance_percentage' => 0, 'skips_food' => false]);

        $row = $this->billFor($this->hotel)['rows'][0];

        $this->assertSame(27, $row['days']);
        $this->assertSame(3, $row['left_out']);
        $this->assertSame(2700.0, $row['amount']);
        $this->assertStringContainsString('3 days not counted', $row['note']);
    }

    public function test_the_status_option_is_offered_to_both_companies_saved_and_copied_onto_the_day(): void
    {
        $this->admin();

        foreach ([$this->hotel, $this->food] as $company) {
            $this->open($company);

            $this->get(route('payroll.attendance-status.index'))->assertOk()->assertSee('Meals are not counted on this status');

            $this->post(route('payroll.attendance-status.store'), [
                'name' => 'Leave', 'shortcut_key' => 'L', 'color' => '#aa0000',
                'attendance_percentage' => 0, 'status_type' => 'Unpaid', 'skips_food' => '1',
            ])->assertSessionHasNoErrors();

            $status = AttendanceStatus::where('payroll_company_id', $company->id)->firstOrFail();
            $this->assertTrue($status->skips_food);

            $employee = $this->staff($company, 'X-'.$company->id, 'Someone', '2025-01-01', eats: false, slip: false);
            $month = AttendanceMonth::create(['payroll_company_id' => $company->id, 'year' => 2026, 'month' => 9]);

            $this->post(route('payroll.attendance.save', $month), ['attendance' => [$employee->id => [5 => 'L']]]);
            $this->assertTrue((bool) AttendanceEntry::where('employee_id', $employee->id)->where('day', 5)->value('skips_food'));

            $this->put(route('payroll.attendance-status.update', $status), [
                'name' => 'Leave', 'shortcut_key' => 'L', 'color' => '#aa0000',
                'attendance_percentage' => 0, 'status_type' => 'Unpaid', 'skips_food' => '0',
            ])->assertSessionHasNoErrors();
            $this->assertFalse((bool) AttendanceEntry::where('employee_id', $employee->id)->where('day', 5)->value('skips_food'));
        }
    }

    public function test_a_month_whose_salary_is_generated_keeps_its_meal_days_when_the_status_changes(): void
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

        $this->admin();
        $this->open($this->hotel);

        $this->put(route('payroll.attendance-status.update', $leave), [
            'name' => 'Leave', 'shortcut_key' => 'L', 'color' => '#ff0000',
            'attendance_percentage' => 0, 'status_type' => 'Unpaid', 'skips_food' => '0',
        ]);

        $this->assertTrue((bool) AttendanceEntry::first()->skips_food);
        $this->assertSame(29, $this->billFor($this->hotel)['rows'][0]['days']);
    }

    /* -------------------------------------------------------------------- reports */

    public function test_the_monthly_report_carries_salary_and_meals_together_with_one_total(): void
    {
        $this->price(3000);
        $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');
        $this->staff($this->hotel, 'E-2', 'Bhavin Shah', '2026-09-16');   // meals from the day they joined: 15 of 30 days

        $this->admin();
        $this->open($this->hotel);

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

        $this->admin();
        $this->open($this->food);

        $this->get(route('payroll.monthly-report.index', ['year' => 2026, 'month' => 9]))
            ->assertOk()->assertSee('Staff meals')->assertSee('A cost of Pallav Food')
            ->assertDontSee('Pay to Pallav Food')->assertDontSee('Payable to');

        $this->assertStringStartsWith('%PDF', $this->get(route('payroll.report.monthly', ['year' => 2026, 'month' => 9]))->assertOk()->getContent());
    }

    public function test_a_price_change_within_a_month_is_explained_on_the_bill(): void
    {
        $this->price(3000, '2026-01-01');
        $this->price(3300, '2026-09-15');
        $this->staff($this->hotel, 'E-1', 'Asha Menon', '2025-06-01');

        $this->admin();
        $this->open($this->hotel);

        $this->get(route('payroll.food-charge.index', ['year' => 2026, 'month' => 9]))
            ->assertOk()->assertSee('The price changed during September')->assertSee('from 15 Sep');
    }

    public function test_the_meal_price_and_staff_meals_are_named_in_the_menu_and_the_old_names_are_gone(): void
    {
        $this->admin();
        $this->open($this->food);

        $this->get(route('payroll.staff.index'))
            ->assertSee('Meal Price')->assertSee('Staff Meals')
            ->assertDontSee('Food Price')->assertDontSee('Food Charges');
    }
}
