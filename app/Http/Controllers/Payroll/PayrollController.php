<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\AttendanceMonth;
use App\Models\AttendanceStatus;
use App\Models\BonusIncentive;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\IdProofType;
use App\Models\JoiningLetter;
use App\Models\PayrollAdvance;
use App\Models\PayrollCompany;
use App\Models\SalaryProcessing;
use App\Services\Payroll\SalaryProcessor;
use App\Support\PayrollContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PayrollController extends Controller
{
    public function __construct(private SalaryProcessor $processor) {}

    public function index(Request $request)
    {
        if ($request->filled('current_company')) {
            PayrollContext::remember($request->string('current_company')->toString());
        }

        $company = PayrollContext::current();
        $category = $request->string('category')->toString() ?: PayrollContext::defaultCategory();

        if ($company === null) {
            $category = 'profile';
        } elseif ($category === 'profile') {
            $category = 'attendance-status';
        }

        return view('payroll.index', array_merge([
            'companies' => PayrollCompany::orderBy('name')->get(),
            'company' => $company,
            'category' => $category,
            'categories' => $company === null ? ['profile' => 'Profile'] : PayrollContext::CATEGORIES,
            'railCounts' => $this->railCounts($company),
        ], $this->dataFor($category, $company, $request)));
    }

    /**
     * Badge counts shown against each module in the navigation rail.
     *
     * @return array<string, int>
     */
    private function railCounts(?PayrollCompany $company): array
    {
        if ($company === null) {
            return [];
        }

        $id = $company->id;

        return [
            'attendance-status' => AttendanceStatus::where('payroll_company_id', $id)->count(),
            'deduction' => Deduction::where('payroll_company_id', $id)->count(),
            'staff' => Employee::where('payroll_company_id', $id)->where('is_active', true)->count(),
            'separation' => \App\Models\EmployeeSeparation::where('payroll_company_id', $id)->whereNull('rejoined_at')->count(),
            'advance' => PayrollAdvance::where('payroll_company_id', $id)->where('is_settled', false)->count(),
            'bonus-incentive' => BonusIncentive::where('payroll_company_id', $id)->count(),
            'salary-slip' => SalaryProcessing::where('payroll_company_id', $id)->count(),
            // Anything not yet fully settled needs attention
            'salary-payment' => SalaryProcessing::where('payroll_company_id', $id)->where('payment_status', '!=', 'Paid')->count(),
        ];
    }

    private function dataFor(string $category, ?PayrollCompany $company, Request $request): array
    {
        if ($company === null) {
            return ['activeCompanyCount' => PayrollCompany::where('is_active', true)->count()];
        }

        return match ($category) {
            'attendance-status' => [
                'statuses' => AttendanceStatus::where('payroll_company_id', $company->id)->orderBy('name')->get(),
            ],
            'deduction' => [
                'deductions' => Deduction::where('payroll_company_id', $company->id)->orderBy('name')->get(),
            ],
            'joining-letter' => [
                'letter' => JoiningLetter::where('payroll_company_id', $company->id)->first(),
            ],
            'experience-letter' => [
                'letter' => \App\Models\ExperienceLetter::where('payroll_company_id', $company->id)->first(),
            ],
            'staff' => [
                'employees' => Employee::with('deductions.deduction')
                    ->where('payroll_company_id', $company->id)->orderBy('name')->get(),
                'deductionOptions' => Deduction::where('payroll_company_id', $company->id)->where('is_active', true)->orderBy('name')->get(),
                'idProofTypes' => IdProofType::where('is_active', true)->orderBy('name')->get(),
                'rejoinable' => \App\Models\EmployeeSeparation::with('employee')
                    ->where('payroll_company_id', $company->id)
                    ->whereNull('rejoined_at')
                    ->where('status', 'Relieved')
                    ->get(),
            ],
            'separation' => [
                'separations' => \App\Models\EmployeeSeparation::with('employee')
                    ->where('payroll_company_id', $company->id)
                    ->orderByDesc('last_working_date')->get(),
                'activeEmployees' => $this->activeEmployees($company),
                'hasExperienceTemplate' => \App\Models\ExperienceLetter::where('payroll_company_id', $company->id)->exists(),
            ],
            'attendance' => $this->attendanceData($company, $request),
            'advance' => [
                'advances' => PayrollAdvance::with('employee')
                    ->where('payroll_company_id', $company->id)->orderByDesc('advance_date')->get(),
                'activeEmployees' => $this->activeEmployees($company),
            ],
            'bonus-incentive' => [
                'entries' => BonusIncentive::with('employee')
                    ->where('payroll_company_id', $company->id)->orderByDesc('entry_date')->get(),
                'activeEmployees' => $this->activeEmployees($company),
            ],
            'salary-update' => [
                'employees' => Employee::withCount('updateHistories')
                    ->where('payroll_company_id', $company->id)->orderBy('name')->get(),
            ],
            'salary-slip' => [
                'processings' => SalaryProcessing::with('employee')
                    ->where('payroll_company_id', $company->id)
                    ->orderByDesc('year')->orderByDesc('month')->orderBy('employee_name')->get(),
            ],
            'monthly-report' => $this->monthlyReportData($company, $request),
            'salary-payment' => $this->salaryPaymentData($company, $request),
            'period-report' => $this->periodReportData($company, $request),
            default => [],
        };
    }

    private function activeEmployees(PayrollCompany $company)
    {
        return Employee::where('payroll_company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function attendanceData(PayrollCompany $company, Request $request): array
    {
        $year = (int) ($request->input('year') ?: now()->year);
        $monthNumber = (int) ($request->input('month') ?: now()->month);

        // Never navigate past the current month
        $requested = Carbon::create($year, $monthNumber, 1);
        if ($requested->greaterThan(now()->startOfMonth())) {
            $requested = now()->startOfMonth();
        }

        $month = AttendanceMonth::firstOrCreate([
            'payroll_company_id' => $company->id,
            'year' => $requested->year,
            'month' => $requested->month,
        ]);

        $month->revertStaleUnlock(session()->getId());

        $employees = $this->activeEmployees($company);

        $entries = $month->entries()
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($rows) => $rows->keyBy('day'));

        $daysInMonth = $month->daysInMonth();
        $isCurrentMonth = $requested->isSameMonth(now());

        // While a month is still running you can only have filled the days so far,
        // so progress is measured against elapsed days rather than the whole month.
        $daysExpected = $isCurrentMonth ? min($daysInMonth, now()->day) : $daysInMonth;

        $filledCount = $month->entries()->whereNotNull('attendance_status_id')->count();

        return [
            'month' => $month,
            'monthStart' => $requested,
            'employees' => $employees,
            'entries' => $entries,
            'statuses' => AttendanceStatus::where('payroll_company_id', $company->id)
                ->where('is_active', true)->orderBy('name')->get(),
            'incompleteEmployees' => $this->processor->employeesWithIncompleteAttendance($month),
            'progress' => [
                'filled' => $filledCount,
                'expected' => $employees->count() * $daysExpected,
                'required' => $employees->count() * $daysInMonth,
                'isCurrentMonth' => $isCurrentMonth,
            ],
        ];
    }

    /**
     * Month the report-style screens open on. An explicit year/month wins;
     * otherwise the most recently processed month, since the running month
     * never has salary yet. Never goes past the current month.
     */
    private function resolveReportMonth(PayrollCompany $company, Request $request): Carbon
    {
        if ($request->filled('year') && $request->filled('month')) {
            $requested = Carbon::create((int) $request->input('year'), (int) $request->input('month'), 1);
        } else {
            $latest = SalaryProcessing::where('payroll_company_id', $company->id)
                ->orderByDesc('year')->orderByDesc('month')
                ->first(['year', 'month']);

            $requested = $latest
                ? Carbon::create($latest->year, $latest->month, 1)
                : now()->startOfMonth();
        }

        return $requested->greaterThan(now()->startOfMonth()) ? now()->startOfMonth() : $requested->startOfMonth();
    }

    private function monthlyReportData(PayrollCompany $company, Request $request): array
    {
        $start = $this->resolveReportMonth($company, $request);

        $rows = SalaryProcessing::with('lines')
            ->where('payroll_company_id', $company->id)
            ->where('year', $start->year)->where('month', $start->month)
            ->orderBy('employee_name')->get();

        return [
            'monthStart' => $start,
            'rows' => $rows,
            'grid' => $rows->isNotEmpty() ? $this->attendanceGridFor($company, $start->year, $start->month) : null,
        ];
    }

    private function salaryPaymentData(PayrollCompany $company, Request $request): array
    {
        $start = $this->resolveReportMonth($company, $request);

        $payments = SalaryProcessing::with('paymentUpdater', 'employee')
            ->where('payroll_company_id', $company->id)
            ->where('year', $start->year)->where('month', $start->month)
            ->orderBy('employee_name')->get();

        $net = (float) $payments->sum('net_salary');
        $paid = (float) $payments->sum('paid_amount');

        return [
            'monthStart' => $start,
            'payments' => $payments,
            'paymentSummary' => [
                'net' => $net,
                'paid' => $paid,
                'outstanding' => max(0, $net - $paid),
                'fullyPaid' => $payments->where('payment_status', 'Paid')->count(),
                'total' => $payments->count(),
                'byStatus' => collect(array_keys(SalaryProcessing::PAYMENT_STATUSES))
                    ->mapWithKeys(fn ($s) => [$s => $payments->where('payment_status', $s)->count()]),
            ],
        ];
    }

    /**
     * Read-only attendance grid used by the monthly report, matching the
     * layout of Attendance Management.
     *
     * @return array<string, mixed>|null
     */
    private function attendanceGridFor(PayrollCompany $company, int $year, int $month): ?array
    {
        $attendanceMonth = AttendanceMonth::where('payroll_company_id', $company->id)
            ->where('year', $year)->where('month', $month)->first();

        if ($attendanceMonth === null) {
            return null;
        }

        $start = Carbon::create($year, $month, 1);

        return [
            'start' => $start,
            'daysInMonth' => $start->daysInMonth,
            'entries' => $attendanceMonth->entries()->get()->groupBy('employee_id')->map(fn ($rows) => $rows->keyBy('day')),
            'statuses' => AttendanceStatus::where('payroll_company_id', $company->id)->get()
                ->mapWithKeys(fn ($s) => [strtoupper($s->shortcut_key) => ['color' => $s->color, 'name' => $s->name]]),
        ];
    }

    private function periodReportData(PayrollCompany $company, Request $request): array
    {
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $employeeId = $request->input('employee_id');
        $error = null;
        $rows = collect();

        if ($fromDate && $toDate) {
            $from = Carbon::parse($fromDate);
            $to = Carbon::parse($toDate);

            if ($from->greaterThan($to)) {
                $error = 'From Date must be on or before To Date.';
            } elseif ($from->year !== $to->year || $from->month !== $to->month) {
                $error = 'The selected date range must belong to the same month and year.';
            } elseif ($to->greaterThan(now())) {
                $error = 'The selected date range must not exceed the current date.';
            } else {
                $rows = SalaryProcessing::with('lines')
                    ->where('payroll_company_id', $company->id)
                    ->where('year', $from->year)->where('month', $from->month)
                    ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
                    ->orderBy('employee_name')->get();
            }
        }

        return [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'selectedEmployee' => $employeeId,
            'periodError' => $error,
            'rows' => $rows,
            // Anyone with processed salary can be reported on, including leavers
            'reportableEmployees' => Employee::where('payroll_company_id', $company->id)
                ->orderBy('name')->get(),
        ];
    }
}
