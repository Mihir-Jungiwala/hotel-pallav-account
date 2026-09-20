<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\FoodChargeRate;
use App\Support\FoodCharges;
use App\Support\PayrollContext;
use App\Support\PayrollScope;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Food Charges: everything about what Hotel Pallav owes Pallav Food for its
 * staff's meals, on one page - the amount, who eats there, and the month's
 * bill. It exists only for the company that pays it.
 */
class FoodChargeController extends Controller
{
    private function company()
    {
        $company = PayrollContext::currentOrFail();

        abort_unless($company->paysFoodCharges(), 404);

        return $company;
    }

    public function index(Request $request)
    {
        $company = $this->company();
        $monthStart = SalaryReportController::resolveMonth($company->id, $request);

        return view('payroll.pages.food-charges', [
            'company' => $company,
            'monthStart' => $monthStart,
            'rate' => FoodCharges::currentRate($company),
            'rateHistory' => FoodChargeRate::where('payroll_company_id', $company->id)
                ->orderByDesc('effective_from')->orderByDesc('id')->get(),
            'food' => FoodCharges::statement($company, $monthStart->year, $monthStart->month),
            // Everyone who could eat there, so the switches are all in one list
            'staff' => Employee::where('payroll_company_id', $company->id)
                ->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    /** A changed amount starts from its month; earlier months keep the amount they had. */
    public function saveRate(Request $request)
    {
        $company = $this->company();

        $data = $request->validate([
            'monthly_amount' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'effective_from' => ['required', 'date'],
        ]);

        $from = Carbon::parse($data['effective_from'])->startOfMonth();

        // Setting the same amount again for the same month is a no-op, not a second rate
        $existing = FoodChargeRate::where('payroll_company_id', $company->id)
            ->whereDate('effective_from', $from)->first();

        if ($existing) {
            $existing->update(['monthly_amount' => $data['monthly_amount']]);
        } else {
            FoodChargeRate::create([
                'payroll_company_id' => $company->id,
                'monthly_amount' => $data['monthly_amount'],
                'effective_from' => $from,
                'created_by' => Auth::id(),
            ]);
        }

        return back()->with('success', 'Pallav Food now charges Rs '.number_format((float) $data['monthly_amount'], 2)
            .' per employee from '.$from->format('F Y').'.');
    }

    public function destroyRate(FoodChargeRate $rate)
    {
        $company = $this->company();

        abort_unless($rate->payroll_company_id === $company->id, 404);

        $rate->delete();

        return back()->with('success', 'That amount was removed.');
    }

    /** The switch beside each person on this page, doing the same as the one on their record. */
    public function toggleEmployee(Employee $employee)
    {
        $this->company();
        PayrollScope::ensure($employee);

        $employee->update(['eats_at_pallav_food' => ! $employee->eats_at_pallav_food]);

        return back()->with('success', $employee->name.($employee->eats_at_pallav_food
            ? ' now eats at Pallav Food.'
            : ' no longer eats at Pallav Food.'));
    }
}
