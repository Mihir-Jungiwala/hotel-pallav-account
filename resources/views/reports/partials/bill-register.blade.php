@php $money = fn ($n) => 'Rs '.number_format((float) $n, 2); @endphp

@include('reports.partials._totals', ['cards' => ['Billed total' => $money($totals['total']), 'Hotel Pallav' => $money($totals['hotel']), 'Pallav Food' => $money($totals['food']), 'Bills' => $totals['bills']]])

<div class="card reveal">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" data-paginate="25" data-pager="#reportPager">
            <thead><tr>
                <th>Bill No.</th><th>Date</th><th>Guest</th><th>Company</th>
                <th class="text-end">Hotel</th><th class="text-end">Food</th>
                <th class="text-end">Total</th><th>Payment</th>
            </tr></thead>
            <tbody>
            @forelse($rows as $bill)
                <tr data-row="{{ $bill->bill_number }} {{ $bill->guest_name }}">
                    <td class="fw-semibold">{{ $bill->bill_number }}</td>
                    <td class="text-nowrap">{{ optional($bill->bill_date)->format('d M Y') }}</td>
                    <td>{{ $bill->guest_name }}</td>
                    <td class="text-muted">{{ optional($bill->company)->name ?: '-' }}</td>
                    <td class="text-end">{{ $money($bill->total_hotel_amount) }}</td>
                    <td class="text-end">{{ $money($bill->total_food_amount) }}</td>
                    <td class="text-end fw-semibold">{{ $money($bill->total_hotel_amount + $bill->total_food_amount) }}</td>
                    <td>
                        <span class="text-nowrap">{{ $bill->hotel_mode_of_payment ?: '-' }}</span>
                        <span class="d-block text-muted" style="font-size:11.5px;">{{ $bill->food_mode_of_payment ?: '-' }}</span>
                    </td>
                </tr>
            @empty
                    <tr><td colspan="8">
                        <div class="empty-state py-4">
                            <div class="es-icon"><i class="bi bi-receipt"></i></div>
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
