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
            'hr_head_name' => 'Kiran Rao', 'address' => 'MG Road',
        ]);

        $this->get(route('company.index'))->assertOk()
            ->assertSee('Sunrise Tours')->assertSee('desk@sunrise.test')->assertSee('24ABCDE1234F1Z5')
            ->assertSee('GST 12%')->assertSee('1 contact')->assertSee('id="companyModal"', false)
            ->assertSee('data-company-id="'.$company->id.'"', false);
    }

    public function test_search_finds_a_company_by_any_detail_and_all_words_must_match(): void
    {
        $this->company(['name' => 'Sunrise Tours', 'gst_number' => '24AAAAA1111A1Z1', 'md_one_name' => 'Meera Joshi']);
        $this->company(['name' => 'Moonlight Events', 'gst_number' => '27BBBBB2222B1Z2']);

        $this->get(route('company.index', ['q' => 'meera']))->assertSee('Sunrise Tours')->assertDontSee('Moonlight Events');
        $this->get(route('company.index', ['q' => '27BBBBB']))->assertSee('Moonlight Events')->assertDontSee('Sunrise Tours');
        $this->get(route('company.index', ['q' => 'sunrise joshi']))->assertSee('Sunrise Tours');
        $this->get(route('company.index', ['q' => 'sunrise moonlight']))->assertSee('No companies match');
    }

    public function test_a_company_can_be_added_edited_and_deleted_and_the_filter_stays(): void
    {
        $this->from(route('company.index', ['q' => 'x']))->post(route('company.store'), ['name' => 'New Co', 'gst_percentage' => 18])
            ->assertRedirect(route('company.index', ['q' => 'x']))->assertSessionHasNoErrors();

        $company = CompanyProfile::sole();
        $this->put(route('company.update', $company), ['name' => 'Renamed Co', 'email' => 'a@b.test'])->assertSessionHasNoErrors();
        $this->assertSame('Renamed Co', $company->fresh()->name);

        $this->delete(route('company.destroy', $company))->assertSessionHas('success');
        $this->assertSame(0, CompanyProfile::count());
    }

    public function test_a_duplicate_name_or_gst_number_is_refused_with_a_message(): void
    {
        $this->company(['name' => 'Taken Co', 'gst_number' => 'GST-1']);

        $this->post(route('company.store'), ['name' => 'Taken Co'])->assertSessionHasErrors('name');
        $this->post(route('company.store'), ['name' => 'Other Co', 'gst_number' => 'GST-1'])->assertSessionHasErrors('gst_number');
        $this->post(route('company.store'), ['name' => 'Rates Co', 'gst_percentage' => 150])->assertSessionHasErrors('gst_percentage');
    }

    public function test_a_company_can_keep_its_own_name_when_edited(): void
    {
        $company = $this->company(['name' => 'Same Co']);

        $this->put(route('company.update', $company), ['name' => 'Same Co', 'country' => 'India'])->assertSessionHasNoErrors();
        $this->assertSame('India', $company->fresh()->country);
    }

    public function test_a_refused_save_reopens_the_pop_up_with_what_was_typed(): void
    {
        $this->from(route('company.index'))->post(route('company.store'), ['_form' => 'company', 'name' => '', 'email' => 'typed@keep.test', 'gst_percentage' => 999])
            ->assertSessionHasErrors();

        $this->get(route('company.index'))->assertSee('"email":"typed@keep.test"', false);
    }
}
