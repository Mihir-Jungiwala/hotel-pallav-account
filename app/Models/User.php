<?php

namespace App\Models;

use App\Support\Access;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    public const ROLE_SUPERADMIN = 'SuperAdmin';
    public const ROLE_ADMIN = 'Admin';
    public const ROLE_EDITOR = 'Editor';
    public const ROLE_VIEWER = 'Viewer';

    /** Higher rank outranks lower. */
    public const RANKS = [
        self::ROLE_SUPERADMIN => 4,
        self::ROLE_ADMIN => 3,
        self::ROLE_EDITOR => 2,
        self::ROLE_VIEWER => 1,
    ];

    public const ROLE_DESCRIPTIONS = [
        self::ROLE_SUPERADMIN => 'Owns the system. Manages every account, including Admins. Only one can exist.',
        self::ROLE_ADMIN => 'Runs day-to-day operations. Manages Editors and Viewers and can delete records.',
        self::ROLE_EDITOR => 'Creates and updates records. Cannot delete or manage users.',
        self::ROLE_VIEWER => 'Read-only access to every screen and report.',
    ];

    /** Defaults a brand new account carries before it reaches the database. */
    protected $attributes = [
        'is_active' => true,
        'two_factor_enabled' => false,
        'must_change_password' => false,
        'failed_login_attempts' => 0,
        'lock_level' => 0,
        'otp_attempts' => 0,
        'otp_sends' => 0,
        'otp_cooldown_level' => 0,
    ];

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'role',
        'role_id',
        'is_active',
        'must_change_password',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'password_changed_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'blocked_at' => 'datetime',
            'otp_cooldown_until' => 'datetime',
            'otp_sent_at' => 'datetime',
            'session_started_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'failed_login_attempts' => 'integer',
            'lock_level' => 'integer',
            'otp_attempts' => 'integer',
            'otp_sends' => 'integer',
            'otp_cooldown_level' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
    }

    /** A closed account: the entries it recorded stay, the sign-in does not. */
    public function isDeleted(): bool
    {
        return $this->trashed();
    }

    /** How the name reads on an entry once the account has been closed. */
    public function displayName(): string
    {
        return $this->trashed() ? $this->name.' (deleted)' : $this->name;
    }

    public function displayUsername(): string
    {
        return $this->trashed() ? '@'.$this->username.' (deleted)' : '@'.$this->username;
    }

    /** The rung this account stands on. */
    public function roleRecord(): ?Role
    {
        return $this->role_id
            ? Role::ladder()->firstWhere('id', $this->role_id)
            : Role::byKey($this->role);
    }

    public function extraPermissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    public function rank(): int
    {
        return $this->roleRecord()?->level ?? self::RANKS[$this->role] ?? 0;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    /**
     * May this account do that? The role decides, then anything given to or
     * taken from this person alone has the last word.
     */
    public function can($permission, $arguments = []): bool
    {
        if (! is_string($permission) || ! str_contains($permission, '.')) {
            return parent::can($permission, $arguments);
        }

        return Access::allows($this, $permission);
    }

    /** @param  list<string>|string  $abilities */
    public function canAny($abilities, $arguments = []): bool
    {
        foreach ((array) $abilities as $permission) {
            if ($this->can($permission, $arguments)) {
                return true;
            }
        }

        return false;
    }

    /** Runs the user management screens. */
    public function isAdmin(): bool
    {
        return $this->can('users.view');
    }

    public function isViewer(): bool
    {
        return ! $this->canWrite();
    }

    /** Allowed to record or correct anything at all. */
    public function canWrite(): bool
    {
        return Access::writes($this);
    }

    /** Allowed to delete anything at all. */
    public function canDelete(): bool
    {
        return Access::deletes($this);
    }

    /**
     * Blocked after five wrong passwords or codes. There is no timer: an
     * administrator unblocks it, or the owner resets the password by email.
     */
    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    /** Blocked, or inside a temporary lock set by an administrator. */
    public function isLocked(): bool
    {
        return $this->isBlocked()
            || ($this->locked_until !== null && $this->locked_until->isFuture());
    }

    /** True while the account has to wait before another code can be emailed. */
    public function isWaitingForCode(): bool
    {
        return $this->otp_cooldown_until !== null && $this->otp_cooldown_until->isFuture();
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))
            ->filter()->take(2)
            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
            ->implode('');
    }

    /**
     * Ownership guard for operational records: SuperAdmin and Admin may manage
     * any record (Admin never a SuperAdmin's), everyone else only their own.
     */
    public function canManage(?User $owner): bool
    {
        if ($owner === null) {
            return $this->isAdmin();
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->isAdmin()) {
            return ! $owner->isSuperAdmin();
        }

        return $this->id === $owner->id;
    }
}
