<?php

namespace Tests\Feature\Payroll;

use App\Models\Employee;
use App\Models\JoiningLetter;
use App\Models\PayrollCompany;
use App\Models\SalaryProcessing;
use App\Models\SalaryProcessingLine;
use App\Models\User;
use App\Support\PayrollPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A document that runs a few lines onto a second page looks careless. These
 * pin the behaviour that a near miss is tightened onto one page, through the
 * same routes a person clicks.
 */
class PdfFitTest extends TestCase
{
    use RefreshDatabase;

    private PayrollCompany $company;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = PayrollCompany::create([
            'name' => 'Pallav Hotel', 'code' => 'PH01', 'address' => '1 Station Road',
            'city' => 'Rajkot', 'state' => 'Gujarat', 'mobile_number' => '9000000000',
            'authorized_person_name' => 'R Patel', 'authorized_designation' => 'Manager',
        ]);

        $this->employee = Employee::create([
            'payroll_company_id' => $this->company->id, 'employee_code' => 'E-1', 'name' => 'Asha Menon',
            'designation' => 'Front Office', 'joining_date' => now()->subYear(), 'salary' => 24000,
            'daily_working_hours' => 8, 'payment_mode' => 'Cash', 'is_active' => true,
        ]);

        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('payroll.index', ['current_company' => $this->company->id]));
    }

    private function slipWithDeductions(int $count): SalaryProcessing
    {
        $processing = SalaryProcessing::create([
            'payroll_company_id' => $this->company->id, 'employee_id' => $this->employee->id,
            'company_name' => $this->company->name, 'employee_name' => $this->employee->name,
            'employee_code' => $this->employee->employee_code, 'designation' => 'Front Office',
            'year' => 2026, 'month' => 8, 'total_days_in_month' => 31, 'days_100' => 30, 'days_0' => 1,
            'total_payable_days' => 30, 'daily_salary' => 800, 'hourly_rate' => 100, 'monthly_salary' => 24000,
            'daily_working_hours' => 8, 'attendance_salary' => 24000, 'net_salary' => 22000,
            'payment_mode' => 'Cash', 'payment_status' => 'Pending', 'processed_at' => now(),
        ]);

        foreach (range(1, $count) as $i) {
            SalaryProcessingLine::create([
                'salary_processing_id' => $processing->id, 'category' => 'Deduction',
                'label' => 'Deduction '.$i, 'deduction_type' => 'Monthly', 'amount' => 100 * $i,
            ]);
        }

        SalaryProcessingLine::create([
            'salary_processing_id' => $processing->id, 'category' => 'Advance', 'label' => 'Advance',
            'deduction_type' => 'Monthly', 'amount' => 500, 'pending_amount' => 900,
        ]);

        return $processing;
    }

    public function test_a_slip_with_several_deductions_stays_on_one_page(): void
    {
        foreach ([3, 6, 9] as $count) {
            $processing = $this->slipWithDeductions($count);

            $body = $this->get(route('payroll.salary-slip.view', $processing))->assertOk()->getContent();

            $this->assertSame(1, PayrollPdf::pageCount($body), "{$count} deductions should fit one page");

            $processing->lines()->delete();
            $processing->delete();
        }
    }

    public function test_a_letter_with_long_terms_is_tightened_onto_one_page(): void
    {
        JoiningLetter::create([
            'payroll_company_id' => $this->company->id,
            'introduction_content' => 'We are pleased to appoint {EMPLOYEE_NAME} as {DESIGNATION}.',
            'roles_responsibilities' => '{RESPONSIBILITIES}',
            'terms_conditions' => str_repeat("The employee shall follow the policies of the company as amended from time to time.\n", 9),
            'closing_message' => 'Welcome aboard.',
            'acceptance_heading' => 'Acceptance of Offer', 'acceptance_content' => 'I accept the terms above.',
            'use_company_signatory' => true,
        ]);

        $body = $this->get(route('payroll.joining-letter.generate', $this->employee))->assertOk()->getContent();

        $this->assertSame(1, PayrollPdf::pageCount($body));
    }

    public function test_a_genuinely_long_document_still_runs_to_more_pages_rather_than_being_crushed(): void
    {
        // Far more than any tightening can absorb: it must paginate, not
        // shrink to an unreadable size
        $processing = $this->slipWithDeductions(60);

        $body = $this->get(route('payroll.salary-slip.view', $processing))->assertOk()->getContent();

        $this->assertGreaterThan(1, PayrollPdf::pageCount($body));
    }

    public function test_the_page_counter_does_not_count_the_page_tree(): void
    {
        $this->assertSame(2, PayrollPdf::pageCount('/Type /Pages /Type /Page /Type/Page'));
    }

    public function test_every_compressed_stream_in_a_fitted_pdf_can_be_read_back(): void
    {
        // A document rendered twice has its fonts compressed twice and the
        // text opens as garbage. Every Flate stream must inflate cleanly.
        $processing = $this->slipWithDeductions(6);
        $body = $this->get(route('payroll.salary-slip.view', $processing))->assertOk()->getContent();

        preg_match_all('/<<([^>]*)>>\s*stream\r?\n(.*?)\r?\nendstream/s', $body, $m);
        $checked = 0;
        foreach ($m[1] as $i => $dictionary) {
            if (str_contains($dictionary, 'FlateDecode')) {
                $checked++;
                $this->assertNotFalse(@gzuncompress($m[2][$i]), 'A PDF stream failed to decompress');
            }
        }
        $this->assertGreaterThan(0, $checked);
    }
}
