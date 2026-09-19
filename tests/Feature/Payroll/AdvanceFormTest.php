<?php

namespace Tests\Feature\Payroll;

use App\Models\Employee;
use App\Models\PayrollAdvance;
use App\Models\PayrollCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The instalment only means something for a monthly recovery. The form hides
 * it otherwise, and the server must hold the same line, or a request that
 * skips the form could save an advance that would never clear.
 */
class AdvanceFormTest extends TestCase
{
    use RefreshDatabase;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $company = PayrollCompany::create(['name' => 'Pallav Hotel', 'code' => 'PH01']);
        $this->employee = Employee::create([
            'payroll_company_id' => $company->id, 'employee_code' => 'E-1', 'name' => 'Asha Menon',
            'designation' => 'Cook', 'joining_date' => now()->subYear(), 'salary' => 20000,
            'daily_working_hours' => 8, 'payment_mode' => 'Cash', 'is_active' => true,
        ]);

        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('payroll.index', ['current_company' => $company->id]));
    }

    private function post_advance(array $overrides = [])
    {
        return $this->post(route('payroll.advance.store'), array_merge([
            'employee_id' => $this->employee->id,
            'advance_date' => now()->format('Y-m-d H:i:s'),
            'amount' => 6000,
            'deduction_type' => 'Monthly',
        ], $overrides));
    }

    public function test_a_monthly_advance_needs_an_instalment(): void
    {
        $this->post_advance()->assertSessionHasErrors('deduction_amount');
        $this->assertDatabaseCount('payroll_advances', 0);
    }

    public function test_a_monthly_advance_cannot_have_a_zero_instalment(): void
    {
        $this->post_advance(['deduction_amount' => 0])->assertSessionHasErrors('deduction_amount');
    }

    public function test_a_monthly_advance_with_an_instalment_is_saved(): void
    {
        $this->post_advance(['deduction_amount' => 1500])->assertSessionHasNoErrors();

        $this->assertSame('1500.00', (string) PayrollAdvance::first()->deduction_amount);
    }

    public function test_a_one_time_advance_needs_no_instalment_and_takes_the_full_amount(): void
    {
        $this->post_advance(['deduction_type' => 'One Time'])->assertSessionHasNoErrors();

        $this->assertSame('6000.00', (string) PayrollAdvance::first()->deduction_amount);
    }

    public function test_the_form_only_offers_the_instalment_for_a_monthly_recovery(): void
    {
        $this->get(route('payroll.advance.index'))
            ->assertOk()
            ->assertSee('data-show-when="deduction_type=Monthly"', false);
    }
}
