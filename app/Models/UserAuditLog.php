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
        'login.blocked' => 'Blocked after failed attempts',
        'login.verified' => 'Signed in with an email code',
        'login.password_only' => 'Signed in with a password',
        'otp.cooldown' => 'Code emails paused',
        'password.reset_by_code' => 'Reset password by email code',
        'two_factor.enabled' => 'Turned on the email code',
        'two_factor.disabled' => 'Turned off the email code',
        'master.set_created' => 'Created a list',
        'master.set_updated' => 'Updated a list',
        'master.set_deleted' => 'Deleted a list',
        'master.item_created' => 'Added an option',
        'master.item_updated' => 'Updated an option',
        'master.item_deleted' => 'Removed an option',
        'force.enabled' => 'Armed force mode',
        'force.disabled' => 'Disarmed force mode',
        'force.override' => 'Forced past a lock',
        'unit.switched' => 'Switched business unit',
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
