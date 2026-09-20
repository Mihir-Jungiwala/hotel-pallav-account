<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing given to, or taken from, a single person on top of their role.
 * Rare by design: if a lot of these appear, the roles are wrong.
 */
class UserPermission extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['granted' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
