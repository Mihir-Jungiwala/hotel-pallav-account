<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeSeparation;
use App\Models\FoodChargeRate;
use App\Models\PayrollCompany;
use Illuminate\Support\Carbon;

/**
 * What Hotel Pallav owes Pallav Food for its staff's meals in a month.
 *
 * The staff do not pay; the owner does, so none of this touches salary. Pallav
 * Food fixes one monthly amount per employee. It is counted by calendar days:
 * every day counts, absent days and week-offs included. Only days outside
 * someone's service count for nothing - before they joined, or after their
 * last working day - so a full month is the full amount and a mid-month joiner
 * pays from their joining date.
 *
 * It reads the staff list rather than processed salary, so the month still
 * running shows what is building up before any salary is generated.
 */
class FoodCharges
{
    /** The monthly amount in force for a month: the latest one that had started by then. */
    public static function rateFor(PayrollCompany $company, Carbon $month): ?float
    {
        $rate = FoodChargeRate::where('payroll_company_id', $company->id)
            ->whereDate('effective_from', '<=', $month->copy()->endOfMonth())
            ->orderByDesc('effective_from')->orderByDesc('id')
            ->first();

        return $rate ? (float) $rate->monthly_amount : null;
    }

    /** The rate in force today, for the page and the form. */
    public static function currentRate(PayrollCompany $company): ?float
    {
        return self::rateFor($company, now());
    }

    /**
     * Days of the month someone is charged for: every day they were on the
     * payroll, from their joining date to their last working day.
     */
    public static function daysCounted(Carbon $monthStart, ?Carbon $joined, ?Carbon $lastWorkingDay = null): int
    {
        $from = $monthStart->copy()->startOfMonth();
        $to = $monthStart->copy()->endOfMonth()->startOfDay();

        if ($joined !== null && $joined->greaterThan($from)) {
            $from = $joined->copy()->startOfDay();
        }

        if ($lastWorkingDay !== null && $lastWorkingDay->lessThan($to)) {
            $to = $lastWorkingDay->copy()->startOfDay();
        }

        return $to->greaterThanOrEqualTo($from) ? $from->diffInDays($to) + 1 : 0;
    }

    /** Staff who are charged, whether or not the month has any salary processed yet. */
    public static function chargedStaff(PayrollCompany $company)
    {
        return Employee::where('payroll_company_id', $company->id)
            ->where('eats_at_pallav_food', true)
            ->orderBy('name')->get();
    }

    /**
     * The statement for one month, or null when this company pays no food
     * charge, no amount is set yet, or nobody was charged that month.
     *
     * @return array{payee: string, month: Carbon, rate: float, daysInMonth: int, rows: array<int, array<string, mixed>>, total: float}|null
     */
    public static function statement(PayrollCompany $company, int $year, int $month): ?array
    {
        if (! $company->paysFoodCharges()) {
            return null;
        }

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $rate = self::rateFor($company, $start);

        if ($rate === null) {
            return null;
        }

        $staff = self::chargedStaff($company);

        // Anyone relieved and not since rejoined stops being charged after their last day
        $lastDays = EmployeeSeparation::whereIn('employee_id', $staff->pluck('id'))
            ->whereNull('rejoined_at')->where('status', 'Relieved')
            ->pluck('last_working_date', 'employee_id');

        $rows = $staff->map(function (Employee $employee) use ($start, $rate, $lastDays) {
            $left = ($lastDays[$employee->id] ?? null) ? Carbon::parse($lastDays[$employee->id]) : null;
            $days = self::daysCounted($start, $employee->joining_date, $left);

            return [
                'name' => $employee->name,
                'code' => $employee->employee_code,
                'designation' => $employee->designation,
                'days' => $days,
                'amount' => round($rate * $days / $start->daysInMonth, 2),
                // Why a part month, so nobody has to work it out from the dates
                'note' => self::note($start, $employee->joining_date, $left),
            ];
        })
            ->filter(fn ($row) => $row['days'] > 0)
            ->values()->all();

        if ($rows === []) {
            return null;
        }

        return [
            'payee' => PayrollCompany::FOOD_PAYEE,
            'month' => $start,
            'rate' => $rate,
            'daysInMonth' => $start->daysInMonth,
            'rows' => $rows,
            'total' => round(array_sum(array_column($rows, 'amount')), 2),
        ];
    }

    private static function note(Carbon $monthStart, ?Carbon $joined, ?Carbon $left): ?string
    {
        $parts = [];

        if ($joined !== null && $joined->isSameMonth($monthStart) && $joined->isSameYear($monthStart)) {
            $parts[] = 'joined '.$joined->format('j M');
        }

        if ($left !== null && $left->isSameMonth($monthStart) && $left->isSameYear($monthStart)) {
            $parts[] = 'left '.$left->format('j M');
        }

        return $parts ? implode(', ', $parts) : null;
    }
}
