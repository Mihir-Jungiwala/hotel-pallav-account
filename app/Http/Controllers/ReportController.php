<?php

namespace App\Http\Controllers;

use App\Services\Reports\AccountReports;
use App\Support\UnitContext;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** The accounts reports: on screen first, then the same figures as a PDF. */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        [$type, $from, $to] = $this->criteria($request);

        $report = (new AccountReports($from, $to))->build($type);

        return view('reports.index', [
            'types' => AccountReports::TYPES,
            'type' => $type,
            'meta' => AccountReports::TYPES[$type],
            'from' => $from,
            'to' => $to,
            'rows' => $report['rows'],
            'totals' => $report['totals'],
            'unitLabel' => UnitContext::label(),
        ]);
    }

    public function download(Request $request)
    {
        [$type, $from, $to] = $this->criteria($request);

        $report = (new AccountReports($from, $to))->build($type);
        $meta = AccountReports::TYPES[$type];

        $pdf = Pdf::loadView('reports.pdf.report', [
            'type' => $type,
            'meta' => $meta,
            'from' => $from,
            'to' => $to,
            'rows' => $report['rows'],
            'totals' => $report['totals'],
            'unitLabel' => UnitContext::label(),
        ])->setPaper('a4', in_array($type, ['cash-book', 'bill-register', 'receivables'], true) ? 'landscape' : 'portrait');

        $name = str($meta['name'])->slug().'-'.$from->format('Ymd').'-'.$to->format('Ymd').'.pdf';

        return $pdf->stream($name);
    }

    /** What to report on: the type, and the period. Defaults to this month. */
    private function criteria(Request $request): array
    {
        $requested = $request->string('type')->toString();
        $type = array_key_exists($requested, AccountReports::TYPES) ? $requested : 'cash-book';

        $from = $request->filled('from_date')
            ? Carbon::parse($request->string('from_date')->toString())
            : Carbon::now()->startOfMonth();

        $to = $request->filled('to_date')
            ? Carbon::parse($request->string('to_date')->toString())
            : Carbon::now();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [$type, $from->startOfDay(), $to->startOfDay()];
    }
}
