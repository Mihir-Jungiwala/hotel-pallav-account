<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\FoodCharges;
use App\Support\PayrollContext;
use App\Support\PayrollScope;
use Illuminate\Http\Request;

/**
 * Food Charges: who in this company eats at Pallav Food, and the month's bill.
 * Both Hotel Pallav and Pallav Food have it; the price itself is Pallav
 * Food's alone and is read here, never set (see FoodPriceController).
 */
class FoodChargeController extends Controller
{
    private function company()
    {
        $company = PayrollContext::currentOrFail();

        abort_unless($company->servesMeals(), 404);

        return $company;
    }

    public function index(Request $request)
    {
        $company = $this->company();
        $monthStart = SalaryReportController::resolveMonth($company->id, $request);

        return view('payroll.pages.food-charges', [
            'company' => $company,
            'monthStart' => $monthStart,
            'rate' => FoodCharges::currentRate(),
            'food' => FoodCharges::statement($company, $monthStart->year, $monthStart->month),
            // No bill exists until the month's salary has been generated
            'generated' => FoodCharges::isGenerated($company, $monthStart->year, $monthStart->month),
            // Everyone who could eat there, so the switches are all in one list
            'staff' => Employee::where('payroll_company_id', $company->id)
                ->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
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
