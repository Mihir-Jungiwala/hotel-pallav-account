<?php

namespace App\Support;

use App\Models\AttendanceStatus;
use App\Models\BonusIncentive;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\EmployeeSeparation;
use App\Models\PayrollAdvance;
use App\Models\PayrollCompany;
use App\Models\SalaryProcessing;

/**
 * The payroll menu: the individual pages, grouped for readability only, and
 * the badge on each one. Counts are resolved once per request and only when
 * the menu is actually rendered, so a PDF or a form post never pays for them.
 */
class PayrollNav
{
    /** Section heading => [slug => [route, label, icon]], in the module's order. */
    public static function sections(?PayrollCompany $company = null): array
    {
        $sections = [];
        $company ??= PayrollContext::current();

        foreach (PayrollContext::MODULES as $slug => [$route, $label, $icon, $section]) {
            // The price is Pallav Food's alone; who eats and the bill are for both companies
            if ($slug === 'food-price' && ! $company?->providesFood()) {
                continue;
            }

            if ($slug === 'food-charge' && ! $company?->servesMeals()) {
                continue;
            }

            $sections[$section][$slug] = [$route, $label, $icon];
        }

        return $sections;
    }

    /**
     * Badge counts, each one a number worth acting on rather than a row count:
     * staff still employed, advances not yet recovered, salaries not yet paid.
     *
     * @return array<string, int>
     */
    public static function counts(?PayrollCompany $company = null): array
    {
        static $cache = [];

        $company ??= PayrollContext::current();

        if ($company === null) {
            return [];
        }

        if (isset($cache[$company->id])) {
            return $cache[$company->id];
        }

        $id = $company->id;

        return $cache[$company->id] = [
            'attendance-status' => AttendanceStatus::where('payroll_company_id', $id)->count(),
            'deduction' => Deduction::where('payroll_company_id', $id)->count(),
            'staff' => Employee::where('payroll_company_id', $id)->where('is_active', true)->count(),
            'separation' => EmployeeSeparation::where('payroll_company_id', $id)->whereNull('rejoined_at')->count(),
            'advance' => PayrollAdvance::where('payroll_company_id', $id)->where('is_settled', false)->count(),
            'bonus-incentive' => BonusIncentive::where('payroll_company_id', $id)->count(),
            'salary-slip' => SalaryProcessing::where('payroll_company_id', $id)->count(),
            'salary-payment' => SalaryProcessing::where('payroll_company_id', $id)->where('payment_status', '!=', 'Paid')->count(),
            'food-charge' => $company->servesMeals() ? \App\Models\EmployeeMealPeriod::where('payroll_company_id', $id)
                ->whereDate('starts_on', '<=', today())
                ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()))
                ->distinct('employee_id')->count('employee_id') : 0,
        ];
    }
}
