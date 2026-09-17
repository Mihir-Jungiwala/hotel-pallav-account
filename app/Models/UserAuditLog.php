<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAuditLog extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'details' => 'array',
        'created_at' => 'datetime',
    ];

    public const LABELS = [
        'user.created' => 'Created account',
        'user.updated' => 'Updated account',
        'user.deleted' => 'Deleted account',
        'user.activated' => 'Activated account',
        'user.deactivated' => 'Deactivated account',
        'user.unlocked' => 'Unlocked account',
        'user.password_reset' => 'Reset password',
        'superadmin.transferred' => 'Transferred SuperAdmin',
        'profile.updated' => 'Updated own profile',
        'profile.password_changed' => 'Changed own password',
        'login.locked' => 'Locked after failed logins',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function label(): string
    {
        return self::LABELS[$this->action] ?? $this->action;
    }

    public static function record(string $action, ?User $target = null, array $details = [], ?User $actor = null): void
    {
        static::create([
            'actor_id' => ($actor ?? auth()->user())?->id,
            'target_user_id' => $target?->id,
            'target_username' => $target?->username,
            'action' => $action,
            'details' => $details ?: null,
            'ip_address' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
