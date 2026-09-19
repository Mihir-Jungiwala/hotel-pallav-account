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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two companies exist side by side; whichever one is "current" in the
 * session is the only one a request may touch. Route-model binding resolves
 * a record by its ID alone, so without a company check a crafted URL could
 * reach another company's data - this proves it cannot, for every payroll
 * resource, not just one.
 */
class CompanyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private PayrollCompany $companyA;

    private PayrollCompany $companyB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = PayrollCompany::create(['name' => 'Company A', 'code' => 'CA01']);
        $this->companyB = PayrollCompany::create(['name' => 'Company B', 'code' => 'CB01']);

        $this->actingAs(User::factory()->admin()->create());

        // "Company A" is the active session context for every request below
        $this->get(route('payroll.index', ['current_company' => $this->companyA->id]));
    }

    private function employeeIn(PayrollCompany $company): Employee
    {
        return Employee::create([
            'payroll_company_id' => $company->id,
            'employee_code' => 'E-'.$company->id,
            'name' => 'Test Employee',
            'is_active' => true,
        ]);
    }

    public function test_the_top_level_payroll_link_always_opens_company_listing(): void
    {
        $this->get(route('payroll.index'))->assertSee('Company Setup');
    }

    public function test_opening_a_company_makes_it_the_active_context(): void
    {
        $this->get(route('payroll.index', ['current_company' => $this->companyB->id]))
            ->assertSee('Company B');
    }

    public function test_an_employee_in_the_other_company_cannot_be_edited(): void
    {
        $foreign = $this->employeeIn($this->companyB);

        $this->put(route('payroll.employee.update', $foreign), ['name' => 'Hacked'])
            ->assertNotFound();

        $this->assertSame('Test Employee', $foreign->fresh()->name);
    }

    public function test_an_employee_in_the_other_company_cannot_be_deleted(): void
    {
        $foreign = $this->employeeIn($this->companyB);

        $this->delete(route('payroll.employee.destroy', $foreign))->assertNotFound();

        $this->assertModelExists($foreign);
    }

    public function test_an_employee_in_the_other_company_cannot_be_viewed_as_a_pdf(): void
    {
        $foreign = $this->employeeIn($this->companyB);

        $this->get(route('payroll.employee.view', $foreign))->assertNotFound();
    }

    public function test_an_employee_in_the_current_company_can_be_edited(): void
    {
        $own = $this->employeeIn($this->companyA);

        $this->put(route('payroll.employee.update', $own), [
            'employee_code' => $own->employee_code, 'name' => 'Renamed', 'designation' => 'Manager',
            'joining_date' => now()->toDateString(), 'salary' => 20000,
            'daily_working_hours' => 8, 'payment_mode' => 'Cash',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $own->fresh()->name);
    }

    public function test_an_advance_in_the_other_company_cannot_be_reached(): void
    {
        $foreignEmployee = $this->employeeIn($this->companyB);
        $advance = PayrollAdvance::create([
            'payroll_company_id' => $this->companyB->id,
            'employee_id' => $foreignEmployee->id,
            'advance_date' => now(),
            'amount' => 1000,
            'deduction_type' => 'One Time',
        ]);

        $this->put(route('payroll.advance.update', $advance), ['amount' => 5000])->assertNotFound();
        $this->delete(route('payroll.advance.destroy', $advance))->assertNotFound();
        $this->get(route('payroll.advance.view', $advance))->assertNotFound();

        $this->assertSame('1000.00', (string) $advance->fresh()->amount);
    }

    public function test_a_bonus_entry_in_the_other_company_cannot_be_reached(): void
    {
        $foreignEmployee = $this->employeeIn($this->companyB);
        $entry = BonusIncentive::create([
            'payroll_company_id' => $this->companyB->id,
            'employee_id' => $foreignEmployee->id,
            'entry_date' => now(),
            'type' => 'Bonus',
            'amount' => 500,
        ]);

        $this->put(route('payroll.bonus-incentive.update', $entry), ['amount' => 999])->assertNotFound();
        $this->delete(route('payroll.bonus-incentive.destroy', $entry))->assertNotFound();
    }

    public function test_a_deduction_master_row_in_the_other_company_cannot_be_reached(): void
    {
        $deduction = Deduction::create(['payroll_company_id' => $this->companyB->id, 'name' => 'PF']);

        $this->put(route('payroll.deduction.update', $deduction), ['name' => 'Renamed'])->assertNotFound();
        $this->delete(route('payroll.deduction.destroy', $deduction))->assertNotFound();
    }

    public function test_an_attendance_status_in_the_other_company_cannot_be_reached(): void
    {
        $status = AttendanceStatus::create([
            'payroll_company_id' => $this->companyB->id,
            'name' => 'Absent', 'shortcut_key' => 'A', 'attendance_percentage' => 0, 'color' => '#dc2626',
        ]);

        $this->put(route('payroll.attendance-status.update', $status), ['name' => 'Renamed'])->assertNotFound();
        $this->delete(route('payroll.attendance-status.destroy', $status))->assertNotFound();
    }

    public function test_a_separation_record_in_the_other_company_cannot_be_reached(): void
    {
        $foreignEmployee = $this->employeeIn($this->companyB);
        $separation = EmployeeSeparation::create([
            'payroll_company_id' => $this->companyB->id,
            'employee_id' => $foreignEmployee->id,
            'type' => 'Resignation',
            'reason' => 'Personal reasons',
            'resignation_date' => now(),
            'last_working_date' => now()->addDays(30),
            'status' => 'Pending',
        ]);

        $this->put(route('payroll.separation.update', $separation), ['status' => 'Relieved'])->assertNotFound();
        $this->delete(route('payroll.separation.destroy', $separation))->assertNotFound();
        $this->get(route('payroll.separation.view', $separation))->assertNotFound();
    }

    public function test_salary_history_for_an_employee_in_the_other_company_cannot_be_reached(): void
    {
        $foreign = $this->employeeIn($this->companyB);

        $this->get(route('payroll.salary-update.history', $foreign))->assertNotFound();
        $this->put(route('payroll.salary-update.update', $foreign), ['salary' => 30000])->assertNotFound();
    }

    public function test_a_joining_letter_cannot_be_generated_for_an_employee_in_the_other_company(): void
    {
        $foreign = $this->employeeIn($this->companyB);

        $this->get(route('payroll.joining-letter.generate', $foreign))->assertNotFound();
    }
}
