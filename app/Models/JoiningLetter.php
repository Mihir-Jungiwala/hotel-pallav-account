<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JoiningLetter extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'use_company_signatory' => 'boolean',
    ];

    public const PLACEHOLDERS = [
        '{EMPLOYEE_NAME}',
        '{DESIGNATION}',
        '{DEPARTMENT}',
        '{RESPONSIBILITIES}',
        '{JOINING_DATE}',
        '{CURRENT_DATE}',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(PayrollCompany::class, 'payroll_company_id');
    }

    public function renderFor(Employee $employee, string $text): string
    {
        return strtr($text, [
            '{EMPLOYEE_NAME}' => $employee->name,
            '{DESIGNATION}' => (string) $employee->designation,
            '{DEPARTMENT}' => (string) $employee->department,
            '{RESPONSIBILITIES}' => (string) $employee->responsibilities,
            '{JOINING_DATE}' => optional($employee->joining_date)->format('d-m-Y') ?? '',
            '{CURRENT_DATE}' => now()->format('d-m-Y'),
        ]);
    }
}
