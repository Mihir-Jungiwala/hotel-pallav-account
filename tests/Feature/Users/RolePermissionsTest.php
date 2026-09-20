<?php

namespace Tests\Feature\Users;

use App\Models\CompanyProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_can_read_but_not_write(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->get(route('company.index'))->assertOk();

        $this->post(route('company.store'), ['name' => 'Blocked Co'])->assertSessionHas('error');

        $this->assertDatabaseMissing('company_profiles', ['name' => 'Blocked Co']);
    }

    public function test_viewer_can_still_update_own_profile(): void
    {
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($viewer)->put(route('profile.update'), ['name' => 'Renamed Viewer'])->assertSessionHasNoErrors();

        $this->assertSame('Renamed Viewer', $viewer->fresh()->name);
    }

    public function test_editor_can_create_and_update_but_not_delete(): void
    {
        $editor = User::factory()->editor()->create();
        $this->actingAs($editor);

        $this->post(route('company.store'), ['name' => 'Editor Co', 'contacts' => [['role' => 'Managing Director', 'name' => 'Main Person']]])->assertSessionHasNoErrors();
        $company = CompanyProfile::where('name', 'Editor Co')->firstOrFail();

        $this->delete(route('company.destroy', $company))->assertSessionHas('error');

        $this->assertModelExists($company);
    }

    public function test_admin_can_delete(): void
    {
        $company = CompanyProfile::create(['name' => 'Doomed Co']);

        $this->actingAs(User::factory()->admin()->create())->delete(route('company.destroy', $company));

        $this->assertModelMissing($company);
    }

    public function test_non_admins_do_not_see_user_accounts_in_navigation(): void
    {
        $this->actingAs(User::factory()->editor()->create())
            ->get(route('dashboard'))
            ->assertDontSee(route('users.index'), false);

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertSee(route('users.index'), false);
    }
}
