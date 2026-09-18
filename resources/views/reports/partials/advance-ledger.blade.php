@php $money = fn ($n) => 'Rs '.number_format((float) $n, 2); @endphp

@include('reports.partials._totals', ['cards' => ['Advances paid' => $money($totals['paid']), 'Recovered' => $money($totals['recovered']), 'Still to recover' => $money($totals['outstanding']), 'Staff' => $totals['staff']]])

<div class="card reveal">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-paginate="25" data-pager="#reportPager">
            <thead><tr>
                <th class="col-no">No.</th><th>Date</th><th>Employee</th><th>For month</th>
                <th>Business</th><th class="text-end">Paid</th><th class="text-end">Recovered</th><th class="text-end">Balance</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr data-row="{{ $row['staff'] }} {{ $row['code'] }}">
                    <td class="entry-no">#{{ $row['entry'] }}</td>
                    <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                    <td>
                        <span class="fw-semibold">{{ $row['staff'] }}</span>
                        @if($row['code'])<span class="d-block text-muted" style="font-size:11.5px;">{{ $row['code'] }}</span>@endif
                    </td>
                    <td>{{ $row['month'] }}</td>
                    <td><span class="unit-tag {{ $row['unit'] === 'Pallav Food' ? 'food' : '' }}">{{ $row['unit'] }}</span></td>
                    <td class="text-end">{{ $money($row['amount']) }}</td>
                    <td class="text-end amount-in">{{ $money($row['recovered']) }}</td>
                    <td class="text-end fw-semibold">{{ $money($row['amount'] - $row['recovered']) }}</td>
                </tr>
            @empty
                    <tr><td colspan="8">
                        <div class="empty-state py-4">
                            <div class="es-icon"><i class="bi bi-people"></i></div>
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
