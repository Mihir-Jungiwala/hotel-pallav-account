<?php

namespace App\Services;

use App\Models\User;

/**
 * Single source of truth for who may do what to which account.
 *
 *   SuperAdmin → manages Admin, Editor, Viewer. Exactly one exists; it can only
 *                change hands through a transfer to an active Admin.
 *   Admin      → manages Editor and Viewer.
 *   Editor     → no user management.
 *   Viewer     → no user management.
 */
class UserHierarchy
{
    /** Roles the actor may assign when creating or editing an account. */
    public function assignableRoles(User $actor): array
    {
        return match ($actor->role) {
            User::ROLE_SUPERADMIN => [User::ROLE_ADMIN, User::ROLE_EDITOR, User::ROLE_VIEWER],
            User::ROLE_ADMIN => [User::ROLE_EDITOR, User::ROLE_VIEWER],
            default => [],
        };
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
            'shift_handovers' => 'Shift handovers',
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
