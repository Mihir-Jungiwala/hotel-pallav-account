<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One stretch of someone taking meals at Pallav Food: from a day, to a day (or
 * still going). A person can have several, one after another. Nothing is ever
 * overwritten: stopping closes a record, starting again opens a new one.
 */
class EmployeeMealPeriod extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(PayrollCompany::class, 'payroll_company_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Whether the meals carry on, with no end date. */
    public function isOngoing(): bool
    {
        return $this->ends_on === null;
    }

    /** Whether it covers a given day. */
    public function covers(Carbon $day): bool
    {
        return $this->starts_on->lessThanOrEqualTo($day)
            && ($this->ends_on === null || $this->ends_on->greaterThanOrEqualTo($day));
    }

    /** "12 Aug 2026 - 05 Sep 2026", or "12 Aug 2026 - ongoing". */
    public function label(): string
    {
        return $this->starts_on->format('d M Y').' - '.($this->ends_on ? $this->ends_on->format('d M Y') : 'ongoing');
    }
}
