<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollCompany;
use App\Models\SalaryProcessing;
use App\Support\PayrollContext;
use Illuminate\Http\Request;

/**
 * Company Listing - the payroll module's landing page.
 *
 * This is the one payroll screen that works without a company selected,
 * because choosing one is what it is for. Opening a company remembers it for
 * the session and goes straight to a working page, so nobody lands on an
 * empty "now pick a feature" screen in between.
 */
class PayrollController extends Controller
{
    /** Where opening a company takes you: that company's own landing page. */
    private const FIRST_PAGE = 'payroll.dashboard.index';

    public function index(Request $request)
    {
        // Opening a company from anywhere - the listing, the switcher, a
        // bookmark - routes through here so the session is the only place the
        // current company is ever set.
        if ($request->filled('current_company')) {
            $requested = $request->string('current_company')->toString();

            if ($requested === PayrollContext::SENTINEL) {
                PayrollContext::forget();

                return redirect()->route('payroll.index');
            }

            $company = PayrollCompany::where('is_active', true)->find($requested);

            if ($company === null) {
                return redirect()->route('payroll.index')
                    ->with('error', 'That company is no longer available.');
            }

            PayrollContext::remember((string) $company->id);

            // Carry them back to the page they were refused earlier, if any
            $intended = session()->pull('payroll.intended');

            return redirect($intended ?: route(self::FIRST_PAGE));
        }

        PayrollCompany::ensureFixed();

        // Being on the listing means no company is open. Leaving the previous
        // one in the session made the menu beside it keep offering that
        // company's pages, as if you were still inside it.
        PayrollContext::forget();

        $search = trim((string) $request->input('q'));

        $companies = PayrollCompany::withCount([
            'employees',
            'employees as active_employees_count' => fn ($q) => $q->where('is_active', true),
        ])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('owner_name', 'like', "%{$search}%")))
            // Live companies first, then the most recently added
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return view('payroll.companies.index', [
            'companies' => $companies,
            'search' => $search,
            'totalStaff' => Employee::where('is_active', true)->count(),
            'unpaidCount' => SalaryProcessing::where('payment_status', '!=', 'Paid')->count(),
        ]);
    }
}
