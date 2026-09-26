<?php

namespace Tests\Feature\Payroll;

use App\Models\PayrollCompany;
use App\Models\User;
use App\Support\PayrollContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Payroll runs for exactly Hotel Pallav and Pallav Food; nobody adds or removes one. */
class FixedCompaniesTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_the_listing_creates_the_two_companies_once(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('payroll.index'))->assertOk()->assertSee('Hotel Pallav')->assertSee('Pallav Food');
        $this->get(route('payroll.index'));

        $this->assertSame(['Hotel Pallav', 'Pallav Food'], PayrollCompany::orderBy('id')->pluck('name')->all());
    }

    public function test_there_is_no_way_to_add_delete_or_deactivate_a_company(): void
    {
        foreach (['payroll.company.store', 'payroll.company.destroy', 'payroll.company.toggle-active'] as $name) {
            $this->assertFalse(Route::has($name), "{$name} should not exist");
        }

        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('payroll.index'))->assertDontSee('New Company')->assertDontSee('Delete company');
    }

    public function test_a_companys_name_and_code_cannot_be_changed_but_its_details_can(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('payroll.index'));
        $company = PayrollCompany::where('code', 'HP01')->firstOrFail();

        $this->put(route('payroll.company.update', $company), [
            'name' => 'Renamed', 'code' => 'ZZ99', 'mobile_number' => '9000000000',
            'address' => '1 Station Road', 'city' => 'Rajkot', 'state' => 'Gujarat',
            'authorized_person_name' => 'R Patel', 'authorized_designation' => 'Manager',
        ])->assertSessionDoesntHaveErrors();

        $company->refresh();
        $this->assertSame('Hotel Pallav', $company->name);
        $this->assertSame('HP01', $company->code);
        $this->assertSame('Rajkot', $company->city);
    }

    /**
     * The sidebar dropdown and the Company Listing page must never show the two
     * companies in a different order from each other, whatever order they were
     * created, saved or last touched in.
     */
    public function test_the_sidebar_dropdown_and_the_listing_page_show_companies_in_the_same_order(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('payroll.index'));

        // Pallav Food is touched last and would sort first by recency or id-desc
        $food = PayrollCompany::where('code', 'PF01')->firstOrFail();
        $food->touch();

        $dropdownOrder = PayrollContext::selectable()->pluck('code')->all();
        $listingOrder = $this->get(route('payroll.index'))
            ->viewData('companies')->pluck('code')->all();

        $this->assertSame(['HP01', 'PF01'], $dropdownOrder);
        $this->assertSame($dropdownOrder, $listingOrder);
    }
}
