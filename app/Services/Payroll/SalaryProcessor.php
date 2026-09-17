<?php

namespace App\Services\Payroll;

use App\Models\AttendanceEntry;
use App\Models\AttendanceMonth;
use App\Models\BonusIncentive;
use App\Models\Employee;
use App\Models\PayrollAdvance;
use App\Models\SalaryProcessing;
use App\Models\SalaryProcessingLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Backend-only. Driven by the Generate Salary / Re-Generate Salary buttons in
 * Attendance Management — this service has no screen of its own.
 *
 *   Net Salary = Attendance Salary + Overtime + Bonus + Incentive
 *                - Deductions - Advances     (floored at zero)
 */
class SalaryProcessor
{
    public function __construct(private OvertimeCalculator $overtime) {}

    /**
     * Employees whose attendance is not fully filled in for the month.
     *
     * @return array<int, string>
     */
    public function employeesWithIncompleteAttendance(AttendanceMonth $month): array
    {
        $daysInMonth = $month->daysInMonth();
        $employees = $this->activeEmployees($month);

        $filled = AttendanceEntry::where('attendance_month_id', $month->id)
            ->whereNotNull('attendance_status_id')
            ->selectRaw('employee_id, COUNT(*) as filled')
            ->groupBy('employee_id')
            ->pluck('filled', 'employee_id');

        $incomplete = [];

        foreach ($employees as $employee) {
            if ((int) ($filled[$employee->id] ?? 0) < $daysInMonth) {
                $incomplete[] = $employee->name;
            }
        }

        return $incomplete;
    }

    public function generate(AttendanceMonth $month, int $userId): void
    {
        DB::transaction(function () use ($month, $userId) {
            // Payments are real-world events: a re-generate must not forget them
            $payments = $this->monthProcessings($month)
                ->keyBy('employee_id')
                ->map(fn (SalaryProcessing $p) => $p->only([
                    'payment_status', 'paid_amount', 'paid_at', 'payment_reference',
                    'payment_remarks', 'payment_updated_by', 'payment_updated_at',
                ]));

            $this->reverse($month);

            foreach ($this->activeEmployees($month) as $employee) {
                $this->processEmployee($month, $employee, $userId);
            }

            foreach ($this->monthProcessings($month) as $processing) {
                $previous = $payments->get($processing->employee_id);
                if (! $previous) {
                    continue;
                }

                // If the recalculated salary grew past what was paid, it is no longer fully settled
                if ($previous['payment_status'] === 'Paid'
                    && (float) $previous['paid_amount'] < (float) $processing->net_salary - 0.009) {
                    $previous['payment_status'] = 'Partially Paid';
                }

                $processing->forceFill($previous)->save();
            }

            $month->forceFill([
                'is_locked' => true,
                'locked_at' => now(),
                'salary_generated_at' => now(),
                'salary_generated_by' => $userId,
                'generation_count' => $month->generation_count + 1,
                'unlocked_by' => null,
                'unlocked_at' => null,
                'unlock_session_id' => null,
            ])->save();
        });
    }

    /**
     * Undo everything a previous run applied, so a re-generate starts clean.
     * Historical rows for other months are never touched.
     */
    public function reverse(AttendanceMonth $month): void
    {
        $processings = SalaryProcessing::with('lines')
            ->where('payroll_company_id', $month->payroll_company_id)
            ->where('year', $month->year)
            ->where('month', $month->month)
            ->get();

        foreach ($processings as $processing) {
            $touched = [];

            // Give each carry-forward balance back to the advance it came from
            $carryForwards = PayrollAdvance::where('source_salary_processing_id', $processing->id)->get();

            foreach ($carryForwards as $carryForward) {
                if ($carryForward->parent_advance_id && $parent = PayrollAdvance::find($carryForward->parent_advance_id)) {
                    $parent->recovered_amount = max(0, (float) $parent->recovered_amount - (float) $carryForward->amount);
                    $parent->save();
                    $touched[$parent->id] = $parent->id;
                }

                $carryForward->delete();
            }

            foreach ($processing->lines as $line) {
                if ($line->source_type === 'advance' && $line->source_id) {
                    if ($advance = PayrollAdvance::find($line->source_id)) {
                        $advance->recovered_amount = max(0, (float) $advance->recovered_amount - (float) $line->amount);
                        $advance->save();
                        $touched[$advance->id] = $advance->id;
                    }
                }

                if ($line->source_type === 'employee_deduction' && $line->source_id) {
                    \App\Models\EmployeeDeduction::where('id', $line->source_id)->update(['is_settled' => false]);
                }
            }

            foreach (PayrollAdvance::whereIn('id', $touched)->get() as $advance) {
                $advance->update(['is_settled' => $advance->outstanding() <= 0.001]);
            }

            $processing->delete();
        }
    }

