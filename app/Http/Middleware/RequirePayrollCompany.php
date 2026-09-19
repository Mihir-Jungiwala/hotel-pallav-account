<?php

namespace App\Http\Middleware;

use App\Support\PayrollContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every individual payroll page works inside one selected company. Reaching
 * one of them without a company in the session - a bookmark, a shared link,
 * or a company that was deactivated while it was open - lands on the Company
 * Listing instead of a half-empty screen, with the page they wanted
 * remembered so they carry on there once they have picked one.
 */
class RequirePayrollCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        if (PayrollContext::current() !== null) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(404);
        }

        session(['payroll.intended' => $request->fullUrl()]);

        return redirect()->route('payroll.index')
            ->with('error', 'Choose a company first - payroll always works inside one company.');
    }
}
