<?php

namespace Tests\Feature\Access;

use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserPermission;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The ladder of roles, what each rung may do, and how that is enforced.
 */
class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $key): Role
    {
        return Role::where('key', $key)->firstOrFail();
    }

    private function actingAsSuperAdmin(): User
    {
        $user = User::factory()->superAdmin()->create(['role_id' => $this->role('SuperAdmin')->id]);
        $this->actingAs($user);

        return $user;
    }

    /* ------------------------------------------------------------ the shape */

    public function test_the_four_roles_keep_exactly_what_they_could_always_do(): void
    {
        $viewer = $this->role('Viewer');
        $editor = $this->role('Editor');
        $admin = $this->role('Admin');
        $super = $this->role('SuperAdmin');

        // A Viewer reads and prints, and changes nothing
        $this->assertTrue($viewer->can('revenue.view'));
        $this->assertTrue($viewer->can('revenue.export'));
        $this->assertFalse($viewer->can('revenue.create'));
        $this->assertFalse($viewer->can('users.view'));

        // An Editor adds the recording, but never the deleting
        $this->assertTrue($editor->can('revenue.create'));
        $this->assertTrue($editor->can('revenue.view'), 'inherited from Viewer');
        $this->assertFalse($editor->can('revenue.delete'));

        // An Admin deletes and runs the accounts
        $this->assertTrue($admin->can('revenue.delete'));
        $this->assertTrue($admin->can('users.create'));
        $this->assertTrue($admin->can('system.backdate'));
        $this->assertFalse($admin->can('masters.edit'));
        $this->assertFalse($admin->can('access.edit'));

        // The SuperAdmin alone holds the system itself
        $this->assertTrue($super->can('masters.edit'));
        $this->assertTrue($super->can('access.edit'));
        $this->assertTrue($super->can('system.force'));
        $this->assertTrue($super->can('revenue.view'), 'inherited all the way down');
    }

    public function test_a_role_gains_everything_below_it(): void
    {
        $admin = $this->role('Admin');

        $this->assertContains('revenue.view', $admin->effectivePermissions(), 'from Viewer');
        $this->assertContains('revenue.create', $admin->effectivePermissions(), 'from Editor');
        $this->assertSame('Viewer', $admin->sourceOf('revenue.view')->name);
        $this->assertSame('Admin', $admin->sourceOf('revenue.delete')->name);
    }

    public function test_inheritance_can_be_switched_off(): void
    {
        $admin = $this->role('Admin');
        $admin->update(['inherits' => false]);
        Access::flush();

        $admin = $this->role('Admin');
        $this->assertTrue($admin->can('revenue.delete'), 'its own');
        $this->assertFalse($admin->can('revenue.view'), 'no longer inherited');
    }

    /* -------------------------------------------------------- the enforcing */

    public function test_a_viewer_cannot_write_and_is_told_which_screen(): void
    {
        $viewer = User::factory()->viewer()->create(['role_id' => $this->role('Viewer')->id]);

        $this->actingAs($viewer)->get(route('revenue.index'))->assertOk();

        $this->from(route('revenue.index'))
            ->post(route('revenue.hotel.store'), [
                'date' => now()->toDateString(), 'time' => '09:00', 'depositor' => 'Front desk', 'amount' => 100,
            ])
            ->assertRedirect(route('revenue.index'))
            ->assertSessionHas('error');
    }

    public function test_taking_a_screen_away_from_a_role_closes_it_for_everyone_on_it(): void
    {
        $editor = User::factory()->editor()->create(['role_id' => $this->role('Editor')->id]);

        $this->actingAs($editor)->get(route('expense.index'))->assertOk();

        // The Viewer rung owns expense.view, and the Editor inherits it
        RolePermission::where('role_id', $this->role('Viewer')->id)->where('permission', 'expense.view')->delete();
        Access::flush();

        $this->actingAs($editor)->get(route('expense.index'))->assertForbidden();
        $this->actingAs($editor)->get(route('revenue.index'))->assertOk('other screens are untouched');
    }

    public function test_giving_a_role_something_opens_it_without_touching_anyone_else(): void
    {
        $editorRole = $this->role('Editor');
        $editor = User::factory()->editor()->create(['role_id' => $editorRole->id]);
        $viewer = User::factory()->viewer()->create(['role_id' => $this->role('Viewer')->id]);

        $this->actingAs($editor)->get(route('users.index'))->assertForbidden();

        RolePermission::create(['role_id' => $editorRole->id, 'permission' => 'users.view']);
        Access::flush();

        $this->actingAs($editor)->get(route('users.index'))->assertOk();
        $this->actingAs($viewer)->get(route('users.index'))->assertForbidden();
    }

    /* ------------------------------------------------------ one person alone */

    public function test_one_person_can_be_given_or_refused_something_on_their_own(): void
    {
        $editor = User::factory()->editor()->create(['role_id' => $this->role('Editor')->id]);

        UserPermission::create(['user_id' => $editor->id, 'permission' => 'revenue.delete', 'granted' => true]);
        Access::flush();
        $this->assertTrue($editor->fresh()->can('revenue.delete'));

        UserPermission::updateOrCreate(
            ['user_id' => $editor->id, 'permission' => 'revenue.create'],
            ['granted' => false],
        );
        Access::flush();

        $editor = $editor->fresh();
        $this->assertFalse($editor->can('revenue.create'), 'taken away from this person');
        $this->assertTrue($this->role('Editor')->can('revenue.create'), 'the role still has it');
    }

    public function test_where_a_permission_came_from_is_spelled_out(): void
    {
        $admin = User::factory()->admin()->create(['role_id' => $this->role('Admin')->id]);

        $this->assertSame('role', Access::explain($admin, 'users.create')['origin']);
        $this->assertSame('inherited', Access::explain($admin, 'revenue.view')['origin']);
        $this->assertSame('Viewer', Access::explain($admin, 'revenue.view')['role']->name);
        $this->assertSame('none', Access::explain($admin, 'masters.edit')['origin']);

        UserPermission::create(['user_id' => $admin->id, 'permission' => 'masters.edit', 'granted' => true, 'reason' => 'Covering while away']);
        Access::flush();

        $explained = Access::explain($admin->fresh(), 'masters.edit');
        $this->assertSame('granted', $explained['origin']);
        $this->assertSame('Covering while away', $explained['reason']);
    }

    /* ------------------------------------------------------------ the screen */

    public function test_the_screen_shows_the_ladder_and_needs_permission_to_open(): void
    {
        $this->actingAsSuperAdmin();
        $this->get(route('access.index'))->assertOk()
            ->assertSee('Roles and Permissions')
            ->assertSee('SuperAdmin')->assertSee('Viewer')
            ->assertSee('data-permission="revenue.delete"', false);

        $editor = User::factory()->editor()->create(['role_id' => $this->role('Editor')->id]);
        $this->actingAs($editor)->get(route('access.index'))->assertForbidden();
    }

    public function test_an_admin_may_read_the_screen_but_not_change_it(): void
    {
        $admin = User::factory()->admin()->create(['role_id' => $this->role('Admin')->id]);
        $this->actingAs($admin);

        $this->get(route('access.index'))->assertOk()->assertSee('Only an account with');

        $this->postJson(route('access.permission', $this->role('Editor')), [
            'permission' => 'revenue.delete', 'granted' => true,
        ])->assertForbidden();
    }

    public function test_ticking_a_permission_saves_it_and_reports_the_new_counts(): void
    {
        $this->actingAsSuperAdmin();
        $editor = $this->role('Editor');

        $response = $this->postJson(route('access.permission', $editor), [
            'permission' => 'revenue.delete', 'granted' => true,
        ])->assertOk();

        $this->assertContains('revenue.delete', $response->json('own'));
        $this->assertDatabaseHas('role_permissions', ['role_id' => $editor->id, 'permission' => 'revenue.delete']);

        $this->postJson(route('access.permission', $editor), [
            'permission' => 'revenue.delete', 'granted' => false,
        ])->assertOk();

        $this->assertDatabaseMissing('role_permissions', ['role_id' => $editor->id, 'permission' => 'revenue.delete']);
    }

    public function test_nobody_can_hand_out_what_they_do_not_hold(): void
    {
        $adminRole = $this->role('Admin');
        $admin = User::factory()->admin()->create(['role_id' => $adminRole->id]);

        // An Admin who was given the right to edit roles still cannot pass on
        // Master Data, which no rung of theirs holds
        RolePermission::create(['role_id' => $adminRole->id, 'permission' => 'access.edit']);
        Access::flush();

        $this->actingAs($admin)->postJson(route('access.permission', $this->role('Editor')), [
            'permission' => 'masters.edit', 'granted' => true,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('role_permissions', [
            'role_id' => $this->role('Editor')->id, 'permission' => 'masters.edit',
        ]);
    }

    /* ------------------------------------------------------- the ladder moves */

    public function test_the_ladder_can_be_reordered_and_inheritance_follows(): void
    {
        $this->actingAsSuperAdmin();
        $admin = $this->role('Admin');
        $editor = $this->role('Editor');
        $viewer = $this->role('Viewer');
        $super = $this->role('SuperAdmin');

        // Editor is moved above Admin
        $this->postJson(route('access.reorder'), [
            'order' => [$super->id, $editor->id, $admin->id, $viewer->id],
        ])->assertOk();

        Access::flush();
        $this->assertTrue($this->role('Editor')->can('revenue.delete'), 'now sits above Admin');
        $this->assertFalse($this->role('Admin')->can('revenue.create'), 'Editor is no longer below it');
        $this->assertTrue($this->role('Admin')->can('revenue.view'), 'Viewer still is');
    }

    public function test_the_top_rung_cannot_be_dragged_off_the_top(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson(route('access.reorder'), [
            'order' => [$this->role('Admin')->id, $this->role('SuperAdmin')->id, $this->role('Editor')->id, $this->role('Viewer')->id],
        ])->assertStatus(422);

        $this->assertSame(4, $this->role('SuperAdmin')->fresh()->level);
    }

    /* --------------------------------------------------------- custom roles */

    public function test_a_custom_role_can_be_created_used_and_deleted(): void
    {
        $this->actingAsSuperAdmin();

        $this->post(route('access.store'), [
            'name' => 'Front Desk Supervisor',
            'description' => 'Runs the front desk.',
            'icon' => 'bi-headset', 'accent' => '#1D4ED8', 'inherits' => 1, 'level' => 3,
        ])->assertRedirect();

        Access::flush();
        $role = Role::where('name', 'Front Desk Supervisor')->firstOrFail();
        $this->assertFalse($role->is_system);
        $this->assertTrue($role->can('revenue.view'), 'inherits from the rungs below');
        $this->assertFalse($role->can('revenue.delete'), 'nothing of its own yet');

        // Someone on it, so it cannot be deleted from under them
        $user = User::factory()->editor()->create(['role_id' => $role->id, 'role' => $role->name]);
        $this->delete(route('access.destroy', $role))->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['id' => $role->id]);

        $user->update(['role_id' => $this->role('Editor')->id, 'role' => 'Editor']);
        $this->delete(route('access.destroy', $role))->assertSessionHas('success');
        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
    }

    public function test_a_built_in_role_cannot_be_deleted(): void
    {
        $this->actingAsSuperAdmin();

        $this->delete(route('access.destroy', $this->role('Editor')))->assertSessionHas('error');
        $this->assertDatabaseHas('roles', ['key' => 'Editor']);
    }

    /* ------------------------------------------- the powers that are not screens */

    public function test_choosing_the_date_and_time_is_a_permission_like_any_other(): void
    {
        $editor = User::factory()->editor()->create(['role_id' => $this->role('Editor')->id]);
        $this->actingAs($editor);

        $this->post(route('revenue.hotel.store'), [
            'date' => '2026-09-01', 'time' => '03:00', 'depositor' => 'Front desk', 'amount' => 10,
        ])->assertSessionHasNoErrors();
        $this->assertTrue(\App\Models\HotelCashDeposit::sole()->date->isToday(), 'stamped, not chosen');

        RolePermission::create(['role_id' => $this->role('Editor')->id, 'permission' => 'system.backdate']);
        Access::flush();

        $this->post(route('revenue.hotel.store'), [
            'date' => '2026-09-01', 'time' => '03:00', 'depositor' => 'Front desk', 'amount' => 20,
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-09-01', \App\Models\HotelCashDeposit::orderByDesc('id')->first()->date->toDateString());
    }
}
