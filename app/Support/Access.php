<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;
use App\Models\UserPermission;
use Illuminate\Support\Facades\Schema;

/**
 * Works out what one account may actually do, and says where each answer
 * came from, so a screen can explain itself rather than just allowing or
 * refusing.
 *
 * Three layers, in order:
 *   1. the role's own permissions,
 *   2. everything inherited from the rungs below it,
 *   3. anything given to, or taken from, this person alone.
 */
class Access
{
    /**
     * Worked out once per account, held against the object rather than its id:
     * two different accounts can carry the same id across a test run, and a
     * stale answer there would hand one person another's access.
     */
    private static ?\WeakMap $cache = null;

    private static ?bool $installed = null;

    private static function cache(): \WeakMap
    {
        return self::$cache ??= new \WeakMap();
    }

    /** Until the roles table exists (fresh install, mid-migration), fall back. */
    public static function installed(): bool
    {
        return self::$installed ??= Schema::hasTable('roles') && Role::ladder()->isNotEmpty();
    }

    public static function flush(): void
    {
        self::$cache = new \WeakMap();
        self::$installed = null;
        Role::flush();
    }

    public static function allows(User $user, string $permission): bool
    {
        return in_array($permission, self::effective($user), true);
    }

    /** Everything this account may do, after all three layers. @return list<string> */
    public static function effective(User $user): array
    {
        $cache = self::cache();

        if (isset($cache[$user])) {
            return $cache[$user];
        }

        if (! self::installed()) {
            return $cache[$user] = self::fallback($user);
        }

        $permissions = $user->roleRecord()?->effectivePermissions() ?? [];

        foreach (self::overrides($user) as $permission => $granted) {
            $permissions = $granted
                ? array_merge($permissions, [$permission])
                : array_diff($permissions, [$permission]);
        }

        return $cache[$user] = array_values(array_unique($permissions));
    }

    /**
     * What this person alone has been given or had taken away.
     *
     * @return array<string, bool> permission => granted
     */
    public static function overrides(User $user): array
    {
        if (! $user->exists || ! self::installed()) {
            return [];
        }

        return UserPermission::where('user_id', $user->getKey())
            ->pluck('granted', 'permission')
            ->map(fn ($granted) => (bool) $granted)
            ->all();
    }

    /**
     * Where a permission comes from, for the "why can they do this?" line.
     *
     * @return array{allowed: bool, origin: string, role: ?Role, reason: ?string}
     */
    public static function explain(User $user, string $permission): array
    {
        $overrides = self::overrides($user);
        $role = $user->roleRecord();

        if (array_key_exists($permission, $overrides)) {
            $row = UserPermission::where('user_id', $user->getKey())->where('permission', $permission)->first();

            return [
                'allowed' => $overrides[$permission],
                'origin' => $overrides[$permission] ? 'granted' : 'revoked',
                'role' => $role,
                'reason' => $row?->reason,
            ];
        }

        $source = $role?->sourceOf($permission);

        return [
            'allowed' => (bool) $source,
            'origin' => match (true) {
                ! $source => 'none',
                $source->is($role) => 'role',
                default => 'inherited',
            },
            'role' => $source,
            'reason' => null,
        ];
    }

    /** Anything at all this account can add or change. */
    public static function writes(User $user): bool
    {
        foreach (self::effective($user) as $permission) {
            if (str_ends_with($permission, '.create') || str_ends_with($permission, '.edit')) {
                return true;
            }
        }

        return false;
    }

    /** Anything at all this account can delete. */
    public static function deletes(User $user): bool
    {
        foreach (self::effective($user) as $permission) {
            if (str_ends_with($permission, '.delete')) {
                return true;
            }
        }

        return false;
    }

    /**
     * The old four-rank behaviour, for when the roles table is not there yet
     * (a fresh database part-way through migrating, or a unit test that never
     * touches it).
     *
     * @return list<string>
     */
    private static function fallback(User $user): array
    {
        $rank = User::RANKS[$user->role] ?? 0;
        $permissions = [];

        foreach (Permissions::modules() as $key => $module) {
            foreach ($module['actions'] as $action) {
                $needed = match ($action) {
                    'view', 'export' => 1,
                    'create', 'edit' => 2,
                    default => 3,
                };
                $admin = in_array($key, ['users', 'access', 'masters'], true);
                $needed = $admin ? ($key === 'masters' ? 4 : 3) : $needed;

                if ($rank >= $needed) {
                    $permissions[] = "{$key}.{$action}";
                }
            }
        }

        if ($rank >= 3) {
            $permissions = array_merge($permissions, ['users.password', 'users.activity', 'system.backdate']);
        }
        if ($rank >= 4) {
            $permissions[] = 'system.force';
        }

        return array_values(array_unique($permissions));
    }
}
