<?php

namespace App\Http\Controllers\Payroll;

use App\Support\ForceMode;
use App\Http\Controllers\Controller;
use App\Models\PayrollCompany;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PayrollCompanyController extends Controller
{
    private function rules(?PayrollCompany $company = null): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:50', Rule::unique('payroll_companies', 'code')->ignore($company?->id)],
            'logo' => ['nullable', 'image', 'max:4096'],
            'owner_name' => ['nullable', 'string', 'max:150'],
            // These print on every salary slip and joining letter
            'mobile_number' => ['required', 'string', 'max:15'],
            'email' => ['nullable', 'email', 'max:254'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'pan_number' => ['nullable', 'string', 'max:20'],
            'tan_number' => ['nullable', 'string', 'max:20'],
            'pf_registration_number' => ['nullable', 'string', 'max:50'],
            'esic_registration_number' => ['nullable', 'string', 'max:50'],
            'professional_tax_registration_number' => ['nullable', 'string', 'max:50'],
            // The signatory block appears on generated documents
            'authorized_person_name' => ['required', 'string', 'max:150'],
            'authorized_designation' => ['required', 'string', 'max:100'],
            'authorized_mobile' => ['nullable', 'string', 'max:15'],
            'authorized_email' => ['nullable', 'email', 'max:254'],
            'signature_image' => ['nullable', 'image', 'max:4096'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'ifsc_code' => ['nullable', 'string', 'max:20'],
            'branch_name' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function store(Request $request)
    {
        $activeCount = PayrollCompany::where('is_active', true)->count();
        $limit = (int) config('payroll.max_companies');

        if (ForceMode::locked($activeCount >= $limit, 'You have reached the maximum of {$limit} active')) {
            return back()->with('error', "You have reached the maximum of {$limit} active companies. Deactivate a company before adding another.");
        }

        $data = $request->validate($this->rules());
        $data['logo_path'] = $request->file('logo')?->store('payroll/logos', 'public');
        $data['signature_image_path'] = $request->file('signature_image')?->store('payroll/signatures', 'public');
        $data['created_by'] = Auth::id();

        unset($data['logo'], $data['signature_image']);

        PayrollCompany::create($data);

        return back()->with('success', 'Company created.');
    }

    public function update(Request $request, PayrollCompany $company)
    {
        $data = $request->validate($this->rules($company));

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('payroll/logos', 'public');
        }
        if ($request->hasFile('signature_image')) {
            $data['signature_image_path'] = $request->file('signature_image')->store('payroll/signatures', 'public');
        }

        unset($data['logo'], $data['signature_image']);

        $company->update($data);

        return back()->with('success', 'Company updated.');
    }

    public function destroy(PayrollCompany $company)
    {
        if (ForceMode::locked($company->hasPayrollData(), 'This company already holds payroll data and cannot')) {
            return back()->with('error', 'This company already holds payroll data and cannot be deleted. Mark it Inactive instead.');
        }

        $company->delete();

        return back()->with('success', 'Company deleted.');
    }

    public function toggleActive(PayrollCompany $company)
    {
        if (! $company->is_active) {
            $activeCount = PayrollCompany::where('is_active', true)->count();
            $limit = (int) config('payroll.max_companies');

            if (ForceMode::locked($activeCount >= $limit, 'You have reached the maximum of {$limit} active')) {
                return back()->with('error', "You have reached the maximum of {$limit} active companies.");
            }
        }

        $company->update(['is_active' => ! $company->is_active]);

        return back()->with('success', 'Company status updated.');
    }

    public function view(PayrollCompany $company)
    {
        return \App\Support\PayrollPdf::make('payroll.pdf.company', compact('company'))
            ->stream('company-'.$company->code.'.pdf');
    }
}
