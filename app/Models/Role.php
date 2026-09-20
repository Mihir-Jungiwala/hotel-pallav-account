<?php

namespace App\Models;

use App\Support\Permissions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A rung on the ladder. Everything below a role is inherited by it, so the
 * order of the ladder is itself a decision about access.
 */
class Role extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['inherits' => 'boolean', 'is_system' => 'boolean', 'level' => 'integer'];

    private static ?Collection $ladder = null;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /** Every role, highest rung first. Read once per request. */
    public static function ladder(): Collection
    {
        return self::$ladder ??= self::with('permissions')->orderByDesc('level')->orderBy('name')->get();
    }

    public static function byKey(?string $key): ?self
    {
        if ($key === null) {
            return null;
        }

        return self::ladder()->first(fn (self $role) => $role->key === $key || $role->name === $key);
    }

    public static function flush(): void
    {
        self::$ladder = null;
    }

    /** The permissions ticked on this role itself. @return list<string> */
    public function ownPermissions(): array
    {
        return $this->permissions->pluck('permission')->values()->all();
    }

    /** The rungs below this one, nearest first. */
    public function below(): Collection
    {
        return self::ladder()->filter(fn (self $role) => $role->level < $this->level)->values();
    }

    /**
     * Everything this role can do: its own permissions plus, while it
     * inherits, everything every rung below it can do.
     *
     * @return list<string>
     */
    public function effectivePermissions(): array
    {
        $permissions = $this->ownPermissions();

        if ($this->inherits) {
            foreach ($this->below() as $lower) {
                $permissions = array_merge($permissions, $lower->ownPermissions());
                if (! $lower->inherits) {
                    break; // that rung does not pass anything further up
                }
            }
        }

        return array_values(array_unique($permissions));
    }

    /** Which rung a permission came from: this role, or one below. */
    public function sourceOf(string $permission): ?self
    {
        if (in_array($permission, $this->ownPermissions(), true)) {
            return $this;
        }

        if (! $this->inherits) {
            return null;
        }

        foreach ($this->below() as $lower) {
            if (in_array($permission, $lower->ownPermissions(), true)) {
                return $lower;
            }
            if (! $lower->inherits) {
                break;
            }
        }

        return null;
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->effectivePermissions(), true);
    }

    /** How risky this role is, for the badge on its card. */
    public function sensitiveCount(): int
    {
        return count(array_filter($this->effectivePermissions(), Permissions::isSensitive(...)));
    }

    public function isSuperAdmin(): bool
    {
        return $this->key === User::ROLE_SUPERADMIN;
    }

    /** A role nobody can be left without: the top rung must keep the keys. */
    public function protectsTheSystem(): bool
    {
        return $this->isSuperAdmin();
    }
}
