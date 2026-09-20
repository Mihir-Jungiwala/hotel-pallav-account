<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollCompany;
use App\Models\PayrollLog;
use App\Support\PayrollContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The payroll log: everything done in payroll, newest first. SuperAdmin only.
 * With no company selected it reads across every company; with one selected,
 * that company alone - the same scope as Payroll Master.
 */
class PayrollLogController extends Controller
{
    /** The one-click views along the top of the page. */
    public const QUICK_VIEWS = [
        'today' => ['Today', 'bi-brightness-high'],
        'week' => ['Last 7 days', 'bi-calendar-week'],
        'emails' => ['Emails', 'bi-envelope'],
        'money' => ['Money', 'bi-cash-coin'],
        'deletions' => ['Deletions', 'bi-trash'],
        'problems' => ['Problems', 'bi-exclamation-triangle'],
    ];

    /** Entities that move money, for the "Money" view. */
    private const MONEY_ENTITIES = ['Salary slip', 'Advance', 'Bonus / incentive', 'Food charge rate', 'Attendance'];

    public function index(Request $request)
    {
        // The scope chooser on the page: "all" or a company id
        if ($request->filled('scope')) {
            $scope = $request->string('scope')->toString();
            $company = $scope === 'all' ? null : PayrollCompany::where('is_active', true)->find($scope);

            $company ? PayrollContext::remember((string) $company->id) : PayrollContext::forget();

            return redirect()->route('payroll.log.index', $request->except('scope', 'page'));
        }

        $company = PayrollContext::current();
        $filters = $request->only('q', 'action', 'entity', 'status', 'from', 'to', 'view', 'user');

        $logs = $this->query($request, $company)->paginate(30)->withQueryString();

        $scoped = fn () => PayrollLog::query()->when($company, fn ($q) => $q->where('payroll_company_id', $company->id));

        return view('payroll.pages.log', [
            'company' => $company,
            'companies' => PayrollContext::selectable(),
            'logs' => $logs,
            // Grouped by day, so a long list reads as a diary rather than a wall
            'days' => $logs->getCollection()->groupBy(fn (PayrollLog $log) => $log->created_at->toDateString()),
            'entities' => $scoped()->distinct()->orderBy('entity')->pluck('entity'),
            'people' => $scoped()->whereNotNull('user_name')->distinct()->orderBy('user_name')->pluck('user_name'),
            'quickViews' => self::QUICK_VIEWS,
            'stats' => [
                'total' => $scoped()->count(),
                'today' => $scoped()->whereDate('created_at', today())->count(),
                'emails' => $scoped()->where('action', 'emailed')->where('status', 'success')->count(),
                'failed' => $scoped()->where('status', 'failed')->count(),
            ],
            'filters' => $filters,
            'hasFilters' => (bool) array_filter($filters),
        ]);
    }

    /** The same list the page shows, as a spreadsheet. */
    public function download(Request $request): StreamedResponse
    {
        $company = PayrollContext::current();
        $rows = $this->query($request, $company)->limit(5000)->get();
        $name = 'payroll-log-'.($company ? \Illuminate\Support\Str::slug($company->name).'-' : '').now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['When', 'Company', 'Who', 'Action', 'Outcome', 'What', 'Record', 'Summary', 'Detail', 'From']);

            foreach ($rows as $log) {
                fputcsv($out, [
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->company_name,
                    $log->user_name,
                    $log->action,
                    $log->status,
                    $log->entity,
                    $log->subject_label,
                    $log->summary,
                    $this->flatten($log),
                    $log->ip_address,
                ]);
            }

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<PayrollLog> */
    private function query(Request $request, ?PayrollCompany $company)
    {
        $search = trim((string) $request->input('q'));

        return PayrollLog::query()
            ->when($company, fn ($q) => $q->where('payroll_company_id', $company->id))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('summary', 'like', "%{$search}%")
                ->orWhere('subject_label', 'like', "%{$search}%")
                ->orWhere('user_name', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")
                ->orWhere('details', 'like', "%{$search}%")))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->input('action')))
            ->when($request->filled('entity'), fn ($q) => $q->where('entity', $request->input('entity')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('user'), fn ($q) => $q->where('user_name', $request->input('user')))
            ->when($request->input('from'), fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($request->input('to'), fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->when($request->filled('view'), fn ($q) => $this->applyView($q, $request->string('view')->toString()))
            ->orderByDesc('created_at')->orderByDesc('id');
    }

    /** One click instead of three filters, for the questions people actually ask. */
    private function applyView($query, string $view)
    {
        return match ($view) {
            'today' => $query->whereDate('created_at', today()),
            'week' => $query->where('created_at', '>=', Carbon::today()->subDays(6)),
            'emails' => $query->where('action', 'emailed'),
            'money' => $query->whereIn('entity', self::MONEY_ENTITIES),
            'deletions' => $query->where('action', 'deleted'),
            'problems' => $query->where('status', 'failed'),
            default => $query,
        };
    }

    /** The detail of one entry on a single line, for the spreadsheet. */
    private function flatten(PayrollLog $log): string
    {
        if (! $log->details) {
            return '';
        }

        $parts = [];

        foreach ($log->details as $field => $value) {
            $parts[] = is_array($value)
                ? $field.': '.($value['was'] ?? '-').' -> '.($value['became'] ?? '-')
                : $field.': '.$value;
        }

        return implode('; ', $parts);
    }
}
