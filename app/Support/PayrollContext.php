<?php

namespace App\Support;

use App\Models\PayrollCompany;

/**
 * Resolves the "Current Company" the payroll screens are scoped to.
 *
 * The sentinel value "company" means no operating company is selected — the
 * only category available then is Profile (Company Setup).
 */
class PayrollContext
{
    public const SENTINEL = 'company';

    private const SESSION_KEY = 'payroll.current_company';

    /**
     * Categories available once a real company is selected, in the
     * mandatory module order from the specification.
     */
    public const CATEGORIES = [
        'attendance-status' => 'Attendance Status Master',
        'deduction' => 'Deduction Master',
        'joining-letter' => 'Joining Letter',
        'experience-letter' => 'Experience Letter',
        'staff' => 'Staff Management',
        'separation' => 'Resignations & Exits',
        'attendance' => 'Attendance Management',
        'advance' => 'Advance Management',
        'bonus-incentive' => 'Bonus & Incentive Management',
        'salary-update' => 'Salary Update Management',
        'salary-payment' => 'Salary Payments',
        'salary-slip' => 'Employee Salary Slip',
        'period-report' => 'Daily/Period-wise Salary Report',
        'monthly-report' => 'Monthly Salary Report',
    ];

    public static function remember(string $value): void
    {
        session([self::SESSION_KEY => $value]);
    }

    public static function selectedValue(): string
    {
        return (string) session(self::SESSION_KEY, self::SENTINEL);
    }

    public static function current(): ?PayrollCompany
    {
        $value = self::selectedValue();

        if ($value === self::SENTINEL) {
            return null;
        }

        $company = PayrollCompany::find($value);

        if (! $company || ! $company->is_active) {
            session()->forget(self::SESSION_KEY);

            return null;
        }

        return $company;
    }

    /**
     * The company the screens must be scoped to, or abort if none is selected.
     */
    public static function currentOrFail(): PayrollCompany
    {
        $company = self::current();

        abort_if($company === null, 404, 'Select a company first.');

        return $company;
    }

    public static function defaultCategory(): string
    {
        return self::current() === null ? 'profile' : 'attendance-status';
    }
}
