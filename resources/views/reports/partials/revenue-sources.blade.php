@php $money = fn ($n) => 'Rs '.number_format((float) $n, 2); @endphp

@include('reports.partials._totals', ['cards' => ['Total collected' => $money($totals['total']), 'Hotel Pallav' => $money($totals['hotel']), 'Pallav Food' => $money($totals['food']), 'Sources' => $totals['sources']]])

<div class="card reveal">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-paginate="25" data-pager="#reportPager">
            <thead><tr>
                <th>Source</th><th class="text-end">Deposits</th>
                <th class="text-end">Hotel Pallav</th><th class="text-end">Pallav Food</th>
                <th class="text-end">Total</th><th style="width:150px;">Share</th>
            </tr></thead>
            <tbody>
            @php $top = $rows->max('total') ?: 1; @endphp
            @forelse($rows as $row)
                <tr data-row="{{ $row['source'] }}">
                    <td class="fw-semibold">{{ $row['source'] }}</td>
                    <td class="text-end text-muted">{{ $row['count'] }}</td>
                    <td class="text-end">{{ $money($row['hotel']) }}</td>
                    <td class="text-end">{{ $money($row['food']) }}</td>
                    <td class="text-end fw-semibold">{{ $money($row['total']) }}</td>
                    <td><div class="bar-track"><span class="bar-fill" style="--w: {{ round(($row['total'] / $top) * 100) }}%"></span></div></td>
                </tr>
            @empty
                    <tr><td colspan="6">
                        <div class="empty-state py-4">
                            <div class="es-icon"><i class="bi bi-cash-coin"></i></div>
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
