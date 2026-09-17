<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
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
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->name === 'SuperAdmin' || $this->role === 'SuperAdmin';
    }

    public function isAdmin(): bool
    {
        return $this->isSuperAdmin() || $this->role === 'Admin';
    }

    /**
     * Ownership/role guard mirrored from the Django app: only SuperAdmin, Admin,
     * or the record's own creator may update/delete it, and Admin may never
     * touch a SuperAdmin-owned record.
     */
    public function canManage(User $owner): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->isAdmin()) {
            return ! $owner->isSuperAdmin();
        }

        return $this->id === $owner->id;
    }
}
