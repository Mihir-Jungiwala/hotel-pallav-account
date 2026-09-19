<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollCompany;
use App\Models\PayrollLog;
use App\Support\PayrollContext;
use Illuminate\Http\Request;

/**
 * The payroll log: everything done in payroll, newest first. SuperAdmin only.
 * With no company selected it reads across every company; with one selected,
 * that company alone - the same scope as Payroll Master.
 */
class PayrollLogController extends Controller
{
    public function index(Request $request)
    {
        // The scope chooser on the page: "all" or a company id
        if ($request->filled('scope')) {
            $scope = $request->string('scope')->toString();
            $company = $scope === 'all' ? null : PayrollCompany::where('is_active', true)->find($scope);

            $company ? PayrollContext::remember((string) $company->id) : PayrollContext::forget();

            return redirect()->route('payroll.log.index', $request->except('scope'));
        }

        $company = PayrollContext::current();
        $inScope = fn () => PayrollLog::query()->when($company, fn ($q) => $q->where('payroll_company_id', $company->id));

        $search = trim((string) $request->input('q'));
        $from = $request->input('from');
        $to = $request->input('to');

        $logs = $inScope()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('summary', 'like', "%{$search}%")
                ->orWhere('subject_label', 'like', "%{$search}%")
                ->orWhere('user_name', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")
                ->orWhere('details', 'like', "%{$search}%")))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->input('action')))
            ->when($request->filled('entity'), fn ($q) => $q->where('entity', $request->input('entity')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('payroll.pages.log', [
            'company' => $company,
            'companies' => PayrollContext::selectable(),
            'logs' => $logs,
            'entities' => $inScope()->distinct()->orderBy('entity')->pluck('entity'),
            'stats' => [
                'total' => $inScope()->count(),
                'today' => $inScope()->whereDate('created_at', today())->count(),
                'emails' => $inScope()->where('action', 'emailed')->where('status', 'success')->count(),
                'failed' => $inScope()->where('status', 'failed')->count(),
            ],
            'filters' => $request->only('q', 'action', 'entity', 'status', 'from', 'to'),
        ]);
    }
}
