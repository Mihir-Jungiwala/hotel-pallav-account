<?php

namespace App\Support;

use App\Models\FoodChargeRate;
use App\Models\PayrollCompany;
use App\Models\SalaryProcessing;
use Illuminate\Support\Carbon;

/**
 * What Hotel Pallav owes Pallav Food for its staff's meals in a month.
 *
 * The staff do not pay; the owner does, so none of this touches salary. Pallav
 * Food fixes one monthly amount per employee. It is counted by calendar days:
 * every day of the month counts, absent days and week-offs included, and only
 * the days before someone joined are left out - so a full month is the full
 * amount and a mid-month joiner pays for the days from their joining date.
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

    /** The rate in force today, for showing on the company form. */
    public static function currentRate(PayrollCompany $company): ?float
    {
        return self::rateFor($company, now());
    }

    /** Days of the month that count for someone who joined on $joined. */
    public static function daysCounted(Carbon $monthStart, ?Carbon $joined): int
    {
        $daysInMonth = $monthStart->daysInMonth;

        if ($joined === null || $joined->lessThan($monthStart)) {
            return $daysInMonth;
        }

        if ($joined->greaterThan($monthStart->copy()->endOfMonth())) {
            return 0;
        }

        return $daysInMonth - ($joined->day - 1);
    }

    /**
     * The statement for one month, or null when this company pays no food
     * charge, no amount is set, or nobody is charged.
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

        $rows = SalaryProcessing::with('employee')
            ->where('payroll_company_id', $company->id)
            ->where('year', $year)->where('month', $month)
            ->whereHas('employee', fn ($q) => $q->where('eats_at_pallav_food', true))
            ->orderBy('employee_name')->get()
            ->map(function (SalaryProcessing $slip) use ($start, $rate) {
                $days = self::daysCounted($start, $slip->employee?->joining_date);

                return [
                    'name' => $slip->employee_name,
                    'code' => $slip->employee_code,
                    'designation' => $slip->designation,
                    'days' => $days,
                    'amount' => round($rate * $days / $start->daysInMonth, 2),
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
}
