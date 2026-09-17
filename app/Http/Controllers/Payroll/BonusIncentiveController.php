<?php

namespace App\Http\Controllers\Payroll;

use App\Support\ForceMode;
use App\Http\Controllers\Controller;
use App\Models\BonusIncentive;
use App\Models\SalaryProcessing;
use App\Support\PayrollContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class BonusIncentiveController extends Controller
{
    private function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'entry_date' => ['required', 'date', 'before_or_equal:now'],
            'type' => ['required', Rule::in(['Bonus', 'Incentive'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'remarks' => ['nullable', 'string'],
        ];
    }

    private function messages(): array
    {
        return [
            'amount.gt' => 'Amount must be greater than zero.',
            'entry_date.before_or_equal' => 'Date cannot be in the future.',
        ];
    }

    private function periodProcessed(BonusIncentive $entry): bool
    {
        $date = Carbon::parse($entry->entry_date);

        return SalaryProcessing::where('payroll_company_id', $entry->payroll_company_id)
            ->where('employee_id', $entry->employee_id)
            ->where('year', $date->year)
            ->where('month', $date->month)
            ->exists();
    }

    public function store(Request $request)
    {
        $company = PayrollContext::currentOrFail();

        $data = $request->validate($this->rules(), $this->messages());
        $data['payroll_company_id'] = $company->id;
        $data['created_by'] = Auth::id();

        BonusIncentive::create($data);

        return back()->with('success', 'Entry recorded.');
    }

    public function update(Request $request, BonusIncentive $entry)
    {
        if (ForceMode::locked(! Auth::user()->isAdmin(), 'Only Admin users can update these records')) {
            return back()->with('error', 'Only Admin users can update these records.');
        }

        if (ForceMode::locked($this->periodProcessed($entry), 'Salary Processing is already completed for this period,')) {
            return back()->with('error', 'Salary Processing is already completed for this period, so this entry cannot be changed.');
        }

        $entry->update($request->validate($this->rules(), $this->messages()));

        return back()->with('success', 'Entry updated.');
    }

    public function destroy(BonusIncentive $entry)
    {
        if (ForceMode::locked(! Auth::user()->isAdmin(), 'Only Admin users can delete these records')) {
            return back()->with('error', 'Only Admin users can delete these records.');
        }

        if (ForceMode::locked($this->periodProcessed($entry), 'Salary Processing is already completed for this period,')) {
            return back()->with('error', 'Salary Processing is already completed for this period, so this entry cannot be deleted.');
        }

        $entry->delete();

        return back()->with('success', 'Entry deleted.');
    }

    public function view(BonusIncentive $entry)
    {
        $entry->load('employee', 'company', 'creator');

        return Pdf::loadView('payroll.pdf.bonus-incentive', compact('entry'))
            ->stream('bonus-incentive-'.$entry->id.'.pdf');
    }
}
