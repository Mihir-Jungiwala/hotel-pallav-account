<?php

namespace App\Support;

use App\Models\PayrollCompany;

/**
 * Resolves the "Current Company" every payroll screen is scoped to.
 *
 * Payroll is one module per company: you pick a company on the Company
 * Listing, and from then on every individual payroll page - staff, attendance,
 * advances, salary, documents, reports - reads and writes only that company's
 * records. The choice lives in the session so it survives moving between
 * those pages without being re-asked, and PayrollScope enforces it on every
 * record the server touches.
 */
class PayrollContext
{
    /** No operating company is selected; only the Company Listing is available. */
    public const SENTINEL = 'company';

    private const SESSION_KEY = 'payroll.current_company';

    /**
     * The individual payroll pages, in the module's mandated order. Each one
     * is a separate route, a separate page and a separate menu entry - they
     * share only the selected company.
     *
     * slug => [route name, menu label, icon, section]
     */
    public const MODULES = [
        'dashboard' => ['payroll.dashboard.index', 'Dashboard', 'bi-grid-1x2', 'Company'],
        'attendance' => ['payroll.attendance.index', 'Attendance Management', 'bi-calendar3', 'Operations'],
        'attendance-status' => ['payroll.attendance-status.index', 'Attendance Status', 'bi-palette2', 'Operations'],
        'deduction' => ['payroll.deduction.index', 'Deduction Management', 'bi-dash-circle', 'Operations'],
        'joining-letter' => ['payroll.joining-letter.index', 'Joining Letter', 'bi-file-earmark-text', 'People'],
        'staff' => ['payroll.staff.index', 'Staff Management', 'bi-people', 'People'],
        'advance' => ['payroll.advance.index', 'Advance Management', 'bi-wallet2', 'Money'],
        'bonus-incentive' => ['payroll.bonus-incentive.index', 'Bonus & Incentive', 'bi-gift', 'Money'],
        'salary-update' => ['payroll.salary-update.index', 'Salary Update', 'bi-clock-history', 'Money'],
        'salary-payment' => ['payroll.salary-payment.index', 'Salary Payment', 'bi-credit-card-2-back', 'Money'],
        'food-price' => ['payroll.food-price.index', 'Meal Price', 'bi-tag', 'Money'],
        'food-charge' => ['payroll.food-charge.index', 'Staff Meals', 'bi-cup-hot', 'Money'],
        'experience-letter' => ['payroll.experience-letter.index', 'Experience Letter', 'bi-file-earmark-check', 'People'],
        'separation' => ['payroll.separation.index', 'Resignation', 'bi-box-arrow-right', 'People'],
        'salary-slip' => ['payroll.salary-slip.index', 'Salary Slips', 'bi-receipt', 'Reports'],
        'period-report' => ['payroll.period-report.index', 'Period Report', 'bi-calendar-range', 'Reports'],
        'monthly-report' => ['payroll.monthly-report.index', 'Monthly Report', 'bi-bar-chart', 'Reports'],
    ];

    public static function remember(string $value): void
    {
        session([self::SESSION_KEY => $value]);
    }

    public static function forget(): void
    {
        session()->forget(self::SESSION_KEY);
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

        // A company that was deleted or deactivated while it was open must not
        // keep leaking its records; drop the context and send them back to the
        // listing rather than silently showing stale data.
        if (! $company || ! $company->is_active) {
            self::forget();

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

    /**
     * Companies this user may open. Payroll access is not split by company
     * today, so that is every active one - kept as a single place to narrow
     * later without hunting through the views.
     */
    public static function selectable()
    {
        return PayrollCompany::where('is_active', true)->orderBy('name')->get();
    }
}
