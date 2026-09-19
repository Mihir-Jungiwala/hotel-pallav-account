<?php

namespace Tests\Feature\Payroll;

use App\Models\BonusIncentive;
use App\Models\Employee;
use App\Models\EmployeeSeparation;
use App\Models\ExperienceLetter;
use App\Models\JoiningLetter;
use App\Models\PayrollAdvance;
use App\Models\PayrollCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every PDF the payroll module can produce, rendered through the shared
 * letterhead. These go to employees and to the bank, so a template that
 * throws has to fail here rather than in front of whoever clicked Download.
 */
class PayrollDocumentsTest extends TestCase
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
            'pincode' => '360001', 'mobile_number' => '9000000000',
            'authorized_person_name' => 'R Patel', 'authorized_designation' => 'Manager',
        ]);

        $this->employee = Employee::create([
            'payroll_company_id' => $this->company->id,
            'employee_code' => 'E-1', 'name' => 'Asha Menon',
            'designation' => 'Front Office', 'department' => 'Operations',
            'joining_date' => now()->subYear(), 'salary' => 24000,
            'daily_working_hours' => 8, 'payment_mode' => 'Cash', 'is_active' => true,
        ]);

        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('payroll.index', ['current_company' => $this->company->id]));
    }

    private function assertPdf(string $url): void
    {
        $response = $this->get($url);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));

        // A PDF that rendered has real content, and starts with the format's
        // own marker; anything else means the template produced nothing the
        // renderer could use.
        $body = $response->getContent();
        $this->assertStringStartsWith('%PDF', $body);
        $this->assertGreaterThan(1000, strlen($body));
    }

    public function test_the_company_profile_renders(): void
    {
        $this->assertPdf(route('payroll.company.view', $this->company));
    }

    public function test_the_employee_record_renders(): void
    {
        $this->assertPdf(route('payroll.employee.view', $this->employee));
    }

    public function test_the_joining_letter_renders(): void
    {
        JoiningLetter::create([
            'payroll_company_id' => $this->company->id,
            'subject' => 'Appointment as {DESIGNATION}',
            'introduction_content' => 'We are pleased to appoint {EMPLOYEE_NAME}.',
            'roles_responsibilities' => '{RESPONSIBILITIES}',
            'terms_conditions' => 'Standard terms apply.',
            'closing_message' => 'Welcome aboard.',
            'acceptance_heading' => 'Employee Acceptance',
            'acceptance_content' => 'I accept the terms above.',
            'use_company_signatory' => true,
        ]);

        $this->assertPdf(route('payroll.joining-letter.generate', $this->employee));
    }

    public function test_the_experience_letter_renders(): void
    {
        ExperienceLetter::create([
            'payroll_company_id' => $this->company->id,
            'subject' => 'Experience Certificate',
            'body_content' => '{EMPLOYEE_NAME} worked as {DESIGNATION} from {JOINING_DATE} to {LAST_WORKING_DATE}.',
            'conduct_remarks' => 'Conduct was professional.',
            'closing_message' => 'We wish them well.',
            'use_company_signatory' => true,
        ]);

        $separation = EmployeeSeparation::create([
            'payroll_company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'separation_type' => 'Resignation', 'reason' => 'Moving city',
            'resignation_date' => now()->subDays(40),
            'last_working_date' => now()->subDays(10),
            'status' => 'Relieved',
        ]);

        $this->assertPdf(route('payroll.separation.experience-letter', $separation));
    }

    public function test_the_exit_summary_renders(): void
    {
        $separation = EmployeeSeparation::create([
            'payroll_company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'separation_type' => 'Resignation', 'reason' => 'Moving city',
            'resignation_date' => now()->subDays(40),
            'last_working_date' => now()->subDays(10),
            'status' => 'Relieved',
        ]);

        $this->assertPdf(route('payroll.separation.view', $separation));
    }

    public function test_the_advance_receipt_renders(): void
    {
        $advance = PayrollAdvance::create([
            'payroll_company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'advance_date' => now()->subDays(3),
            'amount' => 5000, 'deduction_type' => 'Monthly', 'deduction_amount' => 1000,
        ]);

        $this->assertPdf(route('payroll.advance.view', $advance));
    }

    public function test_the_bonus_receipt_renders(): void
    {
        $entry = BonusIncentive::create([
            'payroll_company_id' => $this->company->id,
            'employee_id' => $this->employee->id,
            'entry_date' => now()->subDay(), 'type' => 'Bonus', 'amount' => 2000,
        ]);

        $this->assertPdf(route('payroll.bonus-incentive.view', $entry));
    }
}
