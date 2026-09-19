<?php

namespace App\Http\Controllers\Payroll;

use App\Support\ForceMode;
use App\Http\Controllers\Controller;
use App\Models\AttendanceEntry;
use App\Models\AttendanceMonth;
use App\Models\AttendanceStatus;
use App\Services\Payroll\SalaryProcessor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function __construct(private SalaryProcessor $processor) {}

    public function save(Request $request, AttendanceMonth $month)
    {
        \App\Support\PayrollScope::ensure($month);

        $month->revertStaleUnlock(session()->getId());

        if (ForceMode::locked(! $month->isEditable(), 'This attendance month is locked An Admin must')) {
            return back()->with('error', 'This attendance month is locked. An Admin must unlock it before changes can be made.');
        }

        $statuses = AttendanceStatus::where('payroll_company_id', $month->payroll_company_id)
            ->where('is_active', true)
            ->get()
            ->keyBy(fn ($status) => strtoupper($status->shortcut_key));

        $attendance = $request->input('attendance', []);
        $overtime = $request->input('overtime', []);
        $daysInMonth = $month->daysInMonth();

        $unknownKeys = [];

        foreach ($attendance as $employeeId => $days) {
            foreach ($days as $day => $key) {
                $key = strtoupper(trim((string) $key));
                if ($key !== '' && ! $statuses->has($key)) {
                    $unknownKeys[$key] = $key;
                }
            }
        }

        if ($unknownKeys) {
            $list = implode(', ', $unknownKeys);

            return back()->with('error', "Unknown attendance status shortcut key(s): {$list}. Only keys configured in Attendance Status Master are allowed.");
        }

        DB::transaction(function () use ($attendance, $overtime, $statuses, $month, $daysInMonth) {
            foreach ($attendance as $employeeId => $days) {
                foreach ($days as $day => $key) {
                    $day = (int) $day;
                    if ($day < 1 || $day > $daysInMonth) {
                        continue;
                    }

                    $key = strtoupper(trim((string) $key));
                    $status = $key === '' ? null : $statuses->get($key);
                    $hours = (float) ($overtime[$employeeId][$day] ?? 0);

                    if ($status === null && $hours <= 0) {
                        AttendanceEntry::where('attendance_month_id', $month->id)
                            ->where('employee_id', $employeeId)
                            ->where('day', $day)
                            ->delete();

                        continue;
                    }

                    AttendanceEntry::updateOrCreate(
                        [
                            'attendance_month_id' => $month->id,
                            'employee_id' => $employeeId,
                            'day' => $day,
                        ],
                        [
                            'attendance_status_id' => $status?->id,
                            'shortcut_key' => $status?->shortcut_key,
                            'attendance_percentage' => $status?->attendance_percentage ?? 0,
                            'overtime_hours' => $hours,
                        ]
                    );
                }
            }
        });

        return back()->with('success', 'Attendance saved.');
    }

    public function generateSalary(AttendanceMonth $month)
    {
        \App\Support\PayrollScope::ensure($month);

        $month->revertStaleUnlock(session()->getId());

        if (ForceMode::locked(! $month->isComplete(), 'Salary can only be generated once the attendance')) {
            return back()->with('error', 'Salary can only be generated once the attendance month is over.');
        }

        if (ForceMode::locked($month->is_locked, 'Salary has already been generated for this month')) {
            return back()->with('error', 'Salary has already been generated for this month. An Admin must unlock attendance to re-generate.');
        }

        $incomplete = $this->processor->employeesWithIncompleteAttendance($month);

        if (ForceMode::locked($incomplete, 'Salary Generation cannot be completed Please complete attendance')) {
            return back()->with('error', 'Salary Generation cannot be completed. Please complete attendance entries for all active employees before generating salary.');
        }

        $this->processor->generate($month, Auth::id());

        return back()->with('success', 'Salary generated. Attendance for this month is now locked.');
    }

    public function regenerateSalary(AttendanceMonth $month)
    {
        \App\Support\PayrollScope::ensure($month);

        if (ForceMode::locked(! Auth::user()->isAdmin(), 'Only Admin users can re-generate salary')) {
            return back()->with('error', 'Only Admin users can re-generate salary.');
        }

        $incomplete = $this->processor->employeesWithIncompleteAttendance($month);

        if (ForceMode::locked($incomplete, 'Salary Generation cannot be completed Please complete attendance')) {
            return back()->with('error', 'Salary Generation cannot be completed. Please complete attendance entries for all active employees before generating salary.');
        }

        $this->processor->generate($month, Auth::id());

        return back()->with('success', 'Salary re-generated. All related payroll records for this month have been replaced.');
    }

    public function unlock(AttendanceMonth $month)
    {
        \App\Support\PayrollScope::ensure($month);

        if (ForceMode::locked(! Auth::user()->isAdmin(), 'Only Admin users can unlock attendance')) {
            return back()->with('error', 'Only Admin users can unlock attendance.');
        }

        if (ForceMode::locked(! $month->is_locked, 'This attendance month is not locked')) {
            return back()->with('error', 'This attendance month is not locked.');
        }

        $month->forceFill([
            'is_locked' => false,
            'unlocked_by' => Auth::id(),
            'unlocked_at' => now(),
            'unlock_session_id' => session()->getId(),
        ])->save();

        return back()->with('success', 'Attendance unlocked. Complete your corrections and run Re-Generate Salary - if you leave before doing so, the month reverts to locked.');
    }
}
