<?php

namespace Tests\Feature\Payroll;

use App\Models\Employee;
use App\Models\PayrollCompany;
use App\Models\User;
use App\Support\PhoneCountries;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A mobile number is a country code plus the national digits. India is the
 * default, and a "+" in front of a number names its own country.
 */
class PhoneNumberTest extends TestCase
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

    private function add(array $overrides = [])
    {
        return $this->post(route('payroll.employee.store'), array_merge([
            'employee_code' => 'E-9', 'name' => 'Riya Shah', 'designation' => 'Cook',
            'joining_date' => now()->toDateString(), 'salary' => 18000,
            'daily_working_hours' => 8, 'payment_mode' => 'Cash',
            'contact_number' => '9876543210', 'email' => 'riya@example.com',
        ], $overrides));
    }

    /* ------------------------------------------------------------ the rules */

    public function test_india_is_the_default_country(): void
    {
        $this->add()->assertSessionHasNoErrors();

        $employee = Employee::first();
        $this->assertSame('91', $employee->contact_country);
        $this->assertSame('+91 98765 43210', $employee->contactDisplay());
    }

    public function test_a_plus_names_the_country_and_overrides_the_selector(): void
    {
        $this->add(['contact_country' => '91', 'contact_number' => '+971 50 123 4567'])->assertSessionHasNoErrors();

        $employee = Employee::first();
        $this->assertSame('971', $employee->contact_country);
        $this->assertSame('501234567', $employee->contact_number);
        $this->assertSame('+971 501234567', $employee->contactDisplay());
    }

    public function test_a_country_chosen_from_the_list_is_kept(): void
    {
        $this->add(['contact_country' => '44', 'contact_number' => '7400 123456'])->assertSessionHasNoErrors();

        $employee = Employee::first();
        $this->assertSame('44', $employee->contact_country);
        $this->assertSame('7400123456', $employee->contact_number);
    }

    public function test_00_works_as_well_as_a_plus(): void
    {
        $this->add(['contact_number' => '0044 7400 123456'])->assertSessionHasNoErrors();

        $this->assertSame('44', Employee::first()->contact_country);
    }

    public function test_the_longest_code_wins(): void
    {
        // 971 (UAE), not 97 or 9
        $this->assertSame(['971', '501234567'], PhoneCountries::split('+971501234567'));
        // 91 (India), not a code starting with 9
        $this->assertSame(['91', '9876543210'], PhoneCountries::split('+919876543210'));
        // a single-digit code
        $this->assertSame(['1', '2015550123'], PhoneCountries::split('+1 201 555 0123'));
        $this->assertNull(PhoneCountries::split('9876543210'));
        $this->assertNull(PhoneCountries::split('+999123456'));
    }

    public function test_each_country_is_held_to_its_own_length(): void
    {
        $this->add(['contact_country' => '971', 'contact_number' => '5012345'])       // too short for the UAE
            ->assertSessionHasErrors('contact_number');

        $this->assertStringContainsString('United Arab Emirates', session('errors')->first('contact_number'));

        $this->add(['contact_country' => '65', 'contact_number' => '81234567'])       // right for Singapore
            ->assertSessionHasNoErrors();
    }

    public function test_india_still_needs_a_number_starting_6_to_9(): void
    {
        $this->add(['contact_number' => '5876543210'])->assertSessionHasErrors('contact_number');
        $this->add(['contact_number' => '12345'])->assertSessionHasErrors('contact_number');
        $this->assertDatabaseCount('employees', 0);
    }

    public function test_an_unknown_country_code_is_refused(): void
    {
        $this->add(['contact_country' => '999'])->assertSessionHasErrors('contact_country');
    }

    public function test_an_indian_number_typed_with_its_code_but_no_plus_is_still_understood(): void
    {
        $this->add(['contact_number' => '919876543210'])->assertSessionHasNoErrors();

        $this->assertSame('9876543210', Employee::first()->contact_number);
    }

    public function test_a_ten_digit_indian_number_that_starts_91_is_not_mistaken_for_a_code(): void
    {
        // A real number beginning 91: ten digits, so nothing is stripped
        $this->add(['contact_number' => '9198765432'])->assertSessionHasNoErrors();

        $this->assertSame('9198765432', Employee::first()->contact_number);
    }

    public function test_the_emergency_contact_can_be_international_and_stays_optional(): void
    {
        $this->add([
            'emergency_contact_name' => 'Uncle', 'emergency_contact_number' => '+44 7400 123456',
        ])->assertSessionHasNoErrors();

        $employee = Employee::first();
        $this->assertSame('44', $employee->emergency_contact_country);
        $this->assertSame('+44 7400123456', $employee->emergencyContactDisplay());

        $this->add(['employee_code' => 'E-2', 'email' => 'b@example.com'])->assertSessionHasNoErrors();
        $this->assertSame('91', Employee::where('employee_code', 'E-2')->first()->emergency_contact_country);
    }

    public function test_the_country_list_offers_india_first_with_a_unique_code_for_each(): void
    {
        $all = PhoneCountries::all();

        $this->assertSame('IN', $all[0]['iso']);
        $this->assertSame('91', $all[0]['dial']);
        // Two countries sharing a code would make a typed "+" ambiguous
        $this->assertSame(count($all), count(array_unique(array_column($all, 'dial'))));
        $this->assertSame(count($all), count(array_unique(array_column($all, 'iso'))));
    }

    public function test_the_html_pattern_matches_the_server_rule(): void
    {
        $this->assertSame('[6-9][0-9]{9,9}', PhoneCountries::pattern('91'));
        $this->assertSame('5[0-9]{8,8}', PhoneCountries::pattern('971'));
        $this->assertSame('[0-9][0-9]{7,9}', PhoneCountries::pattern('64'));
    }

    /* ------------------------------------------------------------- the form */

    public function test_the_add_form_shows_the_country_chip_with_india_selected_and_the_list(): void
    {
        $this->get(route('payroll.staff.index'))
            ->assertOk()
            ->assertSee('class="phone-country"', false)
            ->assertSee('+91')
            ->assertSee('id="pmsPhoneCountries"', false)
            ->assertSee('United Arab Emirates');
    }

    public function test_an_existing_employee_keeps_their_country_when_the_form_reopens(): void
    {
        $this->add(['contact_number' => '+971 50 123 4567'])->assertSessionHasNoErrors();

        $this->get(route('payroll.staff.index'))
            ->assertOk()
            ->assertSee('name="contact_country" value="971"', false);
    }
}
