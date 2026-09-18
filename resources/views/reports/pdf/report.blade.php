@php
    use Illuminate\Support\Carbon;

    $brandName = config('app.name', 'Hotel Pallav');
    $brandMeta = $unitLabel;
    $docType = $meta['name'];
    $docSub = $from->format('d M Y').' to '.$to->format('d M Y');

    $money = fn ($n) => number_format((float) $n, 2);

    // Each report brings its own columns and its own totals row
    $cards = match ($type) {
        'monthly-summary' => ['Cash in' => $money($totals['in']), 'Cash out' => $money($totals['out']), 'Closing balance' => $money($totals['closing'])],
        'expense-heads' => ['Total spent' => $money($totals['total']), 'Hotel Pallav' => $money($totals['hotel']), 'Pallav Food' => $money($totals['food']), 'Heads' => $totals['heads']],
        'revenue-sources' => ['Total collected' => $money($totals['total']), 'Hotel Pallav' => $money($totals['hotel']), 'Pallav Food' => $money($totals['food']), 'Sources' => $totals['sources']],
        'receivables' => ['Still to collect' => $money($totals['due']), 'Open bills' => $totals['bills'], 'Oldest' => $totals['oldest'].' days'],
        'advance-ledger' => ['Advances paid' => $money($totals['paid']), 'Recovered' => $money($totals['recovered']), 'Still to recover' => $money($totals['outstanding'])],
        'bill-register' => ['Billed total' => $money($totals['total']), 'Hotel Pallav' => $money($totals['hotel']), 'Pallav Food' => $money($totals['food']), 'Bills' => $totals['bills']],
        default => ['Cash in' => $money($totals['in']), 'Cash out' => $money($totals['out']), 'Closing balance' => $money($totals['closing']), 'Entries' => $rows->count()],
    };
@endphp

@extends('payroll.pdf._base')

@section('content')

<table class="stats avoid-break">
    <tr>
        @foreach($cards as $label => $value)
            <td>
                <div class="s-label">{{ $label }}</div>
                <div class="s-value {{ $loop->first ? 'accent' : '' }}">{{ is_numeric(str_replace(',', '', (string) $value)) && ! str_contains((string) $label, 'days') && ! in_array($label, ['Entries', 'Bills', 'Heads', 'Sources'], true) ? '&#8377;'.$value : $value }}</div>
            </td>
        @endforeach
    </tr>
</table>

