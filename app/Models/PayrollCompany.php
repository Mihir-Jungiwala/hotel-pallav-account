<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollCompany extends Model
{
    protected $guarded = ['id'];

    /**
     * Payroll runs for exactly these two companies. They are fixed: nobody adds,
     * renames, deactivates or deletes one. Only their details (address, logo,
     * signatory and so on, which print on letters and slips) can be edited.
     */
    public const FIXED = [
        ['name' => 'Hotel Pallav', 'code' => 'HP01'],
        ['name' => 'Pallav Food', 'code' => 'PF01'],
    ];

    /** Creates either company if it is missing. Safe to call any number of times. */
    public static function ensureFixed(): void
    {
        foreach (self::FIXED as $company) {
            static::firstOrCreate(['code' => $company['code']], ['name' => $company['name'], 'is_active' => true]);
        }
    }

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
