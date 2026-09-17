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

        return Pdf::loadView('payroll.pdf.monthly-report', [
            'company' => $company,
            'rows' => $rows,
            'start' => $start,
            'daysInMonth' => $start->daysInMonth,
            'entries' => $entries,
            'statuses' => $statuses,
        ])->setPaper('a4', 'landscape')
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

        return Pdf::loadView('payroll.pdf.period-report', [
            'company' => $company,
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
        ])->setPaper('a4', 'landscape')
            ->stream('period-salary-report-'.$from->format('Y-m-d').'.pdf');
    }
}