<table class="grid tight">
    @if($type === 'cash-book')
        <thead><tr>
            <th>No.</th><th>Date</th><th>Particulars</th><th>Kind</th><th>Business</th>
            <th class="num">In</th><th class="num">Out</th><th class="num">Balance</th>
        </tr></thead>
        <tbody>
        @forelse($rows as $row)
            <tr>
                <td>#{{ $row['entry'] }}</td>
                <td>{{ Carbon::parse($row['date'])->format('d M Y') }} {{ $row['time'] }}</td>
                <td>{{ \Illuminate\Support\Str::limit($row['particulars'], 34) }}</td>
                <td>{{ $row['kind'] }}</td>
                <td>{{ $row['unit'] === 'food' ? 'Pallav Food' : 'Hotel Pallav' }}</td>
                <td class="num">{{ $row['direction'] === 'in' ? $money($row['amount']) : '' }}</td>
                <td class="num">{{ $row['direction'] === 'out' ? $money($row['amount']) : '' }}</td>
                <td class="num">{{ $money($row['balance']) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="center muted" style="padding:14px;">No entries in this period.</td></tr>
        @endforelse
        @if($rows->isNotEmpty())
            <tr class="total">
                <td colspan="5">Total</td>
                <td class="num">{{ $money($totals['in']) }}</td>
                <td class="num">{{ $money($totals['out']) }}</td>
                <td class="num">{{ $money($totals['closing']) }}</td>
            </tr>
        @endif
        </tbody>

    @elseif($type === 'monthly-summary')
        <thead><tr>
            <th>Day</th><th class="num">Entries</th><th class="num">In</th>
            <th class="num">Out</th><th class="num">Net</th><th class="num">Running balance</th>
        </tr></thead>
        <tbody>
        @foreach($rows as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td class="num">{{ $row['entries'] ?: '-' }}</td>
                <td class="num">{{ $row['in'] ? $money($row['in']) : '-' }}</td>
                <td class="num">{{ $row['out'] ? $money($row['out']) : '-' }}</td>
                <td class="num">{{ $money($row['net']) }}</td>
                <td class="num">{{ $money($row['balance']) }}</td>
            </tr>
        @endforeach
            <tr class="total">
                <td colspan="2">Total</td>
                <td class="num">{{ $money($totals['in']) }}</td>
                <td class="num">{{ $money($totals['out']) }}</td>
                <td class="num">{{ $money($totals['in'] - $totals['out']) }}</td>
                <td class="num">{{ $money($totals['closing']) }}</td>
            </tr>
        </tbody>

    @elseif(in_array($type, ['expense-heads', 'revenue-sources'], true))
        @php $key = $type === 'expense-heads' ? 'head' : 'source'; @endphp
        <thead><tr>
            <th>{{ $type === 'expense-heads' ? 'Expense head' : 'Source' }}</th>
            <th class="num">Entries</th><th class="num">Hotel Pallav</th>
            <th class="num">Pallav Food</th><th class="num">Total</th><th class="num">Share</th>
        </tr></thead>
        <tbody>
        @forelse($rows as $row)
            <tr>
                <td>{{ $row[$key] }}</td>
                <td class="num">{{ $row['count'] }}</td>
                <td class="num">{{ $money($row['hotel']) }}</td>
                <td class="num">{{ $money($row['food']) }}</td>
                <td class="num">{{ $money($row['total']) }}</td>
                <td class="num">{{ $totals['total'] > 0 ? round(($row['total'] / $totals['total']) * 100).'%' : '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="center muted" style="padding:14px;">Nothing recorded in this period.</td></tr>
        @endforelse
        @if($rows->isNotEmpty())
            <tr class="total">
                <td colspan="2">Total</td>
                <td class="num">{{ $money($totals['hotel']) }}</td>
                <td class="num">{{ $money($totals['food']) }}</td>
                <td class="num">{{ $money($totals['total']) }}</td>
                <td class="num">100%</td>
            </tr>
        @endif
        </tbody>

    @elseif($type === 'receivables')
        <thead><tr>
            <th>Bill No.</th><th>Date</th><th>Guest</th><th>Company</th>
            <th class="num">Hotel due</th><th class="num">Food due</th><th class="num">Total due</th><th>Pending</th>
        </tr></thead>
        <tbody>
        @forelse($rows as $row)
            <tr>
                <td>{{ $row['bill_number'] }}</td>
                <td>{{ Carbon::parse($row['date'])->format('d M Y') }}</td>
                <td>{{ \Illuminate\Support\Str::limit($row['guest'], 26) }}</td>
                <td>{{ \Illuminate\Support\Str::limit($row['company'] ?: '-', 22) }}</td>
                <td class="num">{{ $money($row['hotel']) }}</td>
                <td class="num">{{ $money($row['food']) }}</td>
                <td class="num">{{ $money($row['due']) }}</td>
                <td>{{ $row['days'] }} days</td>
            </tr>
        @empty
            <tr><td colspan="8" class="center muted" style="padding:14px;">Nothing outstanding. Every bill is settled.</td></tr>
        @endforelse
        @if($rows->isNotEmpty())
            <tr class="total">
                <td colspan="6">Total outstanding</td>
                <td class="num">{{ $money($totals['due']) }}</td>
                <td></td>
            </tr>
        @endif
        </tbody>

    @elseif($type === 'advance-ledger')
        <thead><tr>
            <th>No.</th><th>Date</th><th>Employee</th><th>For month</th>
            <th>Business</th><th class="num">Paid</th><th class="num">Recovered</th><th class="num">Balance</th>
        </tr></thead>
        <tbody>
        @forelse($rows as $row)
            <tr>
                <td>#{{ $row['entry'] }}</td>
                <td>{{ Carbon::parse($row['date'])->format('d M Y') }}</td>
                <td>{{ $row['staff'] }}{{ $row['code'] ? ' ('.$row['code'].')' : '' }}</td>
                <td>{{ $row['month'] }}</td>
                <td>{{ $row['unit'] }}</td>
                <td class="num">{{ $money($row['amount']) }}</td>
                <td class="num">{{ $money($row['recovered']) }}</td>
                <td class="num">{{ $money($row['amount'] - $row['recovered']) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="center muted" style="padding:14px;">No advances in this period.</td></tr>
        @endforelse
        @if($rows->isNotEmpty())
            <tr class="total">
                <td colspan="5">Total</td>
                <td class="num">{{ $money($totals['paid']) }}</td>
                <td class="num">{{ $money($totals['recovered']) }}</td>
                <td class="num">{{ $money($totals['outstanding']) }}</td>
            </tr>
        @endif
        </tbody>

    @else
        <thead><tr>
            <th>Bill No.</th><th>Date</th><th>Guest</th><th>Company</th>
            <th class="num">Hotel</th><th class="num">Food</th><th class="num">Total</th><th>Payment</th>
        </tr></thead>
        <tbody>
        @forelse($rows as $bill)
            <tr>
                <td>{{ $bill->bill_number }}</td>
                <td>{{ optional($bill->bill_date)->format('d M Y') }}</td>
                <td>{{ \Illuminate\Support\Str::limit($bill->guest_name, 26) }}</td>
                <td>{{ \Illuminate\Support\Str::limit(optional($bill->company)->name ?: '-', 22) }}</td>
                <td class="num">{{ $money($bill->total_hotel_amount) }}</td>
                <td class="num">{{ $money($bill->total_food_amount) }}</td>
                <td class="num">{{ $money($bill->total_hotel_amount + $bill->total_food_amount) }}</td>
                <td>{{ $bill->hotel_mode_of_payment ?: '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="center muted" style="padding:14px;">No bills raised in this period.</td></tr>
        @endforelse
        @if($rows->isNotEmpty())
            <tr class="total">
                <td colspan="4">Total</td>
                <td class="num">{{ $money($totals['hotel']) }}</td>
                <td class="num">{{ $money($totals['food']) }}</td>
                <td class="num">{{ $money($totals['total']) }}</td>
                <td></td>
            </tr>
        @endif
        </tbody>
    @endif
</table>

<div class="muted" style="font-size:7.5pt; margin-top:8px;">
    {{ $meta['hint'] }} Prepared for {{ $unitLabel }} by {{ auth()->user()->name }}.
</div>

@endsection
