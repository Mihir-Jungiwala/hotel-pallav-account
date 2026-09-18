@php $money = fn ($n) => 'Rs '.number_format((float) $n, 2); @endphp

@include('reports.partials._totals', ['cards' => ['Still to collect' => $money($totals['due']), 'Open bills' => $totals['bills'], 'Oldest' => $totals['oldest'].' days', 'Over 60 days' => $money($totals['buckets']['Over 60 days'] ?? 0)]])

<div class="card reveal">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-paginate="25" data-pager="#reportPager">
            <thead><tr>
                <th>Bill No.</th><th>Date</th><th>Guest</th><th>Company</th>
                <th class="text-end">Hotel due</th><th class="text-end">Food due</th>
                <th class="text-end">Total due</th><th>Pending</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr data-row="{{ $row['bill_number'] }} {{ $row['guest'] }} {{ $row['company'] }}">
                    <td class="fw-semibold">{{ $row['bill_number'] }}</td>
                    <td class="text-nowrap">{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d M Y') }}</td>
                    <td>{{ $row['guest'] }}</td>
                    <td class="text-muted">{{ $row['company'] ?: '-' }}</td>
                    <td class="text-end">{{ $money($row['hotel']) }}</td>
                    <td class="text-end">{{ $money($row['food']) }}</td>
                    <td class="text-end fw-semibold">{{ $money($row['due']) }}</td>
                    <td>
                        <span class="pill {{ $row['days'] > 60 ? 'pill-locked' : ($row['days'] > 30 ? 'pill-unlocked' : 'pill-live') }}">
                            {{ $row['days'] }} days
                        </span>
                        <span class="d-block text-muted" style="font-size:11px;">{{ $row['bucket'] }}</span>
                    </td>
                </tr>
            @empty
                    <tr><td colspan="8">
                        <div class="empty-state py-4">
                            <div class="es-icon"><i class="bi bi-hourglass-split"></i></div>
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
