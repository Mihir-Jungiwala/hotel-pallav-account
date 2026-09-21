<?php

namespace App\Support;

use App\Models\AttendanceEntry;
use App\Models\Employee;
use App\Models\EmployeeMealPeriod;
use App\Models\EmployeeSeparation;
use App\Models\FoodChargeRate;
use App\Models\PayrollCompany;
use App\Models\SalaryProcessing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The cost of staff meals at Pallav Food, month by month. It is worked out only
 * once the month's salary has been generated.
 *
 * Pallav Food alone decides the price, and it is a price per employee for a
 * whole month. A price can change part-way through a month: the old one stays
 * on record and the new one starts on the day it is entered, so each day is
 * charged at the price in force that day (a day's share is the monthly price
 * divided by the days in that month). That is why the amount is worked out a
 * day at a time.
 *
 * Who takes meals is a set of dated records per person (from a day, to a day),
 * so starting or stopping someone changes only the days it covers.
 *
 * Which days count:
 *  - a calendar day within one of the person's meals records and within their
 *    service (joining date to last working day), absent days and week-offs included
 *  - except a day marked with an attendance status that says food is not
 *    counted (leave, for example): that day is left out
 *
 * Once salary has been generated for a month, in either company, that month is
 * closed to price changes, so a bill that has been paid against can never be
 * quietly rewritten.
 *
 * Hotel Pallav owes Pallav Food for its staff. Pallav Food's own staff eat at
 * the same price, shown as a cost of its own rather than something owed.
 */
class FoodCharges
{
    /* ---------------------------------------------------------------- price */

    /** Every price ever set, oldest first. Pallav Food's alone. */
    public static function rates(): Collection
    {
        $provider = PayrollCompany::foodProvider();

        if ($provider === null) {
            return collect();
        }

        return FoodChargeRate::where('payroll_company_id', $provider->id)
            ->orderBy('effective_from')->orderBy('id')->get();
    }

    /** The monthly price in force on one day, or null before any price was set. */
    public static function rateOn(Carbon $day, ?Collection $rates = null): ?float
    {
        $rates ??= self::rates();

        $rate = $rates->filter(fn (FoodChargeRate $r) => $r->effective_from->lessThanOrEqualTo($day))->last();

        return $rate ? (float) $rate->monthly_amount : null;
    }

    /** The price in force today. */
    public static function currentRate(): ?float
    {
        return self::rateOn(now()->startOfDay());
    }

    /* --------------------------------------------------------------- locking */

    /** The most recent month for which salary has been generated in either company, as the first of that month. */
    public static function lockedThrough(): ?Carbon
    {
        $latest = SalaryProcessing::orderByDesc('year')->orderByDesc('month')->first(['year', 'month']);

        return $latest ? Carbon::create($latest->year, $latest->month, 1)->startOfDay() : null;
    }

    /** Whether a date falls in a month that salary has already been generated for. */
    public static function isLocked(Carbon $date): bool
    {
        $locked = self::lockedThrough();

        return $locked !== null && $date->copy()->startOfMonth()->lessThanOrEqualTo($locked);
    }

    /** The first month a price can still start in. */
    public static function firstOpenMonth(): Carbon
    {
        $locked = self::lockedThrough();

        return $locked ? $locked->copy()->addMonthNoOverflow() : Carbon::create(2000, 1, 1)->startOfDay();
    }

    /* ------------------------------------------------------------ the bill */

    /** Whether salary has been generated for a company's month. Until it has, there is no bill. */
    public static function isGenerated(PayrollCompany $company, int $year, int $month): bool
    {
        return SalaryProcessing::where('payroll_company_id', $company->id)
            ->where('year', $year)->where('month', $month)->exists();
    }

    /* ------------------------------------------- a company's own closed months */

    /**
     * The latest month whose salary has been generated in this company, as the
     * first of that month. A person's meals belong to their own company's bill,
     * so this - unlike the price - is worked out company by company.
     */
    public static function lockedThroughFor(PayrollCompany $company): ?Carbon
    {
        $latest = SalaryProcessing::where('payroll_company_id', $company->id)
            ->orderByDesc('year')->orderByDesc('month')->first(['year', 'month']);

        return $latest ? Carbon::create($latest->year, $latest->month, 1)->startOfDay() : null;
    }

