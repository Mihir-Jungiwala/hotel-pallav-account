@php $money = fn ($n) => 'Rs '.number_format((float) $n, 2); @endphp

@include('reports.partials._totals', ['cards' => ['Cash in' => $money($totals['in']), 'Cash out' => $money($totals['out']), 'Closing balance' => $money($totals['closing']), 'Busiest day' => $totals['busiest']['label'] ?? '-']])

<div class="card reveal">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-paginate="25" data-pager="#reportPager">
            <thead><tr>
                <th>Day</th><th class="text-end">Entries</th><th class="text-end">In</th>
                <th class="text-end">Out</th><th class="text-end">Net</th><th class="text-end">Running balance</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr data-row="{{ $row['label'] }}">
                    <td class="fw-semibold text-nowrap">{{ $row['label'] }}</td>
                    <td class="text-end text-muted">{{ $row['entries'] ?: '-' }}</td>
                    <td class="text-end amount-in">{{ $row['in'] ? $money($row['in']) : '-' }}</td>
                    <td class="text-end amount-out">{{ $row['out'] ? $money($row['out']) : '-' }}</td>
                    <td class="text-end fw-semibold">{{ $money($row['net']) }}</td>
                    <td class="text-end">{{ $money($row['balance']) }}</td>
                </tr>
            @empty
                    <tr><td colspan="6">
                        <div class="empty-state py-4">
                            <div class="es-icon"><i class="bi bi-calendar3"></i></div>
                            <div class="es-title">Nothing in this period</div>
                            <div class="es-text">Pick a different date range, or record the first entry.</div>
                        </div>
                    </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @include('payroll.partials._pager', ['id' => 'reportPager'])
</div>
