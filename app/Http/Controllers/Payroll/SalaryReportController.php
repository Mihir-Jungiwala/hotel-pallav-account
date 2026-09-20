<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\AttendanceMonth;
use App\Models\AttendanceStatus;
use App\Models\SalaryProcessing;
use App\Support\PayrollContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SalaryReportController extends Controller
{
    /**
     * The month a report screen opens on. An explicit choice wins; otherwise
     * the most recently processed month, because the month still running has
     * no salary in it yet. Never past the current month.
     */
    public static function resolveMonth(int $companyId, Request $request): Carbon
    {
        if ($request->filled('year') && $request->filled('month')) {
            $requested = Carbon::create((int) $request->input('year'), (int) $request->input('month'), 1);
        } else {
            $latest = SalaryProcessing::where('payroll_company_id', $companyId)
                ->orderByDesc('year')->orderByDesc('month')
                ->first(['year', 'month']);

            $requested = $latest
                ? Carbon::create($latest->year, $latest->month, 1)
                : now()->startOfMonth();
        }

        return $requested->greaterThan(now()->startOfMonth())
            ? now()->startOfMonth()
            : $requested->startOfMonth();
    }

    /** Daily / period-wise salary report. */
    public function index(Request $request)
    {
        $company = PayrollContext::currentOrFail();

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

        return view('payroll.pages.period-report', [
            'company' => $company,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'selectedEmployee' => $employeeId,
            'periodError' => $error,
            'rows' => $rows,
            // Anyone with processed salary can be reported on, leavers included
            'reportableEmployees' => \App\Models\Employee::where('payroll_company_id', $company->id)
                ->orderBy('name')->get(),
        ]);
    }

    /** Monthly salary report. */
    public function monthlyIndex(Request $request)
    {
        $company = PayrollContext::currentOrFail();
        $start = self::resolveMonth($company->id, $request);

        $rows = SalaryProcessing::with('lines')
            ->where('payroll_company_id', $company->id)
            ->where('year', $start->year)->where('month', $start->month)
            ->orderBy('employee_name')->get();

        return view('payroll.pages.monthly-report', [
            'company' => $company,
            'monthStart' => $start,
            'rows' => $rows,
            'grid' => $rows->isNotEmpty() ? $this->attendanceGrid($company->id, $start) : null,
            // What Hotel Pallav owes Pallav Food for its staff's meals; null for any other company
            'food' => \App\Support\FoodCharges::statement($company, $start->year, $start->month),
        ]);
    }

    /**
     * Read-only attendance grid beneath the monthly report, laid out the same
     * way as Attendance Management so the two read as one thing.
     *
     * @return array<string, mixed>|null
     */
    private function attendanceGrid(int $companyId, Carbon $start): ?array
    {
        $attendanceMonth = AttendanceMonth::where('payroll_company_id', $companyId)
            ->where('year', $start->year)->where('month', $start->month)->first();

        if ($attendanceMonth === null) {
            return null;
        }

        return [
            'start' => $start,
            'daysInMonth' => $start->daysInMonth,
            'entries' => $attendanceMonth->entries()->get()->groupBy('employee_id')->map(fn ($rows) => $rows->keyBy('day')),
            'statuses' => AttendanceStatus::where('payroll_company_id', $companyId)->get()
                ->mapWithKeys(fn ($s) => [strtoupper($s->shortcut_key) => ['color' => $s->color, 'name' => $s->name]]),
        ];
    }

    public function monthly(Request $request)
    {
        $company = PayrollContext::currentOrFail();

        if ($request->filled('year') && $request->filled('month')) {
            [$year, $month] = [$request->input('year'), $request->input('month')];
        } else {
            [$year, $month] = array_pad(explode('-', (string) $request->input('period')), 2, null);
        }
        abort_if(! $year || ! $month, 404, 'Choose a month first.');

        $rows = SalaryProcessing::with('lines')
            ->where('payroll_company_id', $company->id)
            ->where('year', (int) $year)->where('month', (int) $month)
            ->orderBy('employee_name')->get();

        abort_if($rows->isEmpty(), 404, 'No processed salary for that month.');

        $start = Carbon::create((int) $year, (int) $month, 1);
        $attendanceMonth = AttendanceMonth::where('payroll_company_id', $company->id)
            ->where('year', (int) $year)->where('month', (int) $month)->first();

        $entries = $attendanceMonth
            ? $attendanceMonth->entries()->get()->groupBy('employee_id')->map(fn ($r) => $r->keyBy('day'))
            : collect();

        $statuses = AttendanceStatus::where('payroll_company_id', $company->id)->get()
            ->mapWithKeys(fn ($s) => [strtoupper($s->shortcut_key) => ['color' => $s->color, 'name' => $s->name]]);

        return \App\Support\PayrollPdf::make('payroll.pdf.monthly-report', [
            'company' => $company,
            'rows' => $rows,
            'start' => $start,
            'daysInMonth' => $start->daysInMonth,
            'entries' => $entries,
            'statuses' => $statuses,
            'food' => \App\Support\FoodCharges::statement($company, (int) $year, (int) $month),
        ], 'landscape')
            ->stream('monthly-salary-report-'.$start->format('Y-m').'.pdf');
    }

    public function period(Request $request)
    {
        $company = PayrollContext::currentOrFail();

        $from = Carbon::parse($request->input('from_date'));
        $to = Carbon::parse($request->input('to_date'));

        abort_if($from->year !== $to->year || $from->month !== $to->month, 422, 'The range must sit inside one month.');

        $rows = SalaryProcessing::with('lines')
            ->where('payroll_company_id', $company->id)
            ->where('year', $from->year)->where('month', $from->month)
            ->when($request->input('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->orderBy('employee_name')->get();

        abort_if($rows->isEmpty(), 404, 'Nothing to report for that selection.');

        return \App\Support\PayrollPdf::make('payroll.pdf.period-report', [
            'company' => $company,
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
        ], 'landscape')
            ->stream('period-salary-report-'.$from->format('Y-m-d').'.pdf');
    }
}
