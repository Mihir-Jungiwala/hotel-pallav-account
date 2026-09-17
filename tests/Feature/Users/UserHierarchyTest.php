<?php

namespace Tests\Feature\Users;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private function newUserPayload(string $role, string $username = 'newbie'): array
    {
        return [
            'name' => 'New Person',
            'username' => $username,
            'email' => $username.'@example.com',
            'role' => $role,
            'password' => 'Strong@Pass1',
            'password_confirmation' => 'Strong@Pass1',
        ];
    }

    /* ---------------------------------------------------------------- access */

    public function test_editors_and_viewers_cannot_open_user_management(): void
    {
        foreach ([User::factory()->editor()->create(), User::factory()->viewer()->create()] as $user) {
            $this->actingAs($user)->get(route('users.index'))->assertForbidden();
        }
    }

    public function test_admins_and_superadmin_can_open_user_management(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create())->get(route('users.index'))->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get(route('users.index'))->assertOk();
    }

    /* -------------------------------------------------------------- creation */

    public function test_superadmin_can_create_admin_editor_and_viewer(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        foreach (['Admin', 'Editor', 'Viewer'] as $role) {
            $this->post(route('users.store'), $this->newUserPayload($role, strtolower($role).'1'))->assertSessionHasNoErrors();
            $this->assertDatabaseHas('users', ['username' => strtolower($role).'1', 'role' => $role]);
        }
    }

    public function test_nobody_can_create_a_second_superadmin(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->post(route('users.store'), $this->newUserPayload('SuperAdmin'))->assertSessionHasErrors('role');

        $this->assertSame(1, User::where('role', 'SuperAdmin')->count());
    }

    public function test_admin_can_create_editor_and_viewer_but_not_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('users.store'), $this->newUserPayload('Editor', 'ed1'))->assertSessionHasNoErrors();
        $this->post(route('users.store'), $this->newUserPayload('Viewer', 'vw1'))->assertSessionHasNoErrors();
        $this->post(route('users.store'), $this->newUserPayload('Admin', 'ad1'))->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['username' => 'ad1']);
    }

    public function test_usernames_must_be_unique(): void
    {
        User::factory()->create(['username' => 'taken']);
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->post(route('users.store'), $this->newUserPayload('Editor', 'taken'))->assertSessionHasErrors('username');
    }

    public function test_new_accounts_must_change_password_by_default(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $this->post(route('users.store'), $this->newUserPayload('Editor', 'fresh'));

        $this->assertTrue(User::where('username', 'fresh')->first()->must_change_password);
    }

    /* ---------------------------------------------------------- management */

    public function test_admin_can_edit_and_delete_editors_and_viewers(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();
        $viewer = User::factory()->viewer()->create();

        $this->actingAs($admin)->put(route('users.update', $editor), [
            'name' => 'Renamed', 'username' => $editor->username, 'role' => 'Viewer',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Viewer', $editor->fresh()->role);

        $this->delete(route('users.destroy', $viewer))->assertSessionHasNoErrors();
        $this->assertModelMissing($viewer);
    }

    public function test_admin_cannot_touch_another_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $this->actingAs($admin);

        $this->put(route('users.update', $other), ['name' => 'X', 'username' => $other->username, 'role' => 'Viewer'])->assertForbidden();
        $this->post(route('users.toggle-active', $other))->assertForbidden();
        $this->delete(route('users.destroy', $other));

        $this->assertModelExists($other);
        $this->assertSame('Admin', $other->fresh()->role);
    }

    public function test_admin_cannot_promote_anyone_to_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();

        $this->actingAs($admin)->put(route('users.update', $editor), [
            'name' => $editor->name, 'username' => $editor->username, 'role' => 'Admin',
        ])->assertSessionHasErrors('role');

        $this->assertSame('Editor', $editor->fresh()->role);
    }

    public function test_superadmin_cannot_be_deleted_deactivated_or_edited_by_anyone(): void
    {
        $super = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin);
        $this->delete(route('users.destroy', $super));
        $this->post(route('users.toggle-active', $super))->assertForbidden();
        $this->put(route('users.update', $super), ['name' => 'X', 'username' => $super->username, 'role' => 'Viewer'])->assertForbidden();

        // Not even by the SuperAdmin themself through user management
        $this->actingAs($super);
        $this->delete(route('users.destroy', $super))->assertSessionHas('error');

        $super->refresh();
        $this->assertModelExists($super);
        $this->assertTrue($super->is_active);
        $this->assertSame('SuperAdmin', $super->role);
    }

    public function test_users_cannot_manage_their_own_account_through_user_management(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->put(route('users.update', $admin), ['name' => 'X', 'username' => $admin->username, 'role' => 'Admin'])
            ->assertForbidden();
    }

    public function test_accounts_with_entered_records_are_not_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create();

        \DB::table('hotel_cash_deposits')->insert([
            'date' => now()->toDateString(), 'time' => '10:00', 'user_id' => $editor->id,
            'depositor' => 'Desk', 'amount' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($admin)->delete(route('users.destroy', $editor))->assertSessionHas('error');

        $this->assertModelExists($editor);
        $this->assertDatabaseCount('hotel_cash_deposits', 1);
    }

    public function test_reset_password_forces_a_change_and_clears_lock(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->editor()->create(['locked_until' => now()->addMinutes(10)]);

        $this->actingAs($admin)->put(route('users.reset-password', $editor), [
            'password' => 'Temp@Pass99', 'password_confirmation' => 'Temp@Pass99', 'must_change_password' => '1',
        ])->assertSessionHasNoErrors();

        $editor->refresh();
        $this->assertTrue($editor->must_change_password);
        $this->assertFalse($editor->isLocked());
        $this->assertDatabaseHas('user_audit_logs', ['action' => 'user.password_reset', 'target_user_id' => $editor->id]);
    }

    /* ------------------------------------------------------------ transfer */

    public function test_superadmin_transfers_role_to_an_admin_and_becomes_admin(): void
    {
        $super = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create(['username' => 'nextowner']);

        $this->actingAs($super)->post(route('users.transfer-superadmin', $admin), [
            'current_password' => UserFactory::PASSWORD,
            'confirm_username' => 'nextowner',
        ])->assertRedirect(route('dashboard'));

        $this->assertSame('Admin', $super->fresh()->role);
        $this->assertSame('SuperAdmin', $admin->fresh()->role);
        $this->assertSame(1, User::where('role', 'SuperAdmin')->count());
    }

    public function test_transfer_requires_the_correct_password(): void
    {
        $super = User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create(['username' => 'nextowner']);

        $this->actingAs($super)->post(route('users.transfer-superadmin', $admin), [
            'current_password' => 'Wrong@Pass1',
            'confirm_username' => 'nextowner',
        ])->assertSessionHasErrors('current_password');

        $this->assertSame('SuperAdmin', $super->fresh()->role);
    }

    public function test_transfer_only_goes_to_an_active_admin(): void
    {
        $super = User::factory()->superAdmin()->create();
        $editor = User::factory()->editor()->create(['username' => 'ed']);
        $inactiveAdmin = User::factory()->admin()->inactive()->create(['username' => 'sleepy']);

        $this->actingAs($super);

        foreach ([[$editor, 'ed'], [$inactiveAdmin, 'sleepy']] as [$target, $name]) {
            $this->post(route('users.transfer-superadmin', $target), [
                'current_password' => UserFactory::PASSWORD, 'confirm_username' => $name,
            ])->assertSessionHas('error');
        }

        $this->assertSame('SuperAdmin', $super->fresh()->role);
    }

    public function test_admin_cannot_initiate_a_transfer(): void
    {
        User::factory()->superAdmin()->create();
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create(['username' => 'other']);

        $this->actingAs($admin)->post(route('users.transfer-superadmin', $other), [
            'current_password' => UserFactory::PASSWORD, 'confirm_username' => 'other',
        ])->assertSessionHas('error');

        $this->assertSame('Admin', $other->fresh()->role);
    }

    public function test_database_refuses_two_superadmins(): void
    {
        User::factory()->superAdmin()->create();

        $this->expectException(QueryException::class);
        User::factory()->superAdmin()->create();
    }
}
