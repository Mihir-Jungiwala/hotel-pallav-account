<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollCompany extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function attendanceStatuses(): HasMany
    {
        return $this->hasMany(AttendanceStatus::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(Deduction::class);
    }

    public function joiningLetter(): HasOne
    {
        return $this->hasOne(JoiningLetter::class);
    }

    public function attendanceMonths(): HasMany
    {
        return $this->hasMany(AttendanceMonth::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(PayrollAdvance::class);
    }

    public function bonusIncentives(): HasMany
    {
        return $this->hasMany(BonusIncentive::class);
    }

    public function salaryProcessings(): HasMany
    {
        return $this->hasMany(SalaryProcessing::class);
    }

    /**
     * A company holding any payroll data may only be deactivated, never deleted.
     */
    public function hasPayrollData(): bool
    {
        return $this->employees()->exists()
            || $this->attendanceStatuses()->exists()
            || $this->deductions()->exists()
            || $this->joiningLetter()->exists()
            || $this->attendanceMonths()->exists()
            || $this->advances()->exists()
            || $this->bonusIncentives()->exists()
            || $this->salaryProcessings()->exists();
    }
}
