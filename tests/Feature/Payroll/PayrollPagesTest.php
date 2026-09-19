<?php

namespace Tests\Feature\Payroll;

use App\Models\AttendanceStatus;
use App\Models\BonusIncentive;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\EmployeeSeparation;
use App\Models\PayrollAdvance;
use App\Models\PayrollCompany;
use App\Models\User;
use App\Support\PayrollContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every payroll feature is its own page now. These render each of them, both
 * with records and with none, so a missing variable or a broken include shows
 * up here rather than in front of whoever opens the page.
 */
class PayrollPagesTest extends TestCase
{
    use RefreshDatabase;

    private PayrollCompany $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = PayrollCompany::create(['name' => 'Pallav Hotel', 'code' => 'PH01']);

        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('payroll.index', ['current_company' => $this->company->id]));
    }

    /** @return array<string, array{0: string}> */
    public static function pageProvider(): array
    {
        $pages = [];

        foreach (PayrollContext::MODULES as $slug => [$route, $label]) {
            $pages[$label] = [$route];
        }

        return $pages;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pageProvider')]
    public function test_the_page_renders_when_the_company_is_empty(string $route): void
    {
        $this->get(route($route))->assertOk()->assertSee('Pallav Hotel');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pageProvider')]
    public function test_the_page_renders_with_records(string $route): void
    {
        $this->seedRecords();

        $this->get(route($route))->assertOk()->assertSee('Pallav Hotel');
    }

    public function test_the_dashboard_flags_missing_setup_for_a_new_company(): void
    {
        $this->get(route('payroll.dashboard.index'))
            ->assertOk()
            ->assertSee('No attendance statuses yet')
            ->assertSee('No staff on the payroll yet')
            ->assertSee('Needs attention');
    }

    public function test_the_dashboard_counts_only_the_open_company(): void
    {
        $this->seedRecords();

        // The same kind of record in another company must not leak into
        // this company's figures
        $other = PayrollCompany::create(['name' => 'Other Co', 'code' => 'OC01']);
        foreach (range(1, 3) as $n) {
            Employee::create([
                'payroll_company_id' => $other->id, 'employee_code' => 'X-'.$n, 'name' => 'Outsider '.$n,
                'designation' => 'Cook', 'joining_date' => now(), 'salary' => 1000,
                'daily_working_hours' => 8, 'payment_mode' => 'Cash', 'is_active' => true,
            ]);
        }

        $this->get(route('payroll.dashboard.index'))
            ->assertOk()
            ->assertSee('Asha Menon')              // this company's activity
            ->assertDontSee('Outsider 1')          // never the other's
            ->assertDontSee('No attendance statuses yet');
    }

    public function test_the_company_listing_renders(): void
    {
        $this->get(route('payroll.index'))->assertOk()->assertSee('Company Listing');
    }

    public function test_the_company_listing_can_be_searched(): void
    {
        PayrollCompany::create(['name' => 'Pallav Food', 'code' => 'PF01']);

        // Asserted against the table rows, not the whole page: the company
        // switcher in the menu lists every company you could open, which is
        // the point of it, so it names them all whatever the search says.
        $this->get(route('payroll.index', ['q' => 'Food']))
            ->assertOk()
            ->assertSee('data-row="Pallav Food', false)
            ->assertDontSee('data-row="Pallav Hotel', false);
    }

    public function test_every_page_carries_the_menu_of_the_other_pages(): void
    {
        $response = $this->get(route('payroll.staff.index'))->assertOk();

        // Each feature stays its own entry rather than being folded together
        foreach (PayrollContext::MODULES as [$route, $label]) {
            $response->assertSee($label);
        }
    }

    private function seedRecords(): void
    {
        $employee = Employee::create([
            'payroll_company_id' => $this->company->id,
            'employee_code' => 'E-1',
            'name' => 'Asha Menon',
            'designation' => 'Front Office',
            'joining_date' => now()->subYear(),
            'salary' => 24000,
            'daily_working_hours' => 8,
            'payment_mode' => 'Cash',
            'is_active' => true,
        ]);

        AttendanceStatus::create([
            'payroll_company_id' => $this->company->id,
            'name' => 'Present', 'shortcut_key' => 'P',
            'attendance_percentage' => 100, 'color' => '#16a34a', 'status_type' => 'Paid',
        ]);

        Deduction::create([
            'payroll_company_id' => $this->company->id,
            'name' => 'Food', 'amount' => 1500,
        ]);

        PayrollAdvance::create([
            'payroll_company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'advance_date' => now()->subDays(3),
            'amount' => 5000,
            'deduction_type' => 'Monthly',
            'deduction_amount' => 1000,
        ]);

        BonusIncentive::create([
            'payroll_company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'entry_date' => now()->subDay(),
            'type' => 'Bonus',
            'amount' => 2000,
        ]);

        EmployeeSeparation::create([
            'payroll_company_id' => $this->company->id,
            'employee_id' => $employee->id,
            'separation_type' => 'Resignation',
            'reason' => 'Moving city',
            'resignation_date' => now()->subDays(10),
            'last_working_date' => now()->addDays(20),
            'status' => 'Pending',
        ]);
    }
}
