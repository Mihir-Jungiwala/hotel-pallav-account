<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in a payroll master list. With no company it applies to every
 * company; with one, to that company alone.
 */
class PayrollMasterItem extends Model
{
    protected $fillable = ['payroll_company_id', 'list', 'label', 'value', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(PayrollCompany::class, 'payroll_company_id');
    }

    public function isGlobal(): bool
    {
        return $this->payroll_company_id === null;
    }
}
