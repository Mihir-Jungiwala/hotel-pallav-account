<?php

namespace App\Http\Controllers\Payroll;

use App\Support\ForceMode;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SalaryUpdateHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SalaryUpdateController extends Controller
{
    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'effective_date' => ['required', 'date'],
            'salary' => ['required', 'numeric', 'min:0'],
            'designation' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'daily_working_hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'payment_mode' => ['required', Rule::in(['Cash', 'Bank'])],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'account_holder_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'ifsc_code' => ['nullable', 'string', 'max:20'],
            'branch_name' => ['nullable', 'string', 'max:150'],
        ]);

        $effectiveDate = $data['effective_date'];
        unset($data['effective_date']);

        $changes = [];

        foreach (SalaryUpdateHistory::TRACKED_FIELDS as $field => $label) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $previous = $employee->$field;
            $new = $data[$field];

            // Compare numerics loosely so 8 and 8.00 are not treated as a change
            $isSame = is_numeric($previous) && is_numeric($new)
                ? abs((float) $previous - (float) $new) < 0.0001
                : (string) $previous === (string) $new;

            if (! $isSame) {
                $changes[] = [
                    'field_name' => $label,
                    'previous_value' => $previous === null ? null : (string) $previous,
                    'new_value' => $new === null ? null : (string) $new,
                ];
            }
        }

        if (ForceMode::locked(! $changes, 'No tracked fields were changed')) {
            return back()->with('error', 'No tracked fields were changed.');
        }

        DB::transaction(function () use ($employee, $data, $changes, $effectiveDate) {
            $employee->update($data);

            $batchId = (string) Str::uuid();

            foreach ($changes as $change) {
                SalaryUpdateHistory::create(array_merge($change, [
                    'employee_id' => $employee->id,
                    'batch_id' => $batchId,
                    'effective_date' => $effectiveDate,
                    'changed_by' => Auth::id(),
                ]));
            }
        });

        return back()->with('success', count($changes).' field(s) updated and recorded in the update history.');
    }

    public function history(Employee $employee)
    {
        $history = $employee->updateHistories()
            ->with('changedBy')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('batch_id');

        return view('payroll.partials.salary-update-history', compact('employee', 'history'));
    }
}
