<?php

namespace Tests\Feature\Payroll;

use App\Mail\StaffDetailsMail;
use App\Models\Employee;
use App\Models\PayrollCompany;
use App\Models\PayrollLog;
use App\Models\PayrollMasterItem;
use App\Models\User;
use App\Support\PayrollLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The payroll log is a full record of what was done in payroll - who, what, in
 * which company, and every detail - visible to the SuperAdmin alone.
 */
class PayrollLogTest extends TestCase
{
    use RefreshDatabase;

    private PayrollCompany $a;

    private PayrollCompany $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = PayrollCompany::create(['name' => 'Company A', 'code' => 'CA01']);
        $this->b = PayrollCompany::create(['name' => 'Company B', 'code' => 'CB01']);
    }

    private function employeeIn(PayrollCompany $company, string $code = 'E-1'): Employee
    {
        return Employee::create([
            'payroll_company_id' => $company->id, 'employee_code' => $code, 'name' => 'Asha Menon',
            'designation' => 'Front Office', 'joining_date' => now()->subYear(), 'salary' => 24000,
            'daily_working_hours' => 8, 'payment_mode' => 'Cash', 'is_active' => true,
            'contact_number' => '9000000000', 'email' => 'asha@example.com',
        ]);
    }

    public function test_only_the_superadmin_can_open_the_log(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('payroll.log.index'))->assertForbidden();

        $this->actingAs(User::factory()->superAdmin()->create());
        $this->get(route('payroll.log.index'))->assertOk();
    }

    public function test_creating_changing_and_deleting_a_record_are_each_logged_in_full(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create(['name' => 'Root Admin']));

        $employee = $this->employeeIn($this->a);
        $employee->update(['salary' => 30000, 'designation' => 'Manager']);
        $employee->delete();

        $rows = PayrollLog::where('entity', 'Staff')->orderBy('id')->get();
        $this->assertSame(['created', 'updated', 'deleted'], $rows->pluck('action')->all());

        $this->assertSame($this->a->id, $rows[0]->payroll_company_id);
        $this->assertSame('Root Admin', $rows[0]->user_name);
        $this->assertSame('Asha Menon (E-1)', $rows[0]->subject_label);
        $this->assertSame('Front Office', $rows[0]->details['Designation']);

        // A change records what each field was and what it became
        $this->assertSame(['was' => '24000.00', 'became' => '30000'], $rows[1]->details['Salary']);
        $this->assertSame(['was' => 'Front Office', 'became' => 'Manager'], $rows[1]->details['Designation']);

        // Bookkeeping is never noise
        $this->assertArrayNotHasKey('Updated At', $rows[1]->details);
    }

    public function test_a_save_that_changes_nothing_is_not_logged(): void
    {
        $employee = $this->employeeIn($this->a);
        $before = PayrollLog::count();

        $employee->update(['name' => $employee->name]);

        $this->assertSame($before, PayrollLog::count());
    }

    public function test_an_email_is_logged_with_who_it_went_to_and_the_outcome(): void
    {
        Mail::fake();
        $employee = $this->employeeIn($this->a);
        $item = PayrollMasterItem::create(['payroll_company_id' => null, 'list' => 'share_emails', 'label' => 'Accounts', 'value' => 'accounts@example.com']);

        $this->actingAs(User::factory()->admin()->create(['name' => 'Rita Shah']));
        $this->get(route('payroll.index', ['current_company' => $this->a->id]));
        $this->post(route('payroll.employee.share', $employee), ['recipient' => $item->id]);

        Mail::assertSent(StaffDetailsMail::class);
        $log = PayrollLog::where('action', 'emailed')->latest('id')->firstOrFail();
        $this->assertSame('success', $log->status);
        $this->assertSame('Rita Shah', $log->user_name);
        $this->assertSame('Accounts <accounts@example.com>', $log->details['Sent to']);
        $this->assertSame('Sent', $log->details['Outcome']);

        // A refused recipient is logged as not sent
        $this->post(route('payroll.employee.share', $employee), ['recipient' => 99999]);
        $failed = PayrollLog::where('action', 'emailed')->latest('id')->firstOrFail();
        $this->assertSame('failed', $failed->status);
        $this->assertSame('Not sent', $failed->details['Outcome']);
    }

    public function test_a_technical_failure_keeps_everything_needed_to_debug_it(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        try {
            throw new \RuntimeException('SMTP connection refused');
        } catch (\Throwable $e) {
            PayrollLogger::failure($e, 'Appointment letter email', null, 'Asha Menon', $this->a->id);
        }

        $log = PayrollLog::where('action', 'error')->firstOrFail();
        $this->assertSame('failed', $log->status);
        $this->assertSame($this->a->id, $log->payroll_company_id);
        $this->assertSame(\RuntimeException::class, $log->details['Exception']);
        $this->assertSame('SMTP connection refused', $log->details['Message']);
        $this->assertStringContainsString('PayrollLogTest.php', $log->details['Thrown at']);
        $this->assertArrayHasKey('Call trail', $log->details);
        $this->assertArrayHasKey('PHP', $log->details);
    }

    public function test_passwords_never_reach_the_log(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $this->get(route('payroll.log.index'), []);

        $this->withServerVariables([])->post('/nowhere', ['password' => 'Secret#123', 'note' => 'hello']);
        PayrollLogger::failure(new \Exception('boom'), 'Test');

        $this->assertStringNotContainsString('Secret#123', json_encode(PayrollLog::all()->toArray()));
    }

    public function test_the_log_follows_the_scope_all_companies_or_one(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $this->employeeIn($this->a, 'A-1');
        $this->employeeIn($this->b, 'B-1');

        $this->get(route('payroll.log.index', ['scope' => 'all']));
        $this->get(route('payroll.log.index'))->assertSee('A-1')->assertSee('B-1');

        $this->get(route('payroll.log.index', ['scope' => $this->a->id]));
        $this->get(route('payroll.log.index'))->assertSee('A-1')->assertDontSee('B-1');
    }

    public function test_the_log_can_be_searched_and_filtered(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $employee = $this->employeeIn($this->a, 'A-1');
        $employee->update(['salary' => 31000]);

        $this->get(route('payroll.log.index', ['scope' => 'all']));
        $this->get(route('payroll.log.index', ['action' => 'updated']))->assertSee('31000')->assertDontSee('Staff added');
        $this->get(route('payroll.log.index', ['q' => 'nothing-like-this']))->assertSee('Nothing matches');
    }
}
