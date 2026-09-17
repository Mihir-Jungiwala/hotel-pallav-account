<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

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

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'role',
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
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rank(): int
    {
        return self::RANKS[$this->role] ?? 0;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    /** Admin or above. */
    public function isAdmin(): bool
    {
        return $this->rank() >= self::RANKS[self::ROLE_ADMIN];
    }

    public function isViewer(): bool
    {
        return $this->role === self::ROLE_VIEWER;
    }

    /** Editor or above: allowed to create and update records. */
    public function canWrite(): bool
    {
        return $this->rank() >= self::RANKS[self::ROLE_EDITOR];
    }

    /** Admin or above: allowed to delete records. */
    public function canDelete(): bool
    {
        return $this->isAdmin();
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
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
