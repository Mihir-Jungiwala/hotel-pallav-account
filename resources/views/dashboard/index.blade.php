@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
@php
    $money = fn ($n) => 'Rs '.number_format((float) $n, 2);

    $todayIn = ($showHotel ? $todayTotals['hotel']['income'] : 0) + ($showFood ? $todayTotals['food']['income'] : 0);
    $todayOut = ($showHotel ? $todayTotals['hotel']['expense'] : 0) + ($showFood ? $todayTotals['food']['expense'] : 0);
    $yesterdayIn = ($showHotel ? $yesterday['hotel']['income'] : 0) + ($showFood ? $yesterday['food']['income'] : 0);
    $monthIn = ($showHotel ? $monthTotals['hotel']['income'] : 0) + ($showFood ? $monthTotals['food']['income'] : 0);
    $monthOut = ($showHotel ? $monthTotals['hotel']['expense'] : 0) + ($showFood ? $monthTotals['food']['expense'] : 0);

    $change = $yesterdayIn > 0 ? round((($todayIn - $yesterdayIn) / $yesterdayIn) * 100) : null;
@endphp

<div class="page-head reveal">
    <div>
        <div class="page-eyebrow">{{ $today->format('l, d F Y') }}</div>
        <h2 class="pms-title">Good {{ (int) now()->format('H') < 12 ? 'morning' : ((int) now()->format('H') < 17 ? 'afternoon' : 'evening') }}, {{ explode(' ', auth()->user()->name)[0] }}</h2>
        <p class="pms-sub">
            <span class="unit-tag"><i class="bi bi-eye"></i> {{ $unitLabel }}</span>
            <span class="ms-2">Here is where the money stands today.</span>
        </p>
    </div>
    <div class="quick-actions">
        <a class="qa-btn" href="{{ route('revenue.index') }}"><i class="bi bi-cash-coin"></i> Record deposit</a>
        <a class="qa-btn" href="{{ route('expense.index') }}"><i class="bi bi-cash-stack"></i> Record expense</a>
        <a class="qa-btn" href="{{ route('shift-handover.index') }}"><i class="bi bi-arrow-left-right"></i> Handover</a>
    </div>
</div>

{{-- Headline figures --}}
<div class="kpi-grid">
    <div class="kpi reveal">
        <div class="kpi-top">
            <span class="kpi-icon in"><i class="bi bi-arrow-down-left"></i></span>
            <span class="kpi-label">Cash in today</span>
        </div>
        <div class="kpi-value" data-count="{{ $todayIn }}">{{ $money($todayIn) }}</div>
        <div class="kpi-foot">
            @if($change === null)
                <span class="kpi-chip">No figure for yesterday</span>
            @else
                <span class="kpi-chip {{ $change >= 0 ? 'up' : 'down' }}">
                    <i class="bi {{ $change >= 0 ? 'bi-graph-up-arrow' : 'bi-graph-down-arrow' }}"></i>
                    {{ abs($change) }}% vs yesterday
                </span>
            @endif
        </div>
    </div>

    <div class="kpi reveal">
        <div class="kpi-top">
            <span class="kpi-icon out"><i class="bi bi-arrow-up-right"></i></span>
            <span class="kpi-label">Cash out today</span>
        </div>
        <div class="kpi-value" data-count="{{ $todayOut }}">{{ $money($todayOut) }}</div>
        <div class="kpi-foot"><span class="kpi-chip">{{ $money($todayIn - $todayOut) }} left in hand</span></div>
    </div>

    <div class="kpi reveal">
        <div class="kpi-top">
            <span class="kpi-icon month"><i class="bi bi-calendar3"></i></span>
            <span class="kpi-label">{{ $monthLabel }} so far</span>
        </div>
        <div class="kpi-value" data-count="{{ $monthIn }}">{{ $money($monthIn) }}</div>
        <div class="kpi-foot"><span class="kpi-chip">{{ $money($monthOut) }} spent</span></div>
    </div>

    <div class="kpi reveal">
        <div class="kpi-top">
            <span class="kpi-icon due"><i class="bi bi-hourglass-split"></i></span>
            <span class="kpi-label">Still to collect</span>
        </div>
        <div class="kpi-value" data-count="{{ $receivables['total'] }}">{{ $money($receivables['total']) }}</div>
        <div class="kpi-foot">
            <a class="kpi-chip link" href="{{ route('bill-master.debit-bills') }}">
                {{ $receivables['bills'] }} open debit {{ Str::plural('bill', $receivables['bills']) }} <i class="bi bi-arrow-right-short"></i>
            </a>
        </div>
    </div>
