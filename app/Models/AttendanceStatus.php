<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceStatus extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public const ALLOWED_PERCENTAGES = [0, 25, 50, 75, 100];

    public function company(): BelongsTo
    {
        return $this->belongsTo(PayrollCompany::class, 'payroll_company_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(AttendanceEntry::class);
    }

    public function isPaid(): bool
    {
        return $this->status_type === 'Paid';
    }
}
