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
            // Hotel Pallav only: what Pallav Food charges per employee per month
            'food_charge_amount' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'food_charge_from' => ['nullable', 'date'],
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

    public function update(Request $request, PayrollCompany $company)
    {
        $data = $request->validate($this->rules($company));

        // The name and code are fixed
        unset($data['name'], $data['code']);

        $this->saveFoodCharge($company, $data['food_charge_amount'] ?? null, $data['food_charge_from'] ?? null);
        unset($data['food_charge_amount'], $data['food_charge_from']);

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

    /** A changed amount starts a new rate from its month; earlier months keep the amount they had. */
    private function saveFoodCharge(PayrollCompany $company, $amount, $from): void
    {
        if (! $company->paysFoodCharges() || $amount === null || $amount === '') {
            return;
        }

        $current = \App\Support\FoodCharges::currentRate($company);

        if ($current !== null && abs($current - (float) $amount) < 0.005) {
            return;
        }

        \App\Models\FoodChargeRate::create([
            'payroll_company_id' => $company->id,
            'monthly_amount' => $amount,
            'effective_from' => \Illuminate\Support\Carbon::parse($from ?: now())->startOfMonth(),
            'created_by' => Auth::id(),
        ]);
    }

    public function view(PayrollCompany $company)
    {
        return \App\Support\PayrollPdf::make('payroll.pdf.company', compact('company'))
            ->stream('company-'.$company->code.'.pdf');
    }
}
