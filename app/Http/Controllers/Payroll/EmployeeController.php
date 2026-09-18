<?php

namespace App\Http\Controllers\Payroll;

use App\Support\ForceMode;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Support\PayrollContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    private function rules(int $companyId, ?Employee $employee = null): array
    {
        return [
            'employee_code' => [
                'required', 'string', 'max:50',
                Rule::unique('employees', 'employee_code')
                    ->where('payroll_company_id', $companyId)
                    ->ignore($employee?->id),
            ],
            'name' => ['required', 'string', 'max:150'],
            'photo' => ['nullable', 'image', 'max:4096'],
            // Designation prints on the salary slip and joining letter
            'designation' => ['required', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'responsibilities' => ['nullable', 'string'],
            'joining_date' => ['required', 'date'],
            'salary' => ['required', 'numeric', 'min:0'],
            'daily_working_hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'contact_number' => ['nullable', 'string', 'max:15'],
            'address' => ['nullable', 'string', 'max:255'],
            'payment_mode' => ['required', Rule::in(['Cash', 'Bank'])],
            // Bank details only matter when salary is actually routed to a bank
            'bank_name' => ['nullable', 'required_if:payment_mode,Bank', 'string', 'max:150'],
            'account_holder_name' => ['nullable', 'required_if:payment_mode,Bank', 'string', 'max:150'],
            'account_number' => ['nullable', 'required_if:payment_mode,Bank', 'string', 'max:50'],
            'ifsc_code' => ['nullable', 'required_if:payment_mode,Bank', 'string', 'max:20'],
            'branch_name' => ['nullable', 'string', 'max:150'],
            'id_proof_type_id' => ['nullable', 'exists:id_proof_types,id'],
            'id_proof_number' => ['nullable', 'string', 'max:50'],
            'id_proof_image' => ['nullable', 'image', 'max:4096'],
            'id_proof_back_image' => ['nullable', 'image', 'max:4096'],
            'resume' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
            'email' => ['nullable', 'email', 'max:254'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'country_and_pincode' => ['nullable', 'string', 'max:100'],
            'qualification' => ['nullable', 'string', 'max:150'],
            'qualification_institution' => ['nullable', 'string', 'max:150'],
            'skills' => ['nullable', 'string', 'max:2000'],
            'deductions' => ['nullable', 'array'],
            'deductions.*.deduction_id' => ['nullable', 'exists:deductions,id'],
            'deductions.*.deduction_type' => ['nullable', Rule::in(['One Time', 'Monthly'])],
            'deductions.*.amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    private function messages(): array
    {
        return [
            'employee_code.unique' => 'This Employee ID is already used within the selected company.',
            'bank_name.required_if' => 'Bank name is required when the payment mode is Bank.',
            'account_holder_name.required_if' => 'Account holder name is required when the payment mode is Bank.',
            'account_number.required_if' => 'Account number is required when the payment mode is Bank.',
            'ifsc_code.required_if' => 'IFSC code is required when the payment mode is Bank.',
        ];
    }

    public function store(Request $request)
    {
        $company = PayrollContext::currentOrFail();

        $data = $request->validate($this->rules($company->id), $this->messages());
        $assignments = $data['deductions'] ?? [];
        unset($data['deductions']);

        $data['payroll_company_id'] = $company->id;
        $data['created_by'] = Auth::id();
        $data['photo_path'] = $request->file('photo')?->store('payroll/employees', 'public');
        $data['id_proof_image_path'] = $request->file('id_proof_image')?->store('payroll/id-proofs', 'public');
        $data['id_proof_back_image_path'] = $request->file('id_proof_back_image')?->store('payroll/id-proofs', 'public');
        $data['resume_path'] = $request->file('resume')?->store('payroll/resumes', 'public');
        unset($data['photo'], $data['id_proof_image'], $data['id_proof_back_image'], $data['resume']);

        DB::transaction(function () use ($data, $assignments) {
            $employee = Employee::create($data);
            $this->syncDeductions($employee, $assignments);
        });

        return back()->with('success', 'Employee added.');
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $request->validate($this->rules($employee->payroll_company_id, $employee), $this->messages());
        $assignments = $data['deductions'] ?? [];
        unset($data['deductions']);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('payroll/employees', 'public');
        }
        if ($request->hasFile('id_proof_image')) {
            $data['id_proof_image_path'] = $request->file('id_proof_image')->store('payroll/id-proofs', 'public');
        }
        if ($request->hasFile('id_proof_back_image')) {
            $data['id_proof_back_image_path'] = $request->file('id_proof_back_image')->store('payroll/id-proofs', 'public');
        }
        if ($request->hasFile('resume')) {
            $data['resume_path'] = $request->file('resume')->store('payroll/resumes', 'public');
        }
        unset($data['photo'], $data['id_proof_image'], $data['id_proof_back_image'], $data['resume']);

        DB::transaction(function () use ($employee, $data, $assignments) {
            $employee->update($data);
            $this->syncDeductions($employee, $assignments);
        });

        return back()->with('success', 'Employee updated.');
    }

    private function syncDeductions(Employee $employee, array $assignments): void
    {
        $employee->deductions()->whereNotIn('id', collect($assignments)->pluck('id')->filter())->delete();

        foreach ($assignments as $row) {
            if (empty($row['deduction_id'])) {
                continue;
            }

            EmployeeDeduction::updateOrCreate(
                ['id' => $row['id'] ?? null],
                [
                    'employee_id' => $employee->id,
                    'deduction_id' => $row['deduction_id'],
                    'deduction_type' => $row['deduction_type'] ?? 'Monthly',
                    'amount' => $row['amount'] ?? 0,
                ]
            );
        }
    }

    public function destroy(Employee $employee)
    {
        if (ForceMode::locked($blocker = $employee->blockingDependency(), 'This employee is already used in {$blocker} and')) {
            return back()->with('error', "This employee is already used in {$blocker} and cannot be deleted. Remove the dependent records first, or mark the employee Inactive.");
        }

        $employee->delete();

        return back()->with('success', 'Employee deleted.');
    }

    public function toggleActive(Employee $employee)
    {
        $employee->update(['is_active' => ! $employee->is_active]);

        return back()->with('success', 'Employee status updated.');
    }

    public function view(Employee $employee)
    {
        $employee->load('deductions.deduction', 'idProofType', 'company');

        return Pdf::loadView('payroll.pdf.employee', compact('employee'))
            ->stream('employee-'.$employee->employee_code.'.pdf');
    }
}
