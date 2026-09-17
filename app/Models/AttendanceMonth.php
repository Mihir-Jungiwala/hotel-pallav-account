<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class AttendanceMonth extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'salary_generated_at' => 'datetime',
        'unlocked_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(PayrollCompany::class, 'payroll_company_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(AttendanceEntry::class);
    }

    public function startDate(): Carbon
    {
        return Carbon::create($this->year, $this->month, 1)->startOfDay();
    }

    public function daysInMonth(): int
    {
        return $this->startDate()->daysInMonth;
    }

    public function isTemporarilyUnlocked(): bool
    {
        return $this->is_locked === false && $this->salary_generated_at !== null;
    }

    public function isEditable(): bool
    {
        return ! $this->is_locked;
    }

    /**
     * An admin unlock only survives the session that opened it. If the session
     * ended before Re-Generate Salary ran, the month returns to its locked state.
     */
    public function revertStaleUnlock(?string $currentSessionId): void
    {
        if (! $this->isTemporarilyUnlocked() || $this->unlock_session_id === null) {
            return;
        }

        if ($this->unlock_session_id !== $currentSessionId) {
            $this->forceFill([
                'is_locked' => true,
                'unlocked_by' => null,
                'unlocked_at' => null,
                'unlock_session_id' => null,
            ])->save();
        }
    }

    /**
     * The month can only be completed once it is over.
     */
    public function isComplete(): bool
    {
        return $this->startDate()->endOfMonth()->isPast();
    }
}
