<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExperienceLetter extends Model
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
        '{JOINING_DATE}',
        '{LAST_WORKING_DATE}',
        '{DURATION}',
        '{CURRENT_DATE}',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(PayrollCompany::class, 'payroll_company_id');
    }

    public function renderFor(EmployeeSeparation $separation, string $text): string
    {
        $employee = $separation->employee;

        return strtr($text, [
            '{EMPLOYEE_NAME}' => $employee->name,
            '{DESIGNATION}' => (string) $employee->designation,
            '{DEPARTMENT}' => (string) $employee->department,
            '{JOINING_DATE}' => optional($employee->joining_date)->format('d M Y') ?? '',
            '{LAST_WORKING_DATE}' => $separation->last_working_date->format('d M Y'),
            '{DURATION}' => $separation->tenureLabel(),
            '{CURRENT_DATE}' => now()->format('d M Y'),
        ]);
    }
}
