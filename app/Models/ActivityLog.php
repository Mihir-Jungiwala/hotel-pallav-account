<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'is_active', 'forgot_password_token', 'token_used', 'email_sent_time',
        'activity_type', 'activity_time', 'login_date', 'logout_date', 'login_time', 'logout_time',
        'minutes_logged_in', 'password_change_time', 'password_change_duration',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'token_used' => 'boolean',
        'email_sent_time' => 'datetime',
        'activity_time' => 'datetime',
        'login_date' => 'date',
        'logout_date' => 'date',
        'password_change_time' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
