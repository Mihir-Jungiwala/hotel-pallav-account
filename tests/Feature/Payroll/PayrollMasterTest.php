<?php

namespace Tests\Feature\Payroll;

use App\Mail\StaffDetailsMail;
use App\Models\Employee;
use App\Models\PayrollCompany;
use App\Models\PayrollMasterItem;
use App\Models\User;
use App\Support\PayrollContext;
use App\Support\PayrollMasters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Payroll Master belongs to the SuperAdmin. With no company selected it
 * reaches every company; with one selected, that company alone. Staff details
 * can only be emailed to an address kept there.
 */
class PayrollMasterTest extends TestCase
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

    private function asSuperAdmin(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
    }

    private function open(?PayrollCompany $company): void
    {
        $company
            ? $this->get(route('payroll.index', ['current_company' => $company->id]))
            : $this->get(route('payroll.index'));
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

    private function recipient(?PayrollCompany $company, string $email = 'accounts@example.com', bool $active = true): PayrollMasterItem
    {
        return PayrollMasterItem::create([
            'payroll_company_id' => $company?->id, 'list' => 'share_emails',
            'label' => 'Accounts', 'value' => $email, 'is_active' => $active,
        ]);
    }

    public function test_only_the_superadmin_can_open_it_or_see_it_in_the_menu(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->open($this->a);

        $this->get(route('payroll.master.index'))->assertForbidden();
        $this->get(route('payroll.staff.index'))->assertOk()->assertDontSee('Payroll Master');
        $this->post(route('payroll.master.store', 'share_emails'), ['label' => 'X', 'value' => 'x@example.com'])->assertForbidden();

        $this->asSuperAdmin();
        $this->open($this->a);
        $this->get(route('payroll.master.index'))->assertOk();
        $this->get(route('payroll.staff.index'))->assertSee('Payroll Master');
    }

    public function test_it_opens_with_no_company_selected(): void
    {
        $this->asSuperAdmin();
        $this->open(null);

        $this->get(route('payroll.master.index'))->assertOk()->assertSee('All companies');
    }

    public function test_with_no_company_selected_an_item_applies_to_every_company(): void
    {
        $this->asSuperAdmin();
        $this->open(null);

        $this->post(route('payroll.master.store', 'share_emails'), ['label' => 'Owner', 'value' => 'Owner@Example.com'])
            ->assertRedirect();

        $item = PayrollMasterItem::where('list', 'share_emails')->firstOrFail();
        $this->assertNull($item->payroll_company_id);
        $this->assertSame('owner@example.com', $item->value);
        $this->assertCount(1, PayrollMasters::active('share_emails', $this->a));
        $this->assertCount(1, PayrollMasters::active('share_emails', $this->b));
    }

    public function test_with_a_company_selected_an_item_applies_to_that_company_only(): void
    {
        $this->asSuperAdmin();
        $this->open($this->a);

        $this->post(route('payroll.master.store', 'share_emails'), ['label' => 'Manager', 'value' => 'm@example.com']);

        $this->assertSame($this->a->id, PayrollMasterItem::where('list', 'share_emails')->firstOrFail()->payroll_company_id);
        $this->assertCount(1, PayrollMasters::active('share_emails', $this->a));
        $this->assertCount(0, PayrollMasters::active('share_emails', $this->b));
        $this->assertCount(0, PayrollMasters::active('share_emails', null));
    }

    public function test_a_company_sees_the_shared_items_and_its_own_but_not_anothers(): void
    {
        $this->recipient(null, 'all@example.com');
        $this->recipient($this->a, 'a@example.com');
        $this->recipient($this->b, 'b@example.com');

        $this->assertEqualsCanonicalizing(['all@example.com', 'a@example.com'], PayrollMasters::active('share_emails', $this->a)->pluck('value')->all());
        $this->assertEqualsCanonicalizing(['all@example.com', 'b@example.com'], PayrollMasters::active('share_emails', $this->b)->pluck('value')->all());
    }

    public function test_the_scope_chooser_switches_between_all_and_a_company(): void
    {
        $this->asSuperAdmin();
        $this->open(null);

        $this->get(route('payroll.master.index', ['scope' => $this->b->id]))->assertRedirect(route('payroll.master.index'));
        $this->assertSame((string) $this->b->id, PayrollContext::selectedValue());

        $this->get(route('payroll.master.index', ['scope' => 'all']));
        $this->assertSame(PayrollContext::SENTINEL, PayrollContext::selectedValue());
    }

    public function test_an_item_can_only_be_changed_from_the_scope_it_belongs_to(): void
    {
        $shared = $this->recipient(null, 'all@example.com');
        $own = $this->recipient($this->a, 'a@example.com');

        $this->asSuperAdmin();
        $this->open($this->a);

        // Inside a company, the shared item is read-only
        $this->put(route('payroll.master.update', $shared), ['label' => 'Changed', 'value' => 'x@example.com'])->assertSessionHas('error');
        $this->post(route('payroll.master.toggle', $shared))->assertSessionHas('error');
        $this->delete(route('payroll.master.destroy', $shared))->assertSessionHas('error');
        $this->assertTrue($shared->fresh()->is_active);
        $this->assertSame('Accounts', $shared->fresh()->label);

        // Its own is editable
        $this->post(route('payroll.master.toggle', $own))->assertSessionHas('success');
        $this->assertFalse($own->fresh()->is_active);

        // And another company's is out of reach
        $this->open($this->b);
        $this->delete(route('payroll.master.destroy', $own))->assertSessionHas('error');
        $this->assertDatabaseHas('payroll_master_items', ['id' => $own->id]);
    }

    public function test_an_email_list_needs_a_valid_address_and_refuses_duplicates(): void
    {
        $this->asSuperAdmin();
        $this->open(null);

        $this->post(route('payroll.master.store', 'share_emails'), ['label' => 'Bad', 'value' => 'not-an-email'])->assertSessionHasErrors('value');
        $this->post(route('payroll.master.store', 'share_emails'), ['label' => 'Ok', 'value' => 'ok@example.com'])->assertSessionDoesntHaveErrors();
        $this->post(route('payroll.master.store', 'share_emails'), ['label' => 'Again', 'value' => 'OK@example.com'])->assertSessionHasErrors('value');
        $this->post(route('payroll.master.store', 'nonsense'), ['label' => 'X'])->assertNotFound();

        $this->assertSame(1, PayrollMasterItem::where('list', 'share_emails')->count());
    }

    public function test_clicking_share_emails_the_details_to_that_address(): void
    {
        Mail::fake();
        $employee = $this->employeeIn($this->a);
        $recipient = $this->recipient(null, 'accounts@example.com');

        $this->actingAs(User::factory()->admin()->create(['name' => 'Rita Shah']));
        $this->open($this->a);

        $this->post(route('payroll.employee.share', $employee), ['recipient' => $recipient->id])
            ->assertSessionHas('success');

        Mail::assertSent(StaffDetailsMail::class, function (StaffDetailsMail $mail) {
            return $mail->hasTo('accounts@example.com')
                && str_contains($mail->pdf, '%PDF')
                && $mail->sharedBy === 'Rita Shah'
                && str_contains($mail->render(), 'Asha Menon');
        });
    }

    public function test_share_only_goes_to_a_listed_active_address_that_the_company_can_use(): void
    {
        Mail::fake();
        $employee = $this->employeeIn($this->a);
        $otherCompanys = $this->recipient($this->b, 'b@example.com');
        $off = $this->recipient(null, 'off@example.com', false);

        $this->actingAs(User::factory()->admin()->create());
        $this->open($this->a);

        foreach ([$otherCompanys->id, $off->id, 99999] as $id) {
            $this->post(route('payroll.employee.share', $employee), ['recipient' => $id])->assertSessionHas('error');
        }

        // Anything typed in place of a list entry is not accepted
        $this->post(route('payroll.employee.share', $employee), ['recipient' => 'someone@example.com'])->assertSessionHasErrors('recipient');

        Mail::assertNothingSent();
    }

    public function test_share_cannot_reach_another_companys_staff(): void
    {
        Mail::fake();
        $foreign = $this->employeeIn($this->b, 'E-9');
        $recipient = $this->recipient(null);

        $this->actingAs(User::factory()->admin()->create());
        $this->open($this->a);

        $this->post(route('payroll.employee.share', $foreign), ['recipient' => $recipient->id])->assertNotFound();
        Mail::assertNothingSent();
    }

    public function test_the_staff_page_offers_the_recipients_and_points_the_superadmin_to_the_master_when_there_are_none(): void
    {
        $this->employeeIn($this->a);

        $this->actingAs(User::factory()->admin()->create());
        $this->open($this->a);
        $this->get(route('payroll.staff.index'))->assertSee('No recipients yet.')->assertSee('Ask the SuperAdmin');

        $this->recipient($this->a, 'accounts@example.com');
        $this->get(route('payroll.staff.index'))->assertSee('accounts@example.com');

        $this->asSuperAdmin();
        $this->open($this->b);
        $this->employeeIn($this->b, 'E-2');
        $this->get(route('payroll.staff.index'))->assertSee('Add some in Payroll Master')->assertDontSee('accounts@example.com');
    }

    public function test_designations_from_the_master_are_suggested_on_the_staff_form(): void
    {
        PayrollMasterItem::create(['payroll_company_id' => null, 'list' => 'designations', 'label' => 'Head Chef']);
        PayrollMasterItem::create(['payroll_company_id' => $this->b->id, 'list' => 'designations', 'label' => 'Only B']);

        $this->actingAs(User::factory()->admin()->create());
        $this->open($this->a);

        $this->get(route('payroll.staff.index'))->assertSee('Head Chef')->assertDontSee('Only B');
    }

    public function test_payrolls_lists_no_longer_live_in_the_general_master_data(): void
    {
        foreach (['gender', 'separation_type', 'salary_payment_mode', 'advance_deduction_type', 'bonus_type', 'attendance_status_type'] as $key) {
            $this->assertDatabaseMissing('option_sets', ['key' => $key]);
        }
    }

    public function test_salary_payment_modes_can_be_added_and_removed_and_are_what_forms_accept(): void
    {
        $this->asSuperAdmin();
        $this->open($this->a);

        $this->post(route('payroll.master.store', 'salary_payment_mode'), ['label' => 'UPI'])->assertSessionDoesntHaveErrors();
        $this->assertContains('UPI', PayrollMasters::choices('salary_payment_mode', $this->a));
        $this->assertNotContains('UPI', PayrollMasters::choices('salary_payment_mode', $this->b));

        $employee = $this->employeeIn($this->a);
        $this->put(route('payroll.salary-update.update', $employee), [
            'salary' => 24000, 'payment_mode' => 'UPI', 'daily_working_hours' => 8,
        ])->assertSessionDoesntHaveErrors('payment_mode');

        $this->put(route('payroll.salary-update.update', $employee), [
            'salary' => 24000, 'payment_mode' => 'Barter', 'daily_working_hours' => 8,
        ])->assertSessionHasErrors('payment_mode');

        $item = PayrollMasterItem::where('list', 'salary_payment_mode')->where('label', 'UPI')->firstOrFail();
        $this->delete(route('payroll.master.destroy', $item));
        $this->assertNotContains('UPI', PayrollMasters::choices('salary_payment_mode', $this->a));
    }

    public function test_exit_types_and_genders_come_from_the_master_and_forms_validate_against_them(): void
    {
        $this->asSuperAdmin();
        $this->open($this->a);

        PayrollMasterItem::where('list', 'gender')->delete();
        PayrollMasterItem::create(['payroll_company_id' => $this->a->id, 'list' => 'gender', 'label' => 'Prefer not to say']);

        $this->assertSame(['Prefer not to say'], PayrollMasters::choices('gender', $this->a));
        // Another company falls back to the built-in choices
        $this->assertSame(['Male', 'Female', 'Other'], PayrollMasters::choices('gender', $this->b));

        $this->get(route('payroll.staff.index'))->assertOk()->assertSee('Prefer not to say');
    }

    public function test_a_salary_revision_is_kept_and_the_persons_page_shows_every_record(): void
    {
        $employee = $this->employeeIn($this->a);
        $this->actingAs(User::factory()->admin()->create());
        $this->open($this->a);

        foreach ([26000, 30000] as $salary) {
            $this->put(route('payroll.salary-update.update', $employee), [
                'effective_date' => now()->toDateString(), 'salary' => $salary, 'daily_working_hours' => 8, 'payment_mode' => 'Cash',
            ])->assertSessionHas('success');
        }

        // The list shows only the current figure and a way in
        $this->get(route('payroll.salary-update.index'))->assertOk()->assertSee('30,000.00')->assertDontSee('26,000.00');

        // The person's page shows every record: what it was, and what it became
        $this->get(route('payroll.salary-update.show', $employee))->assertOk()
            ->assertSee('24000')->assertSee('26000')->assertSee('30000')->assertSee('2 changes on record');
    }

    public function test_another_companys_person_has_no_salary_page(): void
    {
        $foreign = $this->employeeIn($this->b, 'E-9');
        $this->actingAs(User::factory()->admin()->create());
        $this->open($this->a);

        $this->get(route('payroll.salary-update.show', $foreign))->assertNotFound();
    }
}