</div>

<div class="dash-grid">
    {{-- Trend --}}
    <div class="card dash-card reveal">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Last 14 days</span>
            <span class="legend"><i class="dot in"></i> In <i class="dot out ms-2"></i> Out</span>
        </div>
        <div class="card-body">
            <canvas id="trendChart" height="132"
                    data-labels='@json($trend['labels'])'
                    data-income='@json($trend['income'])'
                    data-expense='@json($trend['expense'])'></canvas>
        </div>
    </div>

    {{-- Needs attention --}}
    <div class="card dash-card reveal">
        <div class="card-header">Needs attention</div>
        <div class="card-body p-0">
            @forelse($attention as $item)
                <a class="attn-row" href="{{ $item['link'] }}">
                    <span class="attn-icon {{ $item['tone'] }}"><i class="bi {{ $item['icon'] }}"></i></span>
                    <span class="attn-body">
                        <span class="attn-text">{{ $item['text'] }}</span>
                        <span class="attn-action">{{ $item['action'] }} <i class="bi bi-arrow-right-short"></i></span>
                    </span>
                </a>
            @empty
                <div class="empty-state py-4">
                    <div class="es-icon"><i class="bi bi-check2-circle"></i></div>
                    <div class="es-title">Nothing pending</div>
                    <div class="es-text">Salary, bills and Handovers are all up to date.</div>
                </div>
            @endforelse
        </div>
    </div>
</div>

