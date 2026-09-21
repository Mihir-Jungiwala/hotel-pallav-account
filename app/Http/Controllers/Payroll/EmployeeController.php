<?php

namespace App\Http\Controllers\Payroll;

use App\Support\ForceMode;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Services\Payroll\OfferLetterMailer;
use App\Support\PhoneCountries;
use App\Support\PayrollContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index()
    {
        $company = PayrollContext::currentOrFail();

        return view('payroll.pages.staff', [
            'company' => $company,
            // Most recent joiner first - the person most likely being set up
            'employees' => Employee::with('deductions.deduction')
                ->where('payroll_company_id', $company->id)
                ->orderByDesc('joining_date')->orderByDesc('id')->get(),
            'deductionOptions' => \App\Models\Deduction::where('payroll_company_id', $company->id)
                ->where('is_active', true)->orderBy('name')->get(),
            // Addresses the SuperAdmin approved for sharing a person's details
            'shareRecipients' => \App\Support\PayrollMasters::active('share_emails', $company),
            // Decides whether the Add form offers to email the appointment letter
            'hasJoiningTemplate' => \App\Models\JoiningLetter::where('payroll_company_id', $company->id)->where('is_active', true)->exists(),
            'rejoinable' => \App\Models\EmployeeSeparation::with('employee')
                ->where('payroll_company_id', $company->id)
                ->whereNull('rejoined_at')
                ->where('status', 'Relieved')
                ->orderByDesc('last_working_date')->get(),
        ]);
    }

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
            // Both are how the company reaches a person and how their letter
            // gets to them, so neither is optional. A number is a country code
            // plus national digits, normalised before it is checked (see
            // normalise()) and judged by that country's own rules.
            'contact_country' => ['required', 'string', Rule::in(PhoneCountries::dials())],
            'contact_number' => [
                'required', 'string',
                function ($attribute, $value, $fail) {
                    if ($error = PhoneCountries::check(request('contact_country'), $value)) {
                        $fail($error);
                    }
                },
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:60'],
            'emergency_contact_country' => ['nullable', 'string', Rule::in(PhoneCountries::dials())],
            'emergency_contact_number' => [
                'nullable', 'string',
                function ($attribute, $value, $fail) {
                    if ($error = PhoneCountries::check(request('emergency_contact_country'), $value)) {
                        $fail($error);
                    }
                },
            ],
            'send_offer_letter' => ['nullable', 'boolean'],
            'payment_mode' => ['required', Rule::in(\App\Support\PayrollMasters::choices('salary_payment_mode'))],
            // Bank details only matter when salary is actually routed to a bank
            'bank_name' => ['nullable', 'required_if:payment_mode,Bank', 'string', 'max:150'],
            'account_holder_name' => ['nullable', 'required_if:payment_mode,Bank', 'string', 'max:150'],
            'account_number' => ['nullable', 'required_if:payment_mode,Bank', 'string', 'max:50'],
            'ifsc_code' => ['nullable', 'required_if:payment_mode,Bank', 'string', 'max:20'],
            'branch_name' => ['nullable', 'string', 'max:150'],
            'id_proof_type' => ['nullable', 'string', 'max:100'],
            'id_proof_number' => ['nullable', 'string', 'max:50'],
            'id_proof_image' => ['nullable', 'image', 'max:4096'],
            'id_proof_back_image' => ['nullable', 'image', 'max:4096'],
            'resume' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:8192'],
            'email' => [
                'required', 'email', 'max:254',
                // One address per person within a company: it is where their
                // letter goes, so two people sharing one is a mistake
                Rule::unique('employees', 'email')
                    ->where('payroll_company_id', $companyId)
                    ->ignore($employee?->id),
            ],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:20', Rule::in(\App\Support\PayrollMasters::choices('gender'))],
            'nationality' => ['nullable', 'string', 'max:100'],
            'country_and_pincode' => ['nullable', 'string', 'max:100'],
            'qualification' => ['nullable', 'string', 'max:150'],
            'qualification_institution' => ['nullable', 'string', 'max:150'],
            'skills' => ['nullable', 'string', 'max:2000'],
            'deductions' => ['nullable', 'array'],
            'deductions.*.deduction_id' => ['nullable', \App\Support\PayrollScope::belongsToCompany('deductions')],
            'deductions.*.deduction_type' => ['nullable', Rule::in(['One Time', 'Monthly'])],
            'deductions.*.amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    private function messages(): array
    {
        return [
            'employee_code.unique' => 'This Employee ID is already used within the selected company.',
            'email.required' => 'An email address is needed - the appointment letter is sent to it.',
            'email.unique' => 'Another employee in this company already uses this email address.',
            'contact_number.required' => 'A mobile number is needed.',
            'bank_name.required_if' => 'Bank name is required when the payment mode is Bank.',
            'account_holder_name.required_if' => 'Account holder name is required when the payment mode is Bank.',
            'account_number.required_if' => 'Account number is required when the payment mode is Bank.',
            'ifsc_code.required_if' => 'IFSC code is required when the payment mode is Bank.',
        ];
    }

    /**
     * People type numbers as "+91 98765 43210", "+971 50 123 4567",
     * "098765-43210" or with spaces. Reduce each to a country code and its
     * national digits before validating, so every way of writing a number is
     * accepted and stored the same way. A leading "+" names the country and
     * wins over whatever the selector said.
     */
    private function normalise(Request $request): void
    {
        foreach (['contact', 'emergency_contact'] as $prefix) {
            $numberField = $prefix.'_number';
            $countryField = $prefix.'_country';

            if (! $request->filled($numberField)) {
                // Nothing typed: keep a sensible country rather than a blank one
                $request->merge([$countryField => $request->input($countryField) ?: PhoneCountries::DEFAULT]);

                continue;
            }

            [$dial, $national] = PhoneCountries::normalise(
                (string) $request->input($numberField),
                $request->input($countryField),
            );

            $request->merge([$countryField => $dial, $numberField => $national]);
        }

        if ($request->filled('email')) {
            $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        }
    }

    public function store(Request $request)
    {
        $company = PayrollContext::currentOrFail();

        $this->normalise($request);

        $data = $request->validate($this->rules($company->id), $this->messages());
        $assignments = $data['deductions'] ?? [];
        $sendLetter = (bool) ($data['send_offer_letter'] ?? false);
        unset($data['deductions'], $data['send_offer_letter']);

        $data['payroll_company_id'] = $company->id;
        $data['created_by'] = Auth::id();
        $data['photo_path'] = $request->file('photo')?->store('payroll/employees', 'public');
        $data['id_proof_image_path'] = $request->file('id_proof_image')?->store('payroll/id-proofs', 'public');
        $data['id_proof_back_image_path'] = $request->file('id_proof_back_image')?->store('payroll/id-proofs', 'public');
        $data['resume_path'] = $request->file('resume')?->store('payroll/resumes', 'public');
        unset($data['photo'], $data['id_proof_image'], $data['id_proof_back_image'], $data['resume']);

        $employee = DB::transaction(function () use ($data, $assignments) {
            $employee = Employee::create($data);
            $this->syncDeductions($employee, $assignments);

            return $employee;
        });

        if (! $sendLetter) {
            return back()->with('success', 'Employee added.');
        }

        // The staff record is already saved; whether the email goes is
        // reported alongside it, never instead of it
        $result = app(OfferLetterMailer::class)->send($employee);

        return $result['sent']
            ? back()->with('success', 'Employee added. '.$result['message'])
            : back()->with('error', 'Employee added, but '.lcfirst($result['message']));
    }

    /** Sends (or re-sends) the appointment letter to someone already on the payroll. */
    public function sendOfferLetter(Employee $employee)
    {
        \App\Support\PayrollScope::ensure($employee);

        $result = app(OfferLetterMailer::class)->send($employee);

        return back()->with($result['sent'] ? 'success' : 'error', $result['message']);
    }

    /** Emails this person's details to one of the addresses kept in Payroll Master. */
    public function share(Request $request, Employee $employee)
    {
        \App\Support\PayrollScope::ensure($employee);

        $data = $request->validate(['recipient' => ['required', 'integer']]);

        $result = app(\App\Services\Payroll\StaffShareMailer::class)->send($employee, (int) $data['recipient'], (string) Auth::user()->name);

        return back()->with($result['sent'] ? 'success' : 'error', $result['message']);
    }

    public function update(Request $request, Employee $employee)
    {
        \App\Support\PayrollScope::ensure($employee);

        $this->normalise($request);

        $data = $request->validate($this->rules($employee->payroll_company_id, $employee), $this->messages());
        $assignments = $data['deductions'] ?? [];
        // Only adding someone sends the letter; editing never does
        unset($data['deductions'], $data['send_offer_letter']);

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
        \App\Support\PayrollScope::ensure($employee);

        if (ForceMode::locked($blocker = $employee->blockingDependency(), 'This employee is already used in {$blocker} and')) {
            return back()->with('error', "This employee is already used in {$blocker} and cannot be deleted. Remove the dependent records first, or mark the employee Inactive.");
        }

        $employee->delete();

        return back()->with('success', 'Employee deleted.');
    }

    public function toggleActive(Employee $employee)
    {
        \App\Support\PayrollScope::ensure($employee);

        $employee->update(['is_active' => ! $employee->is_active]);

        return back()->with('success', 'Employee status updated.');
    }

    public function view(Employee $employee)
    {
        \App\Support\PayrollScope::ensure($employee);

        $employee->load('deductions.deduction', 'idProofType', 'company');

        return \App\Support\PayrollPdf::make('payroll.pdf.employee', compact('employee'))
            ->stream('employee-'.$employee->employee_code.'.pdf');
    }
}
