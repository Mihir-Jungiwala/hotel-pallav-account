@php $money = fn ($n) => 'Rs '.number_format((float) $n, 2); @endphp

@include('reports.partials._totals', ['cards' => ['Cash in' => $money($totals['in']), 'Cash out' => $money($totals['out']), 'Closing balance' => $money($totals['closing']), 'Entries' => $rows->count()]])

<div class="card reveal">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-paginate="25" data-pager="#reportPager">
            <thead><tr>
                <th class="col-no">No.</th><th>Date</th><th>Particulars</th><th>Kind</th>
                <th>Business</th><th class="text-end">In</th><th class="text-end">Out</th><th class="text-end">Balance</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr data-row="{{ $row['particulars'] }} {{ $row['kind'] }}">
                    <td class="entry-no">#{{ $row['entry'] }}</td>
                    <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }} <span class="text-muted">{{ $row['time'] }}</span></td>
                    <td>
                        <span class="fw-semibold">{{ $row['particulars'] }}</span>
                        @if($row['head'])<span class="d-block text-muted" style="font-size:11.5px;">{{ $row['head'] }}</span>@endif
                    </td>
                    <td>{{ $row['kind'] }}</td>
                    <td><span class="unit-tag {{ $row['unit'] === 'food' ? 'food' : '' }}">{{ $row['unit'] === 'food' ? 'Pallav Food' : 'Hotel Pallav' }}</span></td>
                    <td class="text-end amount-in">{{ $row['direction'] === 'in' ? $money($row['amount']) : '' }}</td>
                    <td class="text-end amount-out">{{ $row['direction'] === 'out' ? $money($row['amount']) : '' }}</td>
                    <td class="text-end fw-semibold">{{ $money($row['balance']) }}</td>
                </tr>
            @empty
                    <tr><td colspan="8">
                        <div class="empty-state py-4">
                            <div class="es-icon"><i class="bi bi-journal-text"></i></div>
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
