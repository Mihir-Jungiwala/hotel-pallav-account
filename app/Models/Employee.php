<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'joining_date' => 'date',
        'date_of_birth' => 'date',
        'salary' => 'decimal:2',
        'daily_working_hours' => 'decimal:2',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(PayrollCompany::class, 'payroll_company_id');
    }

    /** "+91 98765 43210": the stored country code and number, as they would be dialled. */
    public function contactDisplay(): string
    {
        return \App\Support\PhoneCountries::format($this->contact_country, $this->contact_number);
    }

    public function emergencyContactDisplay(): string
    {
        return \App\Support\PhoneCountries::format($this->emergency_contact_country, $this->emergency_contact_number);
    }

    public function idProofType(): BelongsTo
    {
        return $this->belongsTo(IdProofType::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(EmployeeDeduction::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(PayrollAdvance::class);
    }

    public function bonusIncentives(): HasMany
    {
        return $this->hasMany(BonusIncentive::class);
    }

    public function attendanceEntries(): HasMany
    {
        return $this->hasMany(AttendanceEntry::class);
    }

    public function salaryProcessings(): HasMany
    {
        return $this->hasMany(SalaryProcessing::class);
    }

    public function updateHistories(): HasMany
    {
        return $this->hasMany(SalaryUpdateHistory::class);
    }

    /**
     * Employees referenced by any downstream payroll module may not be deleted.
     */
    public function blockingDependency(): ?string
    {
        if ($this->attendanceEntries()->exists()) {
            return 'Attendance Management';
        }
        if ($this->advances()->exists()) {
            return 'Advance Management';
        }
        if ($this->bonusIncentives()->exists()) {
            return 'Bonus & Incentive Management';
        }
        if ($this->salaryProcessings()->exists()) {
            return 'Salary Processing';
        }
        if (StaffAdvance::where('employee_id', $this->id)->exists()) {
            return 'Staff Advance Salaries';
        }

        return null;
    }
}
