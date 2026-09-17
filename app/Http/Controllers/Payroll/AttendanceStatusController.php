<?php

namespace App\Http\Controllers\Payroll;

use App\Support\ForceMode;
use App\Http\Controllers\Controller;
use App\Models\AttendanceStatus;
use App\Support\PayrollContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceStatusController extends Controller
{
    private function rules(int $companyId, ?AttendanceStatus $status = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'shortcut_key' => [
                'required', 'string', 'max:10',
                Rule::unique('attendance_statuses', 'shortcut_key')
                    ->where('payroll_company_id', $companyId)
                    ->ignore($status?->id),
            ],
            'color' => [
                'required', 'string', 'max:20',
                Rule::unique('attendance_statuses', 'color')
                    ->where('payroll_company_id', $companyId)
                    ->ignore($status?->id),
            ],
            'attendance_percentage' => ['required', Rule::in(AttendanceStatus::ALLOWED_PERCENTAGES)],
            'status_type' => ['required', Rule::in(['Paid', 'Unpaid'])],
        ];
    }

    private function messages(): array
    {
        return [
            'shortcut_key.unique' => 'This shortcut key is already used by another attendance status.',
            'color.unique' => 'This colour is already used by another attendance status.',
            'attendance_percentage.in' => 'Attendance percentage can only be 0, 25, 50, 75 or 100.',
        ];
    }

    public function store(Request $request)
    {
        $company = PayrollContext::currentOrFail();

        $data = $request->validate($this->rules($company->id), $this->messages());
        $data['shortcut_key'] = strtoupper($data['shortcut_key']);
        $data['payroll_company_id'] = $company->id;

        AttendanceStatus::create($data);

        return back()->with('success', 'Attendance status added.');
    }

    public function update(Request $request, AttendanceStatus $status)
    {
        $data = $request->validate($this->rules($status->payroll_company_id, $status), $this->messages());
        $data['shortcut_key'] = strtoupper($data['shortcut_key']);

        $status->update($data);

        return back()->with('success', 'Attendance status updated.');
    }

    public function destroy(AttendanceStatus $status)
    {
        if (ForceMode::locked($status->entries()->exists(), 'This attendance status is already used in Attendance')) {
            return back()->with('error', 'This attendance status is already used in Attendance Management and cannot be deleted.');
        }

        $status->delete();

        return back()->with('success', 'Attendance status deleted.');
    }

    public function toggleActive(AttendanceStatus $status)
    {
        $status->update(['is_active' => ! $status->is_active]);

        return back()->with('success', 'Status updated.');
    }
}
