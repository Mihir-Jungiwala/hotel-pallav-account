<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\JoiningLetter;
use App\Support\PayrollContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class JoiningLetterController extends Controller
{
    public function save(Request $request)
    {
        $company = PayrollContext::currentOrFail();

        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'introduction_content' => ['nullable', 'string'],
            'roles_responsibilities' => ['nullable', 'string'],
            'terms_conditions' => ['nullable', 'string'],
            'closing_message' => ['nullable', 'string'],
            'use_company_signatory' => ['nullable', 'boolean'],
            'authorized_name' => ['nullable', 'string', 'max:150'],
            'authorized_designation' => ['nullable', 'string', 'max:100'],
            'signature_image' => ['nullable', 'image', 'max:4096'],
            'authorized_closing_text' => ['nullable', 'string'],
            'acceptance_heading' => ['nullable', 'string', 'max:255'],
            'acceptance_content' => ['nullable', 'string'],
            'acceptance_closing_text' => ['nullable', 'string'],
        ]);

        $data['use_company_signatory'] = $request->boolean('use_company_signatory');

        if ($request->hasFile('signature_image')) {
            $data['signature_image_path'] = $request->file('signature_image')->store('payroll/signatures', 'public');
        }
        unset($data['signature_image']);

        // Only one template per company
        JoiningLetter::updateOrCreate(['payroll_company_id' => $company->id], $data);

        return back()->with('success', 'Joining letter template saved.');
    }

    public function destroy(JoiningLetter $joiningLetter)
    {
        \App\Support\PayrollScope::ensure($joiningLetter);

        $joiningLetter->delete();

        return back()->with('success', 'Joining letter template deleted.');
    }

    public function toggleActive(JoiningLetter $joiningLetter)
    {
        \App\Support\PayrollScope::ensure($joiningLetter);

        $joiningLetter->update(['is_active' => ! $joiningLetter->is_active]);

        return back()->with('success', 'Status updated.');
    }

    /**
     * Renders the template for one employee with all placeholders replaced.
     */
    public function generate(Employee $employee)
    {
        \App\Support\PayrollScope::ensure($employee);

        $company = $employee->company;
        $letter = JoiningLetter::where('payroll_company_id', $company->id)->first();

        abort_if($letter === null, 404, 'No joining letter template configured for this company.');

        return Pdf::loadView('payroll.pdf.joining-letter', compact('letter', 'employee', 'company'))
            ->stream('joining-letter-'.$employee->employee_code.'.pdf');
    }
}