    private function monthProcessings(AttendanceMonth $month)
    {
        return SalaryProcessing::where('payroll_company_id', $month->payroll_company_id)
            ->where('year', $month->year)
            ->where('month', $month->month)
            ->get();
    }

    private function activeEmployees(AttendanceMonth $month)
    {
        return Employee::where('payroll_company_id', $month->payroll_company_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    private function processEmployee(AttendanceMonth $month, Employee $employee, int $userId): void
    {
        $daysInMonth = $month->daysInMonth();

        $entries = AttendanceEntry::where('attendance_month_id', $month->id)
            ->where('employee_id', $employee->id)
            ->get();

        $buckets = [0 => 0, 25 => 0, 50 => 0, 75 => 0, 100 => 0];
        $overtimeHours = 0.0;

        foreach ($entries as $entry) {
            $percentage = (int) $entry->attendance_percentage;
            if (array_key_exists($percentage, $buckets)) {
                $buckets[$percentage]++;
            }
            $overtimeHours += (float) $entry->overtime_hours;
        }

        $payableDays = 0.0;
        foreach ($buckets as $percentage => $count) {
            $payableDays += $count * ($percentage / 100);
        }

        $dailySalary = $daysInMonth > 0 ? (float) $employee->salary / $daysInMonth : 0.0;
        $attendanceSalary = round($dailySalary * $payableDays, 2);

        $hourlyRate = $this->overtime->hourlyRate($employee, $daysInMonth);
        $overtimeAmount = $this->overtime->amount($employee, $daysInMonth, $overtimeHours);

        [$start, $end] = $this->monthBounds($month);

        $bonusRows = BonusIncentive::where('employee_id', $employee->id)
            ->whereBetween('entry_date', [$start, $end])
            ->get();

        $bonusAmount = (float) $bonusRows->where('type', 'Bonus')->sum('amount');
        $incentiveAmount = (float) $bonusRows->where('type', 'Incentive')->sum('amount');

        $processing = SalaryProcessing::create([
            'payroll_company_id' => $month->payroll_company_id,
            'employee_id' => $employee->id,
            'year' => $month->year,
            'month' => $month->month,
            'company_name' => $employee->company->name,
            'employee_code' => $employee->employee_code,
            'employee_name' => $employee->name,
            'designation' => $employee->designation,
            'department' => $employee->department,
            'payment_mode' => $employee->payment_mode,
            'monthly_salary' => $employee->salary,
            'daily_working_hours' => $employee->daily_working_hours,
            'total_days_in_month' => $daysInMonth,
            'daily_salary' => round($dailySalary, 2),
            'days_0' => $buckets[0],
            'days_25' => $buckets[25],
            'days_50' => $buckets[50],
            'days_75' => $buckets[75],
            'days_100' => $buckets[100],
            'total_payable_days' => $payableDays,
            'attendance_salary' => $attendanceSalary,
            'overtime_hours' => $overtimeHours,
            'hourly_rate' => round($hourlyRate, 2),
            'overtime_amount' => $overtimeAmount,
            'bonus_amount' => $bonusAmount,
            'incentive_amount' => $incentiveAmount,
            'generation_count' => $month->generation_count + 1,
            'processed_by' => $userId,
            'processed_at' => now(),
        ]);

        foreach ($bonusRows as $row) {
            SalaryProcessingLine::create([
                'salary_processing_id' => $processing->id,
                'category' => $row->type,
                'label' => $row->remarks ?: $row->type,
                'amount' => $row->amount,
                'source_type' => 'bonus_incentive',
                'source_id' => $row->id,
            ]);
        }

        $gross = $attendanceSalary + $overtimeAmount + $bonusAmount + $incentiveAmount;

        $deductionTotal = $this->applyDeductions($processing, $employee);
        $afterDeductions = max(0, $gross - $deductionTotal);

        [$advanceDeducted, $pendingAdvance] = $this->applyAdvances($processing, $employee, $afterDeductions);

        $netSalary = max(0, $afterDeductions - $advanceDeducted);

        $processing->forceFill([
            'deduction_amount' => $deductionTotal,
            'advance_deduction' => $advanceDeducted,
            'pending_advance_amount' => $pendingAdvance,
            'net_salary' => round($netSalary, 2),
        ])->save();
    }

    private function applyDeductions(SalaryProcessing $processing, Employee $employee): float
    {
        $total = 0.0;

        $assignments = $employee->deductions()->with('deduction')->get();

        foreach ($assignments as $assignment) {
            if ($assignment->deduction_type === 'One Time' && $assignment->is_settled) {
                continue;
            }

            $amount = (float) $assignment->amount;
            if ($amount <= 0) {
                continue;
            }

            $total += $amount;

            SalaryProcessingLine::create([
                'salary_processing_id' => $processing->id,
                'category' => 'Deduction',
                'label' => optional($assignment->deduction)->name ?? 'Deduction',
                'deduction_type' => $assignment->deduction_type,
                'amount' => $amount,
                'source_type' => 'employee_deduction',
                'source_id' => $assignment->id,
            ]);

            if ($assignment->deduction_type === 'One Time') {
                $assignment->update(['is_settled' => true]);
            }
        }

        return round($total, 2);
    }

    /**
     * @return array{0: float, 1: float} [amount deducted, amount still pending]
     */
    private function applyAdvances(SalaryProcessing $processing, Employee $employee, float $available): array
    {
        $advances = $employee->advances()
            ->where('is_settled', false)
            ->orderBy('advance_date')
            ->get()
            ->filter(fn (PayrollAdvance $a) => $a->outstanding() > 0.001);

        $remainingBudget = $available;
        $totalDeducted = 0.0;
        $totalCarried = 0.0;

        foreach ($advances as $advance) {
            $outstanding = $advance->outstanding();

            $instalment = $advance->deduction_type === 'Monthly'
                ? min((float) $advance->deduction_amount, $outstanding)
                : $outstanding;

            // Never deduct more than the salary can bear; the rest carries forward
            $deducted = max(0, min($instalment, $remainingBudget));

            if ($deducted > 0.001) {
                $advance->recovered_amount = (float) $advance->recovered_amount + $deducted;

                SalaryProcessingLine::create([
                    'salary_processing_id' => $processing->id,
                    'category' => 'Advance',
                    'label' => $advance->remarks ?: 'Salary Advance',
                    'deduction_type' => $advance->deduction_type,
                    'amount' => round($deducted, 2),
                    'pending_amount' => round($outstanding - $deducted, 2),
                    'source_type' => 'advance',
                    'source_id' => $advance->id,
                ]);

                $remainingBudget -= $deducted;
                $totalDeducted += $deducted;
            }

            $remainder = $outstanding - $deducted;

            if ($remainder > 0.001) {
                // Close this advance out and move the balance into a fresh
                // record dated the 1st of next month, so nothing double-counts.
                $advance->recovered_amount = (float) $advance->recovered_amount + $remainder;
                $this->createCarryForward($processing, $advance, $remainder);
                $totalCarried += $remainder;
            }

            $advance->is_settled = true;
            $advance->save();
        }

        return [round($totalDeducted, 2), round($totalCarried, 2)];
    }

    private function createCarryForward(SalaryProcessing $processing, PayrollAdvance $parent, float $pending): void
    {
        $nextMonthFirst = Carbon::create($processing->year, $processing->month, 1)
            ->addMonthNoOverflow()
            ->startOfDay();

        $remarks = 'Carry Forward from Previous Month';
        if ($parent->remarks) {
            $remarks .= ' - '.$parent->remarks;
        }

        PayrollAdvance::create([
            'payroll_company_id' => $parent->payroll_company_id,
            'employee_id' => $parent->employee_id,
            'advance_date' => $nextMonthFirst,
            'amount' => round($pending, 2),
            'deduction_type' => $parent->deduction_type,
            'deduction_amount' => min((float) $parent->deduction_amount, round($pending, 2)),
            'remarks' => $remarks,
            'is_carry_forward' => true,
            'parent_advance_id' => $parent->id,
            'source_salary_processing_id' => $processing->id,
            'created_by' => $processing->processed_by,
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function monthBounds(AttendanceMonth $month): array
    {
        $start = Carbon::create($month->year, $month->month, 1)->startOfDay();

        return [$start, $start->copy()->endOfMonth()->endOfDay()];
    }
}
