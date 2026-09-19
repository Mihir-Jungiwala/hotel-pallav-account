<?php

namespace Tests\Feature\Payroll;

use App\Models\Employee;
use App\Models\PayrollCompany;
use App\Models\SalaryProcessing;
use App\Models\SalaryProcessingLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The salary slip is the one document an employee actually reads, so it has
 * to render for the awkward shapes too: no deductions, many deductions, an
 * advance still being recovered.
 */
class SalarySlipPdfTest extends TestCase
{
    use RefreshDatabase;

    private PayrollCompany $company;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = PayrollCompany::create([
            'name' => 'Pallav Hotel', 'code' => 'PH01',
            'address' => '1 Station Road', 'city' => 'Rajkot', 'state' => 'Gujarat',
            'mobile_number' => '9000000000',
            'authorized_person_name' => 'R Patel', 'authorized_designation' => 'Manager',
        ]);

        $this->employee = Employee::create([
            'payroll_company_id' => $this->company->id,
            'employee_code' => 'E-1', 'name' => 'Asha Menon',
            'designation' => 'Front Office', 'joining_date' => now()->subYear(),
            'salary' => 24000, 'daily_working_hours' => 8,
            'payment_mode' => 'Cash', 'is_active' => true,
        ]);

        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('payroll.index', ['current_company' => $this->company->id]));
    }

    private function processing(array $overrides = []): SalaryProcessing
    {
        return SalaryProcessing::create(array_merge([
            'payroll_company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'company_name' => $this->company->name,
            'employee_name' => $this->employee->name,
            'employee_code' => $this->employee->employee_code,
            'designation' => $this->employee->designation,
            'year' => 2026, 'month' => 8,
            'total_days_in_month' => 31, 'days_100' => 30, 'days_0' => 1,
            'total_payable_days' => 30, 'daily_salary' => 800, 'hourly_rate' => 100,
            'monthly_salary' => 24000, 'daily_working_hours' => 8,
            'attendance_salary' => 24000, 'net_salary' => 24000,
            'payment_mode' => 'Cash', 'payment_status' => 'Pending',
            'processed_at' => now(),
        ], $overrides));
    }

    public function test_a_slip_with_no_deductions_renders(): void
    {
        $processing = $this->processing();

        $response = $this->get(route('payroll.salary-slip.view', $processing));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_a_slip_with_deductions_and_an_advance_renders(): void
    {
        $processing = $this->processing([
            'deduction_amount' => 1500,
            'advance_deduction' => 1000,
            'bonus_amount' => 2000,
            'overtime_hours' => 6,
            'overtime_amount' => 600,
            'net_salary' => 24100,
            'pending_advance_amount' => 4000,
        ]);

        SalaryProcessingLine::create([
            'salary_processing_id' => $processing->id,
            'category' => 'Deduction', 'label' => 'Food',
            'deduction_type' => 'Monthly', 'amount' => 1500,
        ]);

        SalaryProcessingLine::create([
            'salary_processing_id' => $processing->id,
            'category' => 'Advance', 'label' => 'Advance',
            'deduction_type' => 'Monthly', 'amount' => 1000, 'pending_amount' => 4000,
        ]);

        $response = $this->get(route('payroll.salary-slip.view', $processing));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }
}
