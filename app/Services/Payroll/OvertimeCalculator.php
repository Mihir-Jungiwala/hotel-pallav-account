<?php

namespace App\Services\Payroll;

use App\Models\Employee;

/**
 * Backend-only. No UI of its own - the result is folded into Salary Processing.
 *
 *   Monthly Salary / Total Days in Month = Daily Salary
 *   Daily Salary / Employee Working Hours = Hourly Rate
 *   Hourly Rate * Overtime Hours = Overtime Amount
 */
class OvertimeCalculator
{
    public function hourlyRate(Employee $employee, int $daysInMonth): float
    {
        if ($daysInMonth <= 0) {
            return 0.0;
        }

        $workingHours = (float) $employee->daily_working_hours;

        if ($workingHours <= 0) {
            return 0.0;
        }

        $dailySalary = (float) $employee->salary / $daysInMonth;

        return $dailySalary / $workingHours;
    }

    public function amount(Employee $employee, int $daysInMonth, float $overtimeHours): float
    {
        return round($this->hourlyRate($employee, $daysInMonth) * $overtimeHours, 2);
    }
}
