<?php

namespace App\Http\Controllers\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollCompany;
use App\Models\PayrollLog;
use App\Support\PayrollContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

    /** A printed log is capped: past this it is a book, and the dates or filters should narrow it. */
    private const PDF_LIMIT = 400;

    /** Entities that move money, for the "Money" view. */
    private const MONEY_ENTITIES = ['Salary slip', 'Advance', 'Bonus / incentive', 'Meal price', 'Meals record', 'Attendance'];

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

    /** The same list the page shows, as a document with every detail. */
    public function pdf(Request $request)
    {
        $company = PayrollContext::current();
        $query = $this->query($request, $company);

        $total = (clone $query)->count();
        $rows = $query->limit(self::PDF_LIMIT)->get();

        // Rendered once and kept: a long log is many pages by nature, so the
        // fit-to-one-page retries that other documents use would only cost time
        $pdf = app('dompdf.wrapper')
            ->loadView('payroll.pdf.log', [
                'company' => $company,
                'rows' => $rows,
                'filters' => $this->describeFilters($request),
                'truncated' => $total > $rows->count(),
                'compact' => 0,
            ])
            ->setPaper('a4', 'portrait');

        return (new \App\Support\RenderedPdf($pdf->output()))
            ->stream('payroll-log-'.($company ? \Illuminate\Support\Str::slug($company->name).'-' : '').now()->format('Y-m-d').'.pdf');
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

    /** The filters in force, in words, so the printed page says what it is a slice of. */
    private function describeFilters(Request $request): array
    {
        $parts = [];

        if ($request->filled('view') && isset(self::QUICK_VIEWS[$request->string('view')->toString()])) {
            $parts[] = self::QUICK_VIEWS[$request->string('view')->toString()][0];
        }
        if ($request->filled('q')) {
            $parts[] = 'search "'.$request->string('q')->toString().'"';
        }
        if ($request->filled('action')) {
            $parts[] = 'action: '.(PayrollLog::ACTIONS[$request->string('action')->toString()] ?? $request->string('action')->toString());
        }
        if ($request->filled('entity')) {
            $parts[] = 'what: '.$request->string('entity')->toString();
        }
        if ($request->filled('user')) {
            $parts[] = 'who: '.$request->string('user')->toString();
        }
        if ($request->filled('status')) {
            $parts[] = 'outcome: '.$request->string('status')->toString();
        }
        if ($request->filled('from')) {
            $parts[] = 'from '.Carbon::parse($request->input('from'))->format('j M Y');
        }
        if ($request->filled('to')) {
            $parts[] = 'to '.Carbon::parse($request->input('to'))->format('j M Y');
        }

        return $parts;
    }}
