<?php

namespace App\Http\Controllers\Payroll;

use App\Support\ForceMode;
use App\Http\Controllers\Controller;
use App\Models\Deduction;
use App\Support\PayrollContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeductionController extends Controller
{
    public function index()
    {
        $company = PayrollContext::currentOrFail();

        return view('payroll.pages.deduction', [
            'company' => $company,
            // Newest deduction first, so one just added is where it was expected
            'deductions' => Deduction::where('payroll_company_id', $company->id)
                ->withCount('assignments')
                ->orderByDesc('created_at')->orderByDesc('id')->get(),
        ]);
    }

    private function rules(int $companyId, ?Deduction $deduction = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('deductions', 'name')
                    ->where('payroll_company_id', $companyId)
                    ->ignore($deduction?->id),
            ],
            'amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function store(Request $request)
    {
        $company = PayrollContext::currentOrFail();

        $data = $request->validate(
            $this->rules($company->id),
            ['name.unique' => 'A deduction with this name already exists for this company.']
        );
        $data['payroll_company_id'] = $company->id;

        Deduction::create($data);

        return back()->with('success', 'Deduction added.');
    }

    public function update(Request $request, Deduction $deduction)
    {
        \App\Support\PayrollScope::ensure($deduction);

        $data = $request->validate(
            $this->rules($deduction->payroll_company_id, $deduction),
            ['name.unique' => 'A deduction with this name already exists for this company.']
        );

        $deduction->update($data);

        return back()->with('success', 'Deduction updated.');
    }

    public function destroy(Deduction $deduction)
    {
        \App\Support\PayrollScope::ensure($deduction);

        if (ForceMode::locked($deduction->assignments()->exists(), 'This deduction is already assigned to an employee')) {
            return back()->with('error', 'This deduction is already assigned to an employee and cannot be deleted.');
        }

        $deduction->delete();

        return back()->with('success', 'Deduction deleted.');
    }

    public function toggleActive(Deduction $deduction)
    {
        \App\Support\PayrollScope::ensure($deduction);

        $deduction->update(['is_active' => ! $deduction->is_active]);

        return back()->with('success', 'Status updated.');
    }
}
