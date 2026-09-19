<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\AttendanceMonth;
use App\Models\AttendanceStatus;
use App\Models\BonusIncentive;
use App\Models\Deduction;
use App\Models\Employee;
use App\Models\EmployeeSeparation;
use App\Models\ExperienceLetter;
use App\Models\JoiningLetter;
use App\Models\PayrollAdvance;
use App\Models\PayrollCompany;
use App\Models\SalaryProcessing;
use App\Support\PayrollContext;

/**
 * The company's own landing page - where opening a company arrives.
 *
 * It answers "what is the state of payroll here, and what needs me?" before
 * anyone picks a module. Everything on it is a link into the page that can
 * act on it, so it reads as a way in rather than a report.
 */
class PayrollDashboardController extends Controller
{
    public function index()
    {
        $company = PayrollContext::currentOrFail();
        $id = $company->id;

        $month = now()->startOfMonth();

        $staff = Employee::where('payroll_company_id', $id)->where('is_active', true)->count();
        $allStaff = Employee::where('payroll_company_id', $id)->count();

        $advances = PayrollAdvance::where('payroll_company_id', $id)->get();
        $outstandingAdvance = (float) $advances->sum(fn ($a) => $a->outstanding());

        // The month salary was last processed for - the one payments are about
        $latest = SalaryProcessing::where('payroll_company_id', $id)
            ->orderByDesc('year')->orderByDesc('month')->first(['year', 'month']);

        $payroll = $latest
            ? SalaryProcessing::where('payroll_company_id', $id)
                ->where('year', $latest->year)->where('month', $latest->month)->get()
            : collect();

        $net = (float) $payroll->sum('net_salary');
        $paid = (float) $payroll->sum('paid_amount');

        return view('payroll.pages.dashboard', [
            'company' => $company,
            'month' => $month,
            'stats' => [
                'staff' => $staff,
                'formerStaff' => $allStaff - $staff,
                'attendance' => $this->attendanceProgress($id, $staff),
                'payrollMonth' => $latest ? \Illuminate\Support\Carbon::create($latest->year, $latest->month, 1) : null,
                'net' => $net,
                'paid' => $paid,
                'outstanding' => max(0, $net - $paid),
                'unpaidCount' => $payroll->where('payment_status', '!=', 'Paid')->count(),
                'payrollCount' => $payroll->count(),
                'advanceOutstanding' => $outstandingAdvance,
                'advanceOpen' => $advances->where('is_settled', false)->count(),
            ],
            'attention' => $this->attention($company),
            'recent' => $this->recent($id),
        ]);
    }

    /**
     * How much of the running month has been filled in. Measured against the
     * days that have actually happened, not the whole month, or a month still
     * running would always look behind.
     *
     * @return array<string, mixed>
     */
    private function attendanceProgress(int $companyId, int $staff): array
    {
        $month = AttendanceMonth::where('payroll_company_id', $companyId)
            ->where('year', now()->year)->where('month', now()->month)->first();

        $expected = $staff * now()->day;

        if ($month === null || $expected === 0) {
            return ['filled' => 0, 'expected' => $expected, 'percent' => 0, 'locked' => false];
        }

        $filled = $month->entries()->whereNotNull('attendance_status_id')->count();

        return [
            'filled' => $filled,
            'expected' => $expected,
            'percent' => min(100, (int) round($filled / $expected * 100)),
            'locked' => ! $month->isEditable(),
        ];
    }

