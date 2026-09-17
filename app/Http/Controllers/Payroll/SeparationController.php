<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSeparation;
use App\Models\ExperienceLetter;
use App\Support\PayrollContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SeparationController extends Controller
{
    private function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'separation_type' => ['required', Rule::in(EmployeeSeparation::TYPES)],
            'resignation_date' => ['required', 'date'],
            'last_working_date' => ['required', 'date', 'after_or_equal:resignation_date'],
            'reason' => ['required', 'string'],
            'remarks' => ['nullable', 'string'],
            'status' => ['required', Rule::in(EmployeeSeparation::STATUSES)],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
        ];
    }

    private function messages(): array
    {
        return [
            'last_working_date.after_or_equal' => 'The last working day cannot be before the resignation date.',
            'document.mimes' => 'Upload the acceptance as a PDF or an image.',
        ];
    }

    public function store(Request $request)
    {
        $company = PayrollContext::currentOrFail();
        $data = $request->validate($this->rules(), $this->messages());

        $employee = Employee::where('payroll_company_id', $company->id)->findOrFail($data['employee_id']);

        if (EmployeeSeparation::where('employee_id', $employee->id)->whereNull('rejoined_at')->exists()) {
            return back()->with('error', 'This employee already has an open separation record.');
        }

        $data['payroll_company_id'] = $company->id;
        $data['created_by'] = Auth::id();
        $data['document_path'] = $request->file('document')?->store('payroll/separations', 'public');
        unset($data['document']);

        DB::transaction(function () use ($data, $employee) {
            EmployeeSeparation::create($data);

            // Once relieved the employee drops out of attendance and payroll
            if ($data['status'] === 'Relieved') {
                $employee->update(['is_active' => false]);
            }
        });

        return back()->with('success', 'Separation recorded for '.$employee->name.'.');
    }

    public function update(Request $request, EmployeeSeparation $separation)
    {
        $data = $request->validate($this->rules(), $this->messages());

        if ($request->hasFile('document')) {
            $data['document_path'] = $request->file('document')->store('payroll/separations', 'public');
        }
        unset($data['document']);

        DB::transaction(function () use ($data, $separation) {
            $separation->update($data);

            $employee = $separation->employee;
            if ($employee && ! $separation->hasRejoined()) {
                $employee->update(['is_active' => $data['status'] !== 'Relieved']);
            }
        });

        return back()->with('success', 'Separation updated.');
    }

    public function destroy(EmployeeSeparation $separation)
    {
        if (! Auth::user()->isAdmin()) {
            return back()->with('error', 'Only Admin users can delete a separation record.');
        }

        $employee = $separation->employee;
        $separation->delete();

        // Removing the record puts the employee back into active payroll
        $employee?->update(['is_active' => true]);

        return back()->with('success', 'Separation record removed and the employee reactivated.');
    }

    /**
     * Takes a former employee back on, keeping their payroll history intact.
     */
    public function rejoin(Request $request, EmployeeSeparation $separation)
    {
        $data = $request->validate([
            'joining_date' => ['required', 'date'],
            'designation' => ['required', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'salary' => ['required', 'numeric', 'min:0'],
            'daily_working_hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'contact_number' => ['nullable', 'string', 'max:15'],
            'address' => ['nullable', 'string', 'max:255'],
            'payment_mode' => ['required', Rule::in(['Cash', 'Bank'])],
            'bank_name' => ['nullable', 'required_if:payment_mode,Bank', 'string', 'max:150'],
            'account_holder_name' => ['nullable', 'required_if:payment_mode,Bank', 'string', 'max:150'],
            'account_number' => ['nullable', 'required_if:payment_mode,Bank', 'string', 'max:50'],
            'ifsc_code' => ['nullable', 'required_if:payment_mode,Bank', 'string', 'max:20'],
            'branch_name' => ['nullable', 'string', 'max:150'],
        ]);

        $employee = $separation->employee;
        abort_if($employee === null, 404);

        DB::transaction(function () use ($employee, $separation, $data) {
            $employee->update(array_merge($data, ['is_active' => true]));
            $separation->update(['rejoined_at' => $data['joining_date']]);
        });

        return back()->with('success', $employee->name.' has been rejoined and is active again.');
    }

    public function view(EmployeeSeparation $separation)
    {
        $separation->load('employee', 'company', 'creator');

        return Pdf::loadView('payroll.pdf.separation', ['separation' => $separation])
            ->stream('separation-'.optional($separation->employee)->employee_code.'.pdf');
    }

    /**
     * Experience letter, generated from the company template.
     */
    public function experienceLetter(EmployeeSeparation $separation)
    {
        $separation->load('employee', 'company');
        $company = $separation->company;
        $letter = ExperienceLetter::where('payroll_company_id', $company->id)->first();

        abort_if($letter === null, 404, 'No experience letter template configured for this company.');

        if (! $separation->canIssueExperienceLetter()) {
            return back()->with('error', 'An experience letter can only be issued once the employee is marked Relieved.');
        }

        return Pdf::loadView('payroll.pdf.experience-letter', compact('letter', 'separation', 'company'))
            ->stream('experience-letter-'.optional($separation->employee)->employee_code.'.pdf');
    }
}
