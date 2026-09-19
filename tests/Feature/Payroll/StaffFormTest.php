<?php

namespace Tests\Feature\Payroll;

use App\Mail\OfferLetterMail;
use App\Models\Employee;
use App\Models\JoiningLetter;
use App\Models\PayrollCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Adding staff: how a person can be reached is required and stored one way,
 * and saving sends them their appointment letter.
 */
class StaffFormTest extends TestCase
{
    use RefreshDatabase;

    private PayrollCompany $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = PayrollCompany::create([
            'name' => 'Pallav Hotel', 'code' => 'PH01', 'address' => '1 Station Road',
            'mobile_number' => '9000000000', 'email' => 'office@pallav.test',
            'authorized_person_name' => 'R Patel', 'authorized_designation' => 'Manager',
        ]);

        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('payroll.index', ['current_company' => $this->company->id]));
    }

    private function template(bool $active = true): JoiningLetter
    {
        return JoiningLetter::create([
            'payroll_company_id' => $this->company->id,
            'introduction_content' => 'We are pleased to appoint {EMPLOYEE_NAME} as {DESIGNATION}.',
            'roles_responsibilities' => '{RESPONSIBILITIES}',
            'terms_conditions' => 'Standard terms apply.', 'closing_message' => 'Welcome aboard.',
            'acceptance_heading' => 'Acceptance', 'acceptance_content' => 'I accept.',
            'use_company_signatory' => true, 'is_active' => $active,
        ]);
    }

    private function add(array $overrides = [])
    {
        return $this->post(route('payroll.employee.store'), array_merge([
            'employee_code' => 'E-9', 'name' => 'Riya Shah', 'designation' => 'Cook',
            'joining_date' => now()->toDateString(), 'salary' => 18000,
            'daily_working_hours' => 8, 'payment_mode' => 'Cash',
            'contact_number' => '9876543210', 'email' => 'riya@example.com',
        ], $overrides));
    }

    /* ------------------------------------------------------ required contact */

    public function test_a_mobile_number_and_an_email_are_both_required(): void
    {
        $this->add(['contact_number' => '', 'email' => ''])
            ->assertSessionHasErrors(['contact_number', 'email']);

        $this->assertDatabaseCount('employees', 0);
    }

    public function test_a_mobile_number_must_be_a_real_ten_digit_number(): void
    {
        $this->add(['contact_number' => '12345'])->assertSessionHasErrors('contact_number');
        $this->add(['contact_number' => '5876543210'])->assertSessionHasErrors('contact_number');
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_however_a_number_is_typed_it_is_stored_as_ten_digits(): void
    {
        foreach (['+91 98765 43210', '098765-43210', '98765 43210', '+919876543210'] as $i => $typed) {
            $this->add(['employee_code' => 'E-'.$i, 'email' => "p{$i}@example.com", 'contact_number' => $typed])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(['9876543210'], Employee::pluck('contact_number')->unique()->values()->all());
    }

    public function test_an_email_is_stored_lower_case(): void
    {
        $this->add(['email' => 'Riya.Shah@Example.COM'])->assertSessionHasNoErrors();

        $this->assertSame('riya.shah@example.com', Employee::first()->email);
    }

    public function test_two_people_in_one_company_cannot_share_an_email(): void
    {
        $this->add()->assertSessionHasNoErrors();

        $this->add(['employee_code' => 'E-10', 'name' => 'Someone Else'])
            ->assertSessionHasErrors('email');
    }

    public function test_the_same_email_is_fine_in_a_different_company(): void
    {
        $this->add()->assertSessionHasNoErrors();

        $other = PayrollCompany::create(['name' => 'Pallav Food', 'code' => 'PF01']);
        $this->get(route('payroll.index', ['current_company' => $other->id]));

        $this->add(['employee_code' => 'E-1'])->assertSessionHasNoErrors();
        $this->assertSame(2, Employee::count());
    }

    public function test_an_emergency_contact_is_kept_and_checked(): void
    {
        $this->add([
            'emergency_contact_name' => 'Meena Shah', 'emergency_contact_relation' => 'Mother',
            'emergency_contact_number' => '+91 91234 56789',
        ])->assertSessionHasNoErrors();

        $employee = Employee::first();
        $this->assertSame('Meena Shah', $employee->emergency_contact_name);
        $this->assertSame('9123456789', $employee->emergency_contact_number);

        $this->add(['employee_code' => 'E-2', 'email' => 'x@example.com', 'emergency_contact_number' => '123'])
            ->assertSessionHasErrors('emergency_contact_number');
    }

    /* ------------------------------------------------------- offer letter */

    public function test_saving_emails_the_appointment_letter_with_the_pdf_attached(): void
    {
        Mail::fake();
        $this->template();

        $this->add(['send_offer_letter' => '1'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        Mail::assertSent(OfferLetterMail::class, function (OfferLetterMail $mail) {
            return $mail->hasTo('riya@example.com')
                && str_starts_with($mail->pdf, '%PDF')
                && $mail->filename === 'appointment-letter-E-9.pdf';
        });
        Mail::assertSentCount(1);
    }

    public function test_the_letter_is_not_sent_when_the_switch_is_off(): void
    {
        Mail::fake();
        $this->template();

        $this->add()->assertSessionHasNoErrors();

        Mail::assertNothingSent();
        $this->assertDatabaseCount('employees', 1);
    }

    public function test_without_a_template_the_person_is_still_added_and_told_why_nothing_was_sent(): void
    {
        Mail::fake();

        $this->add(['send_offer_letter' => '1'])
            ->assertSessionHas('error');

        Mail::assertNothingSent();
        $this->assertDatabaseCount('employees', 1);
        $this->assertStringContainsString('no active joining letter template', session('error'));
    }

    public function test_an_inactive_template_is_not_used(): void
    {
        Mail::fake();
        $this->template(active: false);

        $this->add(['send_offer_letter' => '1']);

        Mail::assertNothingSent();
    }

    public function test_a_mail_failure_never_stops_the_person_being_added(): void
    {
        $this->template();

        // Make the mailer throw the way a bad SMTP setup would
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('connection refused'));

        $this->add(['send_offer_letter' => '1'])->assertSessionHas('error');

        $this->assertDatabaseCount('employees', 1);
        $this->assertStringContainsString('could not be emailed', session('error'));
    }

    public function test_editing_someone_never_sends_the_letter(): void
    {
        Mail::fake();
        $this->template();
        $this->add()->assertSessionHasNoErrors();
        $employee = Employee::first();

        $this->put(route('payroll.employee.update', $employee), [
            'employee_code' => 'E-9', 'name' => 'Riya S', 'designation' => 'Cook',
            'joining_date' => now()->toDateString(), 'salary' => 19000, 'daily_working_hours' => 8,
            'payment_mode' => 'Cash', 'contact_number' => '9876543210', 'email' => 'riya@example.com',
            'send_offer_letter' => '1',
        ])->assertSessionHasNoErrors();

        Mail::assertNothingSent();
    }

    public function test_the_letter_can_be_sent_again_from_the_staff_list(): void
    {
        Mail::fake();
        $this->template();
        $this->add()->assertSessionHasNoErrors();

        $this->post(route('payroll.employee.offer-letter.send', Employee::first()))
            ->assertSessionHas('success');

        Mail::assertSent(OfferLetterMail::class, fn ($m) => $m->hasTo('riya@example.com'));
    }

    public function test_the_letter_cannot_be_sent_for_another_companys_employee(): void
    {
        Mail::fake();
        $this->template();

        $other = PayrollCompany::create(['name' => 'Pallav Food', 'code' => 'PF01']);
        $foreign = Employee::create([
            'payroll_company_id' => $other->id, 'employee_code' => 'X-1', 'name' => 'Outsider',
            'email' => 'outsider@example.com', 'is_active' => true,
        ]);

        $this->post(route('payroll.employee.offer-letter.send', $foreign))->assertNotFound();

        Mail::assertNothingSent();
    }

    public function test_the_add_form_offers_the_letter_only_when_a_template_exists(): void
    {
        $this->get(route('payroll.staff.index'))
            ->assertOk()
            ->assertSee('no active joining letter template yet', false);

        $this->template();

        $this->get(route('payroll.staff.index'))
            ->assertOk()
            ->assertSee('Email the appointment letter when I save')
            ->assertDontSee('no active joining letter template yet', false);
    }
}