    /** Whether a day falls in a month whose salary this company has already generated. */
    public static function isClosedFor(PayrollCompany $company, Carbon $day): bool
    {
        $locked = self::lockedThroughFor($company);

        return $locked !== null && $day->copy()->startOfMonth()->lessThanOrEqualTo($locked);
    }

    /** The first day a meals record can still change things from. */
    public static function firstOpenDayFor(PayrollCompany $company): Carbon
    {
        $locked = self::lockedThroughFor($company);

        return $locked ? $locked->copy()->addMonthNoOverflow() : Carbon::create(2000, 1, 1)->startOfDay();
    }

    /* ------------------------------------------------------- who is charged */

    /**
     * Staff charged for a month: people whose meals records cover part of it and
     * who were in the salary run that was generated. The salary run decides who
     * was on the payroll, so the bill follows it.
     */
    public static function chargedStaff(PayrollCompany $company, int $year, int $month): Collection
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->startOfDay();

        return Employee::where('payroll_company_id', $company->id)
            ->whereIn('id', SalaryProcessing::where('payroll_company_id', $company->id)
                ->where('year', $year)->where('month', $month)->select('employee_id'))
            ->whereHas('mealPeriods', fn ($q) => $q
                ->whereDate('starts_on', '<=', $end)
                ->where(fn ($w) => $w->whereNull('ends_on')->orWhereDate('ends_on', '>=', $start)))
            ->with('mealPeriods')
            ->orderBy('name')->get();
    }

    /**
     * Days of one month that someone is charged for.
     *
     * A day counts when it is within their service (joining to last working
     * day), within one of their meals records, and not marked with a status that
     * leaves meals out.
     *
     * @param  array<int, int>  $skipped  day numbers marked with a status that leaves meals out
     * @param  Collection<int, EmployeeMealPeriod>|null  $periods  their meals records; null means no limit
     * @return array<int, int>  the day numbers that count
     */
    public static function countedDays(Carbon $monthStart, ?Carbon $joined, ?Carbon $lastWorkingDay = null, array $skipped = [], ?Collection $periods = null): array
    {
        $from = $monthStart->copy()->startOfMonth();
        $to = $monthStart->copy()->endOfMonth()->startOfDay();

        if ($joined !== null && $joined->greaterThan($from)) {
            $from = $joined->copy()->startOfDay();
        }

        if ($lastWorkingDay !== null && $lastWorkingDay->lessThan($to)) {
            $to = $lastWorkingDay->copy()->startOfDay();
        }

        $days = [];

        for ($day = $from->copy(); $day->lessThanOrEqualTo($to); $day->addDay()) {
            if (in_array($day->day, $skipped, true)) {
                continue;
            }

            if ($periods !== null && ! $periods->contains(fn (EmployeeMealPeriod $p) => $p->covers($day))) {
                continue;
            }

            $days[] = $day->day;
        }

        return $days;
    }

    /**
     * The statement for one month in one company, or null when the company
     * does not use Pallav Food, salary is not generated yet, no price is set,
     * or nobody was charged.
     *
     * @return array<string, mixed>|null
     */
    public static function statement(PayrollCompany $company, int $year, int $month): ?array
    {
        if (! $company->servesMeals() || ! self::isGenerated($company, $year, $month)) {
            return null;
        }

        $start = Carbon::create($year, $month, 1)->startOfDay();
        $rates = self::rates();
        $daysInMonth = $start->daysInMonth;

        if ($rates->isEmpty() || self::rateOn($start->copy()->endOfMonth()->startOfDay(), $rates) === null) {
            return null;
        }

        $staff = self::chargedStaff($company, $year, $month);

        // Anyone relieved and not since rejoined stops being charged after their last day
        $lastDays = EmployeeSeparation::whereIn('employee_id', $staff->pluck('id'))
            ->whereNull('rejoined_at')->where('status', 'Relieved')
            ->pluck('last_working_date', 'employee_id');

        // Days marked with a status that leaves meals out, per employee
        $skippedDays = AttendanceEntry::query()
            ->whereHas('month', fn ($q) => $q->where('payroll_company_id', $company->id)->where('year', $year)->where('month', $month))
            ->where('skips_food', true)
            ->get(['employee_id', 'day'])
            ->groupBy('employee_id')->map(fn ($rows) => $rows->pluck('day')->map(fn ($d) => (int) $d)->all());

        $rows = $staff->map(function (Employee $employee) use ($start, $rates, $daysInMonth, $lastDays, $skippedDays) {
            $left = ($lastDays[$employee->id] ?? null) ? Carbon::parse($lastDays[$employee->id]) : null;
            $skipped = $skippedDays[$employee->id] ?? [];
            $periods = $employee->mealPeriods;

            $counted = self::countedDays($start, $employee->joining_date, $left, $skipped, $periods);

            // Each day at the price in force that day, each worth a day's share of the month
            $total = 0.0;
            foreach ($counted as $day) {
                $total += (self::rateOn($start->copy()->day($day), $rates) ?? 0.0) / $daysInMonth;
            }

            // Days that were on meals but left out for their status, so the bill explains itself
            $withoutSkips = self::countedDays($start, $employee->joining_date, $left, [], $periods);
            $leftOut = count($withoutSkips) - count($counted);

            return [
                'name' => $employee->name,
                'code' => $employee->employee_code,
                'designation' => $employee->designation,
                'days' => count($counted),
                'left_out' => $leftOut,
                'amount' => round($total, 2),
                'note' => self::note($start, $periods, $employee->joining_date, $left, $leftOut),
            ];
        })
            ->filter(fn ($row) => $row['days'] > 0)
            ->values()->all();

        if ($rows === []) {
            return null;
        }

        $owed = $company->paysFoodCharges();

        return [
            'payee' => PayrollCompany::FOOD_PAYEE,
            // Hotel Pallav owes it; Pallav Food's own staff are simply a cost
            'owed' => $owed,
            'title' => $owed ? 'Pay to '.PayrollCompany::FOOD_PAYEE : 'Staff meals',
            'month' => $start,
            'daysInMonth' => $daysInMonth,
            'prices' => self::pricesIn($start, $rates),
            'rate' => self::rateOn($start->copy()->endOfMonth()->startOfDay(), $rates),
            'rows' => $rows,
            'total' => round(array_sum(array_column($rows, 'amount')), 2),
        ];
    }
    /**
     * The prices in force during a month, in order, for saying "3,000 until 14
     * Sep, then 3,500". A single entry when the price did not change.
     *
     * @return array<int, array{from: Carbon, amount: float}>
     */
    public static function pricesIn(Carbon $monthStart, ?Collection $rates = null): array
    {
        $rates ??= self::rates();
        $end = $monthStart->copy()->endOfMonth()->startOfDay();

        $opening = self::rateOn($monthStart, $rates);
        $prices = $opening !== null ? [['from' => $monthStart->copy(), 'amount' => $opening]] : [];

        foreach ($rates as $rate) {
            if ($rate->effective_from->greaterThan($monthStart) && $rate->effective_from->lessThanOrEqualTo($end)) {
                $prices[] = ['from' => $rate->effective_from->copy(), 'amount' => (float) $rate->monthly_amount];
            }
        }

        return $prices;
    }

    private static function note(Carbon $monthStart, Collection $periods, ?Carbon $joined, ?Carbon $left, int $leftOut): ?string
    {
        $end = $monthStart->copy()->endOfMonth()->startOfDay();
        $parts = [];

        if ($joined !== null && $joined->isSameMonth($monthStart) && $joined->isSameYear($monthStart)) {
            $parts[] = 'joined '.$joined->format('j M');
        }

        if ($left !== null && $left->isSameMonth($monthStart) && $left->isSameYear($monthStart)) {
            $parts[] = 'left '.$left->format('j M');
        }

        // Where the meals themselves began or stopped part-way through this month
        foreach ($periods->sortBy('starts_on') as $period) {
            if ($period->starts_on->greaterThan($monthStart) && $period->starts_on->lessThanOrEqualTo($end)) {
                $parts[] = 'meals from '.$period->starts_on->format('j M');
            }

            if ($period->ends_on !== null && $period->ends_on->greaterThanOrEqualTo($monthStart) && $period->ends_on->lessThan($end)) {
                $parts[] = 'meals until '.$period->ends_on->format('j M');
            }
        }

        if ($leftOut > 0) {
            $parts[] = $leftOut.' '.($leftOut === 1 ? 'day' : 'days').' not counted';
        }

        return $parts ? implode(', ', $parts) : null;
    }
}
