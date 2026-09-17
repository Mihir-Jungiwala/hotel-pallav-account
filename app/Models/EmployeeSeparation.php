<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSeparation extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'resignation_date' => 'date',
        'last_working_date' => 'date',
        'rejoined_at' => 'date',
    ];

    public const TYPES = ['Resignation', 'Termination', 'Retirement', 'End of Contract'];
    public const STATUSES = ['Pending', 'Accepted', 'Relieved'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(PayrollCompany::class, 'payroll_company_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasRejoined(): bool
    {
        return $this->rejoined_at !== null;
    }

    /**
     * An experience letter is only meaningful once the person has actually left.
     */
    public function canIssueExperienceLetter(): bool
    {
        return $this->status === 'Relieved' && ! $this->hasRejoined();
    }

    public function tenureLabel(): string
    {
        $from = optional($this->employee)->joining_date;

        if (! $from) {
            return '—';
        }

        return $from->diffForHumans($this->last_working_date, [
            'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE,
            'parts' => 2,
        ]);
    }
}
