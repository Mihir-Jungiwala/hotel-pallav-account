<?php

namespace Tests\Feature\Access;

use App\Models\Role;
use App\Models\User;
use App\Models\UserPermission;
use App\Support\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The User Accounts screen: what it shows about one person, and the rare
 * case of giving or refusing something to them alone.
 */
class UserAccessTest extends TestCase
{
    use RefreshDatabase;

    private function role(string $key): Role
    {
        return Role::where('key', $key)->firstOrFail();
    }

    public function test_the_screen_spells_out_where_each_permission_comes_from(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['role_id' => $this->role('SuperAdmin')->id]);
        $editor = User::factory()->editor()->create(['name' => 'Ravi Shah', 'role_id' => $this->role('Editor')->id]);

        $this->actingAs($superAdmin)->get(route('users.index'))->assertOk()
            ->assertSee('Permissions')
            ->assertSee('origin-role', false)          // its own role
            ->assertSee('origin-inherited', false)     // gained from below
            ->assertSee('Inherited from a lower role');
    }

    public function test_a_custom_role_can_be_handed_out_on_the_account_form(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['role_id' => $this->role('SuperAdmin')->id]);
        $this->actingAs($superAdmin);

        $this->post(route('access.store'), ['name' => 'Night Manager', 'level' => 3, 'inherits' => 1]);
        Access::flush();

        $this->get(route('users.index'))->assertOk()->assertSee('Night Manager');

        $this->post(route('users.store'), [
            'name' => 'Asha Patel', 'username' => 'asha', 'email' => 'asha@example.com',
            'role' => 'Night Manager',
        ])->assertSessionHasNoErrors();

        $user = User::where('username', 'asha')->sole();
        $this->assertSame('Night Manager', $user->role);
        $this->assertSame(Role::where('name', 'Night Manager')->value('id'), $user->role_id);
    }

    public function test_one_person_can_be_given_something_and_have_it_put_back(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['role_id' => $this->role('SuperAdmin')->id]);
        $editor = User::factory()->editor()->create(['role_id' => $this->role('Editor')->id]);
        $this->actingAs($superAdmin);

        $this->postJson(route('users.permission', $editor), [
            'permission' => 'revenue.delete', 'state' => 'grant', 'reason' => 'Covering the month end',
        ])->assertOk();

        Access::flush();
        $this->assertTrue($editor->fresh()->can('revenue.delete'));
        $this->assertDatabaseHas('user_permissions', [
            'user_id' => $editor->id, 'permission' => 'revenue.delete', 'granted' => true,
            'reason' => 'Covering the month end', 'granted_by' => $superAdmin->id,
        ]);

        $this->postJson(route('users.permission', $editor), [
            'permission' => 'revenue.delete', 'state' => 'clear',
        ])->assertOk();

        Access::flush();
        $this->assertFalse($editor->fresh()->can('revenue.delete'));
        $this->assertDatabaseMissing('user_permissions', ['user_id' => $editor->id, 'permission' => 'revenue.delete']);
    }

    public function test_taking_something_away_from_one_person_holds_against_the_request(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['role_id' => $this->role('SuperAdmin')->id]);
        $editor = User::factory()->editor()->create(['role_id' => $this->role('Editor')->id]);

        $this->actingAs($superAdmin)->postJson(route('users.permission', $editor), [
            'permission' => 'expense.create', 'state' => 'revoke', 'reason' => 'Under review',
        ])->assertOk();

        Access::flush();

        $this->actingAs($editor->fresh())
            ->from(route('expense.index'))
            ->post(route('expense.hotel-withdrawal.store'), [
                'date' => now()->toDateString(), 'time' => '10:00', 'withdrawer' => 'Front desk', 'amount' => 50,
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, \App\Models\HotelCashWithdrawal::count());
    }

    public function test_nobody_can_change_their_own_access_or_the_superadmins(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['role_id' => $this->role('SuperAdmin')->id]);
        $this->actingAs($superAdmin);

        $this->postJson(route('users.permission', $superAdmin), [
            'permission' => 'revenue.delete', 'state' => 'revoke',
        ])->assertStatus(422);

        $admin = User::factory()->admin()->create(['role_id' => $this->role('Admin')->id]);
        $this->actingAs($admin)->postJson(route('users.permission', $admin), [
            'permission' => 'revenue.delete', 'state' => 'revoke',
        ])->assertStatus(422);
    }

    public function test_the_detail_feed_carries_everything_the_panel_shows(): void
    {
        $superAdmin = User::factory()->superAdmin()->create(['role_id' => $this->role('SuperAdmin')->id]);
        $editor = User::factory()->editor()->create(['name' => 'Ravi Shah', 'role_id' => $this->role('Editor')->id]);

        UserPermission::create(['user_id' => $editor->id, 'permission' => 'revenue.delete', 'granted' => true]);
        Access::flush();

        $data = $this->actingAs($superAdmin)->getJson(route('users.detail', $editor))->assertOk()->json();

        $this->assertSame('Ravi Shah', $data['name']);
        $this->assertSame('Editor', $data['role']);
        $this->assertSame('Active', $data['status']);
        $this->assertSame(1, $data['overrideCount']);
        $this->assertTrue($data['permissions']['revenue.create']['allowed']);
        $this->assertSame('role', $data['permissions']['revenue.create']['origin']);
        $this->assertSame('inherited', $data['permissions']['revenue.view']['origin']);
        $this->assertSame('granted', $data['permissions']['revenue.delete']['origin']);
        $this->assertFalse($data['permissions']['masters.edit']['allowed']);
    }
}
