<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Single source of truth for who may do what to which account.
 *
 * An account manages the rungs below its own on the ladder, and nothing at or
 * above it. The single SuperAdmin sits on top and changes hands only through
 * a transfer to an active Admin.
 */
class UserHierarchy
{
    /**
     * Roles the actor may assign: every rung below their own, whatever it is
     * called, so a role added on screen can be handed out straight away.
     *
     * @return list<string>
     */
    public function assignableRoles(User $actor): array
    {
        return $this->assignableRoleRecords($actor)->pluck('name')->values()->all();
    }

    /** The same rungs as records, for a screen that wants their look. */
    public function assignableRoleRecords(User $actor): Collection
    {
        if (! $this->canManageUsers($actor)) {
            return collect();
        }

        return Role::ladder()
            ->filter(fn (Role $role) => $role->level < $actor->rank())
            ->values();
    }

    public function canManageUsers(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function canCreateRole(User $actor, string $role): bool
    {
        return in_array($role, $this->assignableRoles($actor), true);
    }

    /** May the actor edit, reset, lock or change status of this account? */
    public function canManage(User $actor, User $target): bool
    {
        if ($actor->is($target) || $target->isSuperAdmin()) {
            return false;
        }

        return $actor->rank() > $target->rank() && $this->canManageUsers($actor);
    }

    public function canDelete(User $actor, User $target): bool
    {
        return $this->canManage($actor, $target);
    }

    /** Only the SuperAdmin can hand the role over, and only to an active Admin. */
    public function canTransferSuperAdminTo(User $actor, User $target): bool
    {
        return $actor->isSuperAdmin()
            && ! $actor->is($target)
            && $target->role === User::ROLE_ADMIN
            && $target->is_active;
    }

    /** Is the account visible in the actor's user list? */
    public function canView(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    /**
     * Records that would be destroyed with the account (cascading foreign
     * keys). Accounts that own any of these must be deactivated instead.
     *
     * @return array<string, int>
     */
    public function blockingRecords(User $target): array
    {
        $tables = [
            'hotel_cash_deposits' => 'Hotel deposits',
            'food_cash_deposits' => 'Food deposits',
            'hotel_cash_withdrawals' => 'Hotel withdrawals',
            'food_cash_withdrawals' => 'Food withdrawals',
            'hotel_misc_expenses' => 'Hotel expenses',
            'food_misc_expenses' => 'Food expenses',
            'staff_advances' => 'Staff advances',
            'shift_handovers' => 'Handovers',
        ];

        $found = [];
        foreach ($tables as $table => $label) {
            $count = \DB::table($table)->where('user_id', $target->id)->count();
            if ($count > 0) {
                $found[$label] = $count;
            }
        }

        return $found;
    }
}
