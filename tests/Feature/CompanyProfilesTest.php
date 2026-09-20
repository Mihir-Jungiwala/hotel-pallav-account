<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyProfilesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    private function company(array $attributes = []): CompanyProfile
    {
        return CompanyProfile::create($attributes + ['name' => 'Acme Travels']);
    }

    public function test_the_list_shows_the_details_and_the_pop_up_form(): void
    {
        $company = $this->company([
            'name' => 'Sunrise Tours', 'email' => 'desk@sunrise.test', 'gst_number' => '24ABCDE1234F1Z5', 'gst_percentage' => 12,
            'contacts' => [['role' => 'HR Head', 'name' => 'Kiran Rao', 'email' => null, 'mobile' => null]], 'address' => 'MG Road',
        ]);

        $this->get(route('company.index'))->assertOk()
            ->assertSee('Sunrise Tours')->assertSee('desk@sunrise.test')->assertSee('24ABCDE1234F1Z5')
            ->assertSee('GST 12%')->assertSee('Kiran Rao')->assertSee('HR Head')->assertSee('id="companyModal"', false)
            ->assertSee('data-company-id="'.$company->id.'"', false);
    }

    public function test_search_finds_a_company_by_any_detail_and_all_words_must_match(): void
    {
        $this->company(['name' => 'Sunrise Tours', 'gst_number' => '24AAAAA1111A1Z1', 'contacts' => [['role' => 'Managing Director', 'name' => 'Meera Joshi']]]);
        $this->company(['name' => 'Moonlight Events', 'gst_number' => '27BBBBB2222B1Z2']);

        $this->get(route('company.index', ['q' => 'meera']))->assertSee('Sunrise Tours')->assertDontSee('Moonlight Events');
        $this->get(route('company.index', ['q' => '27BBBBB']))->assertSee('Moonlight Events')->assertDontSee('Sunrise Tours');
        $this->get(route('company.index', ['q' => 'sunrise joshi']))->assertSee('Sunrise Tours');
        $this->get(route('company.index', ['q' => 'sunrise moonlight']))->assertSee('No companies match');
    }

    public function test_a_company_can_be_added_edited_and_deleted_and_the_filter_stays(): void
    {
        $this->from(route('company.index', ['q' => 'x']))->post(route('company.store'), ['name' => 'New Co', 'gst_percentage' => 18, 'contacts' => [['role' => 'Managing Director', 'name' => 'Main Person']]])
            ->assertRedirect(route('company.index', ['q' => 'x']))->assertSessionHasNoErrors();

        $company = CompanyProfile::sole();
        $this->put(route('company.update', $company), ['name' => 'Renamed Co', 'email' => 'a@b.test', 'contacts' => [['role' => 'Managing Director', 'name' => 'Main Person']]])->assertSessionHasNoErrors();
        $this->assertSame('Renamed Co', $company->fresh()->name);

        $this->delete(route('company.destroy', $company))->assertSessionHas('success');
        $this->assertSame(0, CompanyProfile::count());
    }

    public function test_a_duplicate_name_or_gst_number_is_refused_with_a_message(): void
    {
        $this->company(['name' => 'Taken Co', 'gst_number' => 'GST-1']);

        $this->post(route('company.store'), ['name' => 'Taken Co', 'contacts' => [['role' => 'Managing Director', 'name' => 'Main Person']]])->assertSessionHasErrors('name');
        $this->post(route('company.store'), ['name' => 'Other Co', 'gst_number' => 'GST-1', 'contacts' => [['role' => 'Managing Director', 'name' => 'Main Person']]])->assertSessionHasErrors('gst_number');
        $this->post(route('company.store'), ['name' => 'Rates Co', 'gst_percentage' => 150, 'contacts' => [['role' => 'Managing Director', 'name' => 'Main Person']]])->assertSessionHasErrors('gst_percentage');
    }

    public function test_a_company_can_keep_its_own_name_when_edited(): void
    {
        $company = $this->company(['name' => 'Same Co']);

        $this->put(route('company.update', $company), ['name' => 'Same Co', 'country' => 'India', 'contacts' => [['role' => 'Managing Director', 'name' => 'Main Person']]])->assertSessionHasNoErrors();
        $this->assertSame('India', $company->fresh()->country);
    }

    public function test_a_refused_save_reopens_the_pop_up_with_what_was_typed(): void
    {
        $this->from(route('company.index'))->post(route('company.store'), ['_form' => 'company', 'name' => '', 'email' => 'typed@keep.test', 'gst_percentage' => 999])
            ->assertSessionHasErrors();

        $this->get(route('company.index'))->assertSee('"email":"typed@keep.test"', false);
    }

    public function test_people_are_saved_as_a_list_where_a_role_can_repeat_and_empty_rows_are_dropped(): void
    {
        $this->post(route('company.store'), ['name' => 'People Co', 'contacts' => [
            ['role' => 'Managing Director', 'name' => 'First MD', 'email' => 'a@x.test', 'mobile' => '111'],
            ['role' => 'Managing Director', 'name' => 'Second MD', 'email' => '', 'mobile' => ''],
            ['role' => 'HR Head', 'name' => '', 'email' => '', 'mobile' => ''],
        ]])->assertSessionHasNoErrors();

        $people = CompanyProfile::sole()->contacts;
        $this->assertCount(2, $people);
        $this->assertSame(['Managing Director', 'Managing Director'], array_column($people, 'role'));

        $this->post(route('company.store'), ['name' => 'Bad Co', 'contacts' => [['role' => 'HR Head', 'name' => 'X', 'email' => 'not-an-email']]])
            ->assertSessionHasErrors('contacts.0.email');
    }

    public function test_the_contact_roles_come_from_master_data_and_the_view_has_delete_and_edit(): void
    {
        $company = $this->company(['name' => 'Viewable Co']);

        $html = $this->get(route('company.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<option value="Managing Director">Managing Director</option>', $html);
        $this->assertStringContainsString('data-add-person', $html);
        $this->assertStringContainsString('id="companyView"', $html);
        $this->assertStringContainsString('data-bs-target="#companyView" data-company-id="'.$company->id.'"', $html);
        $this->assertStringContainsString(route('company.destroy', $company), $html);
    }

    public function test_at_least_one_named_contact_person_is_required(): void
    {
        $this->post(route('company.store'), ['name' => 'No People Co'])->assertSessionHasErrors('contacts');
        $this->post(route('company.store'), ['name' => 'No People Co', 'contacts' => []])->assertSessionHasErrors('contacts');
        // A row left completely empty does not count as a person
        $this->post(route('company.store'), ['name' => 'No People Co', 'contacts' => [['role' => 'HR Head', 'name' => '', 'email' => '', 'mobile' => '']]])->assertSessionHasErrors('contacts');
        // A person with an email but no name is refused
        $this->post(route('company.store'), ['name' => 'No People Co', 'contacts' => [['role' => 'HR Head', 'name' => '', 'email' => 'x@y.test']]])->assertSessionHasErrors('contacts.0.name');
        $this->assertSame(0, CompanyProfile::count());

        $this->post(route('company.store'), ['name' => 'One Person Co', 'contacts' => [['role' => 'Other', 'name' => 'Only One']]])->assertSessionHasNoErrors();
        $this->assertSame(1, CompanyProfile::count());
    }

    public function test_an_edit_cannot_remove_the_last_contact_person(): void
    {
        $company = $this->company(['name' => 'Kept Co', 'contacts' => [['role' => 'HR Head', 'name' => 'Stays']]]);

        $this->put(route('company.update', $company), ['name' => 'Kept Co', 'contacts' => []])->assertSessionHasErrors('contacts');
        $this->assertSame('Stays', $company->fresh()->contacts[0]['name']);
    }

    public function test_the_profile_sheet_renders_as_a_one_page_pdf(): void
    {
        $company = $this->company([
            'name' => 'Sheet Co', 'gst_number' => 'GST-9', 'gst_percentage' => 18, 'address' => 'MG Road',
            'instruction' => 'Bill monthly.',
            'contacts' => [
                ['role' => 'Managing Director', 'name' => 'Meera Joshi', 'email' => 'm@x.test', 'mobile' => '111'],
                ['role' => 'Accountant Head', 'name' => 'Raj Shah', 'email' => '', 'mobile' => '222'],
            ],
        ]);

        $response = $this->get(route('company.view', $company))->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));

        $pdf = $response->getContent();
        $this->assertSame(1, \App\Support\PayrollPdf::pageCount($pdf));
    }

    public function test_the_pdf_button_is_on_the_row_and_in_the_view(): void
    {
        $company = $this->company(['name' => 'Linked Co']);

        $this->get(route('company.index'))->assertOk()
            ->assertSee(route('company.view', $company), false)
            ->assertSee('data-view-pdf', false);
    }
}