    /**
     * Things somebody still has to do, setup gaps first: a company missing its
     * attendance statuses cannot record a day at all, so that outranks an
     * unpaid salary.
     *
     * @return array<int, array<string, string>>
     */
    private function attention(PayrollCompany $company): array
    {
        $id = $company->id;
        $items = [];

        if (! AttendanceStatus::where('payroll_company_id', $id)->where('is_active', true)->exists()) {
            $items[] = [
                'tone' => 'danger', 'icon' => 'bi-palette2',
                'text' => 'No attendance statuses yet - attendance cannot be recorded until there is at least one.',
                'action' => 'Set them up', 'link' => route('payroll.attendance-status.index'),
            ];
        }

        if (! Employee::where('payroll_company_id', $id)->where('is_active', true)->exists()) {
            $items[] = [
                'tone' => 'danger', 'icon' => 'bi-people',
                'text' => 'No staff on the payroll yet.',
                'action' => 'Add someone', 'link' => route('payroll.staff.index'),
            ];
        }

        if (! Deduction::where('payroll_company_id', $id)->exists()) {
            $items[] = [
                'tone' => 'warn', 'icon' => 'bi-dash-circle',
                'text' => 'No deductions are configured, so none can be assigned to staff.',
                'action' => 'Configure', 'link' => route('payroll.deduction.index'),
            ];
        }

        if (! JoiningLetter::where('payroll_company_id', $id)->exists()) {
            $items[] = [
                'tone' => 'warn', 'icon' => 'bi-file-earmark-text',
                'text' => 'No joining letter template, so letters cannot be issued.',
                'action' => 'Write one', 'link' => route('payroll.joining-letter.index'),
            ];
        }

        if (! ExperienceLetter::where('payroll_company_id', $id)->exists()) {
            $items[] = [
                'tone' => 'warn', 'icon' => 'bi-file-earmark-check',
                'text' => 'No experience letter template, so leavers cannot be issued one.',
                'action' => 'Write one', 'link' => route('payroll.experience-letter.index'),
            ];
        }

        $unpaid = SalaryProcessing::where('payroll_company_id', $id)
            ->whereIn('payment_status', ['Pending', 'Processing', 'On Hold', 'Partially Paid'])->count();

        if ($unpaid) {
            $items[] = [
                'tone' => 'warn', 'icon' => 'bi-credit-card-2-back',
                'text' => $unpaid.' salary '.($unpaid === 1 ? 'payment is' : 'payments are').' still open.',
                'action' => 'Settle them', 'link' => route('payroll.salary-payment.index'),
            ];
        }

        $failed = SalaryProcessing::where('payroll_company_id', $id)->where('payment_status', 'Failed')->count();
        if ($failed) {
            $items[] = [
                'tone' => 'danger', 'icon' => 'bi-exclamation-octagon',
                'text' => $failed.' salary '.($failed === 1 ? 'payment' : 'payments').' failed and need re-issuing.',
                'action' => 'Review', 'link' => route('payroll.salary-payment.index'),
            ];
        }

        $notice = EmployeeSeparation::where('payroll_company_id', $id)
            ->whereNull('rejoined_at')->where('status', '!=', 'Relieved')->count();

        if ($notice) {
            $items[] = [
                'tone' => 'info', 'icon' => 'bi-box-arrow-right',
                'text' => $notice.' '.($notice === 1 ? 'person is' : 'people are').' working their notice period.',
                'action' => 'Open', 'link' => route('payroll.separation.index'),
            ];
        }

        return $items;
    }

    /**
     * The latest thing to happen in each of the modules that move, so the
     * page shows activity rather than only totals.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recent(int $id): array
    {
        $rows = [];

        foreach (PayrollAdvance::with('employee')->where('payroll_company_id', $id)
            ->orderByDesc('advance_date')->orderByDesc('id')->limit(4)->get() as $row) {
            $rows[] = [
                'at' => $row->advance_date, 'icon' => 'bi-wallet2',
                'title' => optional($row->employee)->name ?: 'Removed employee',
                'detail' => 'Advance of ₹'.number_format($row->amount, 2),
                'link' => route('payroll.advance.index'),
            ];
        }

        foreach (BonusIncentive::with('employee')->where('payroll_company_id', $id)
            ->orderByDesc('entry_date')->orderByDesc('id')->limit(4)->get() as $row) {
            $rows[] = [
                'at' => $row->entry_date, 'icon' => 'bi-gift',
                'title' => optional($row->employee)->name ?: 'Removed employee',
                'detail' => $row->type.' of ₹'.number_format($row->amount, 2),
                'link' => route('payroll.bonus-incentive.index'),
            ];
        }

        foreach (EmployeeSeparation::with('employee')->where('payroll_company_id', $id)
            ->orderByDesc('last_working_date')->orderByDesc('id')->limit(3)->get() as $row) {
            $rows[] = [
                'at' => $row->last_working_date, 'icon' => 'bi-box-arrow-right',
                'title' => optional($row->employee)->name ?: 'Removed employee',
                'detail' => $row->separation_type.' - '.$row->status,
                'link' => route('payroll.separation.index'),
            ];
        }

        usort($rows, fn ($a, $b) => $b['at'] <=> $a['at']);

        return array_slice($rows, 0, 7);
    }
}