{{-- Per business --}}
<div class="row g-3 mb-3">
    @foreach(['hotel' => ['Hotel Pallav', 'bi-building', $showHotel], 'food' => ['Pallav Food', 'bi-cup-hot', $showFood]] as $unit => [$name, $icon, $visible])
        @continue(! $visible)
        <div class="{{ $bothUnits ? 'col-lg-6' : 'col-12' }}">
            <div class="card unit-card reveal {{ $unit }}">
                <div class="card-body">
                    <div class="uc-head">
                        <span class="unit-tag {{ $unit === 'food' ? 'food' : '' }}"><i class="bi {{ $icon }}"></i> {{ $name }}</span>
                        <span class="uc-entries">{{ $todayTotals[$unit]['entries'] }} entries today</span>
                    </div>
                    <div class="uc-figures">
                        <div>
                            <span class="uc-label">In today</span>
                            <span class="uc-value">{{ $money($todayTotals[$unit]['income']) }}</span>
                        </div>
                        <div>
                            <span class="uc-label">Out today</span>
                            <span class="uc-value">{{ $money($todayTotals[$unit]['expense']) }}</span>
                        </div>
                        <div>
                            <span class="uc-label">Balance</span>
                            <span class="uc-value accent">{{ $money($todayTotals[$unit]['balance']) }}</span>
                        </div>
                    </div>
                    <div class="uc-month">
                        <span>{{ $monthLabel }}</span>
                        <strong>{{ $money($monthTotals[$unit]['income']) }} in</strong>
                        <span class="text-muted">/</span>
                        <strong>{{ $money($monthTotals[$unit]['expense']) }} out</strong>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    @if($bothUnits)
        <div class="col-12">
            <div class="card group-card reveal">
                <div class="card-body">
                    <div class="uc-head"><span class="fw-bold">Group total</span><span class="uc-entries">Hotel Pallav and Pallav Food together</span></div>
                    <div class="uc-figures">
                        <div><span class="uc-label">In today</span><span class="uc-value">{{ $money($todayIn) }}</span></div>
                        <div><span class="uc-label">Out today</span><span class="uc-value">{{ $money($todayOut) }}</span></div>
                        <div><span class="uc-label">Balance</span><span class="uc-value accent">{{ $money($todayIn - $todayOut) }}</span></div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<div class="dash-grid">
    {{-- Recent entries --}}
    <div class="card dash-card reveal">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Latest entries</span>
            <a class="small-link" href="{{ route('revenue.index') }}">Cash book <i class="bi bi-arrow-right-short"></i></a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>No.</th><th>Entry</th><th>Business</th><th class="text-end">Amount</th><th>When</th></tr></thead>
                <tbody>
                @forelse($recent as $row)
                    <tr>
                        <td class="entry-no">#{{ $row['entry'] }}</td>
                        <td>
                            <span class="fw-semibold">{{ $row['who'] ?: $row['label'] }}</span>
                            <span class="d-block text-muted" style="font-size:11.5px;">{{ $row['label'] }} by {{ $row['by'] }}</span>
                        </td>
                        <td><span class="unit-tag {{ $row['unit'] === 'food' ? 'food' : '' }}">{{ $row['unit'] === 'food' ? 'Pallav Food' : 'Hotel Pallav' }}</span></td>
                        <td class="text-end fw-semibold {{ $row['direction'] === 'in' ? 'amount-in' : 'amount-out' }}">
                            {{ $row['direction'] === 'in' ? '+' : '-' }}{{ $money($row['amount']) }}
                        </td>
                        <td class="text-nowrap text-muted">{{ optional($row['date'])->format('d M') }} {{ $row['time'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">
                        <div class="empty-state py-4">
                            <div class="es-icon"><i class="bi bi-journal-text"></i></div>
                            <div class="es-title">No entries yet</div>
                            <div class="es-text">Cash deposits and expenses appear here as they are recorded.</div>
                        </div>
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Where the money went --}}
    <div class="card dash-card reveal">
        <div class="card-header">Spending this month</div>
        <div class="card-body">
            @php $topSpend = $expenseHeads->max('total') ?: 1; @endphp
            @forelse($expenseHeads as $head)
                <div class="bar-row">
                    <div class="bar-head">
                        <span>{{ $head['head'] }}</span>
                        <strong>{{ $money($head['total']) }}</strong>
                    </div>
                    <div class="bar-track"><span class="bar-fill" style="--w: {{ round(($head['total'] / $topSpend) * 100) }}%"></span></div>
                </div>
            @empty
                <div class="empty-state py-3">
                    <div class="es-icon"><i class="bi bi-pie-chart"></i></div>
                    <div class="es-title">Nothing spent yet</div>
                    <div class="es-text">Expense heads come from Master Data, so this list follows your own headings.</div>
                </div>
            @endforelse
        </div>
    </div>
</div>

{{-- Handover and counts --}}
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card dash-card reveal">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Latest Handover</span>
                <a class="small-link" href="{{ route('shift-handover.index') }}">All Handovers <i class="bi bi-arrow-right-short"></i></a>
            </div>
            <div class="card-body">
                @if($shiftHandover)
                    <div class="kv-grid three">
                        <div><span>Date</span><strong>{{ optional($shiftHandover->date)->format('d M Y') }}</strong></div>
                        <div><span>Shift</span><strong>{{ $shiftHandover->shift }}</strong></div>
                        <div><span>Total cash</span><strong>{{ $money($shiftHandover->total) }}</strong></div>
                        <div><span>Handed by</span><strong>{{ $shiftHandover->user?->displayName() ?? $shiftHandover->full_name }}</strong></div>
                        <div><span>Entry</span><strong>#{{ $shiftHandover->entryNumber() }}</strong></div>
                        <div><span>Business</span><strong>{{ optional($shiftHandover->businessUnit)->name ?? 'Hotel Pallav' }}</strong></div>
                    </div>
                @else
                    <div class="empty-state py-3">
                        <div class="es-icon"><i class="bi bi-arrow-left-right"></i></div>
                        <div class="es-title">No Handover recorded</div>
                        <div class="es-text">The first Handover will show here.</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card dash-card reveal h-100">
            <div class="card-header">On the books</div>
            <div class="card-body">
                <a class="mini-row" href="{{ route('payroll.staff.index') }}">
                    <span class="mini-icon"><i class="bi bi-people"></i></span>
                    <span class="mini-body"><strong>{{ $staffCount }}</strong> active employees</span>
                    <i class="bi bi-arrow-right-short"></i>
                </a>
                <a class="mini-row" href="{{ route('company.index') }}">
                    <span class="mini-icon"><i class="bi bi-building"></i></span>
                    <span class="mini-body"><strong>{{ $companyCount }}</strong> company profiles</span>
                    <i class="bi bi-arrow-right-short"></i>
                </a>
                <a class="mini-row" href="{{ route('reports.index') }}">
                    <span class="mini-icon"><i class="bi bi-file-earmark-text"></i></span>
                    <span class="mini-body">Account reports</span>
                    <i class="bi bi-arrow-right-short"></i>
                </a>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
@endpush
