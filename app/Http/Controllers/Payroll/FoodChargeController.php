<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeMealPeriod;
use App\Models\PayrollCompany;
use App\Support\FoodCharges;
use App\Support\PayrollContext;
use App\Support\PayrollScope;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Staff Meals: who in this company takes meals at Pallav Food, and the month's
 * bill. Both Hotel Pallav and Pallav Food have it; the price itself is Pallav
 * Food's alone and is read here, never set (see FoodPriceController).
 *
 * A person's meals are dated records - from a day, to a day - like a salary
 * revision. Starting someone adds a record; stopping them closes it; nothing
 * else is touched. And a record can only change days in months whose salary has
 * not been generated yet, so a bill that has been worked out stays as it was.
 */
class FoodChargeController extends Controller
{
    private function company(): PayrollCompany
    {
        $company = PayrollContext::currentOrFail();

        abort_unless($company->servesMeals(), 404);

        return $company;
    }

    public function index(Request $request)
    {
        $company = $this->company();
        $monthStart = SalaryReportController::resolveMonth($company->id, $request);

        $staff = Employee::with(['mealPeriods' => fn ($q) => $q->with('creator')->orderByDesc('starts_on')->orderByDesc('id')])
            ->where('payroll_company_id', $company->id)
            ->orderByDesc('is_active')->orderBy('name')->get();

        $today = now()->startOfDay();

        return view('payroll.pages.food-charges', [
            'company' => $company,
            'monthStart' => $monthStart,
            'today' => $today,
            'firstOpen' => FoodCharges::firstOpenDayFor($company),
            'lockedThrough' => FoodCharges::lockedThroughFor($company),
            'rate' => FoodCharges::currentRate(),
            'food' => FoodCharges::statement($company, $monthStart->year, $monthStart->month),
            // No bill exists until the month's salary has been generated
            'generated' => FoodCharges::isGenerated($company, $monthStart->year, $monthStart->month),
            'staff' => $staff,
            // Who is on meals today, for the summary and the status column
            'onMeals' => $staff->filter(fn (Employee $e) => $e->mealPeriods->contains(fn ($p) => $p->covers($today)))->pluck('id')->all(),
        ]);
    }

    /** Starts a record for one person: from this day, and optionally to that day. */
    public function start(Request $request, Employee $employee)
    {
        $company = $this->company();
        PayrollScope::ensure($employee);

        $data = $request->validate([
            'starts_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$employee->joining_date->format('Y-m-d')],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on', 'before_or_equal:today'],
        ], [
            'starts_on.before_or_equal' => 'Meals cannot start after today.',
            'starts_on.after_or_equal' => $employee->name.' joined on '.$employee->joining_date->format('j F Y').', so meals cannot start before that.',
            'ends_on.before_or_equal' => 'The end date cannot be after today.',
            'ends_on.after_or_equal' => 'The end date cannot be before the start date.',
        ]);

        $starts = Carbon::parse($data['starts_on'])->startOfDay();
        $ends = filled($data['ends_on'] ?? null) ? Carbon::parse($data['ends_on'])->startOfDay() : null;

        if (FoodCharges::isClosedFor($company, $starts)) {
            return back()->withInput()->withErrors(['starts_on' => $this->closed($company, $starts)]);
        }

        if ($clash = $this->overlapping($employee, $starts, $ends)) {
            return back()->withInput()->withErrors(['starts_on' => $employee->name.' already has meals recorded for '.$clash->label().'. Stop that first, or start after it.']);
        }

        EmployeeMealPeriod::create([
            'payroll_company_id' => $company->id,
            'employee_id' => $employee->id,
            'starts_on' => $starts,
            'ends_on' => $ends,
            'created_by' => Auth::id(),
        ]);

        return back()->with('success', $employee->name.' takes meals from '.$starts->format('j F Y')
            .($ends ? ' to '.$ends->format('j F Y') : '').'.');
    }

    /** Sets the day a record ends - closing an ongoing one, or changing an end. */
    public function stop(Request $request, EmployeeMealPeriod $period)
    {
        $company = $this->company();
        PayrollScope::ensure($period);

        $data = $request->validate([
            'ends_on' => ['required', 'date', 'after_or_equal:'.$period->starts_on->format('Y-m-d'), 'before_or_equal:today'],
        ], [
            'ends_on.after_or_equal' => 'The end date cannot be before the start date ('.$period->starts_on->format('j F Y').').',
            'ends_on.before_or_equal' => 'The end date cannot be after today.',
        ]);

        $ends = Carbon::parse($data['ends_on'])->startOfDay();

        // The days that change are the ones between the old end and the new one; the
        // earliest of them is the day after whichever end comes first
        $firstChanged = ($period->ends_on === null || $ends->lessThan($period->ends_on) ? $ends : $period->ends_on)->copy()->addDay();

        if (FoodCharges::isClosedFor($company, $firstChanged)) {
            return back()->withErrors(['ends_on' => $this->closed($company, $firstChanged)]);
        }

        if ($clash = $this->overlapping($period->employee, $period->starts_on, $ends, $period->id)) {
            return back()->withErrors(['ends_on' => 'That would run into another record ('.$clash->label().').']);
        }

        $period->update(['ends_on' => $ends]);

        return back()->with('success', $period->employee->name.' takes meals '.$period->label().'.');
    }

    public function destroy(EmployeeMealPeriod $period)
    {
        $company = $this->company();
        PayrollScope::ensure($period);

        // Removing a record takes away every day it covered, from its first day
        if (FoodCharges::isClosedFor($company, $period->starts_on)) {
            return back()->with('error', $this->closed($company, $period->starts_on)
                .' To stop meals from a later day instead, end the record rather than removing it.');
        }

        $name = $period->employee->name;
        $period->delete();

        return back()->with('success', 'The meals record for '.$name.' was removed.');
    }

    /** Another record of the same person that overlaps the given days, if any. */
    private function overlapping(Employee $employee, Carbon $starts, ?Carbon $ends, ?int $except = null): ?EmployeeMealPeriod
    {
        return EmployeeMealPeriod::where('employee_id', $employee->id)
            ->when($except, fn ($q) => $q->where('id', '!=', $except))
            ->whereDate('starts_on', '<=', ($ends ?? Carbon::create(9999, 12, 31))->toDateString())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $starts->toDateString()))
            ->first();
    }

    private function closed(PayrollCompany $company, Carbon $day): string
    {
        return 'Salary has already been generated for '.$day->format('F Y').', so its meals bill is closed. '
            .'Changes can start from '.FoodCharges::firstOpenDayFor($company)->format('F Y').' onwards.';
    }
}
