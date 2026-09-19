<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\ExperienceLetter;
use App\Support\PayrollContext;
use Illuminate\Http\Request;

class ExperienceLetterController extends Controller
{
    public function index()
    {
        $company = PayrollContext::currentOrFail();

        return view('payroll.pages.experience-letter', [
            'company' => $company,
            'letter' => ExperienceLetter::where('payroll_company_id', $company->id)->first(),
            // Everyone who has actually left, most recent departure first
            'separations' => \App\Models\EmployeeSeparation::with('employee')
                ->where('payroll_company_id', $company->id)
                ->whereNull('rejoined_at')
                ->where('status', 'Relieved')
                ->orderByDesc('last_working_date')->orderByDesc('id')->get(),
        ]);
    }

    public function save(Request $request)
    {
        $company = PayrollContext::currentOrFail();

        $data = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'body_content' => ['nullable', 'string'],
            'conduct_remarks' => ['nullable', 'string'],
            'closing_message' => ['nullable', 'string'],
            'use_company_signatory' => ['nullable', 'boolean'],
            'authorized_name' => ['nullable', 'string', 'max:150'],
            'authorized_designation' => ['nullable', 'string', 'max:100'],
            'signature_image' => ['nullable', 'image', 'max:4096'],
            'authorized_closing_text' => ['nullable', 'string'],
        ]);

        $data['use_company_signatory'] = $request->boolean('use_company_signatory');

        if ($request->hasFile('signature_image')) {
            $data['signature_image_path'] = $request->file('signature_image')->store('payroll/signatures', 'public');
        }
        unset($data['signature_image']);

        ExperienceLetter::updateOrCreate(['payroll_company_id' => $company->id], $data);

        return back()->with('success', 'Experience letter template saved.');
    }

    public function destroy(ExperienceLetter $experienceLetter)
    {
        \App\Support\PayrollScope::ensure($experienceLetter);

        $experienceLetter->delete();

        return back()->with('success', 'Experience letter template deleted.');
    }

    public function toggleActive(ExperienceLetter $experienceLetter)
    {
        \App\Support\PayrollScope::ensure($experienceLetter);

        $experienceLetter->update(['is_active' => ! $experienceLetter->is_active]);

        return back()->with('success', 'Status updated.');
    }
}
