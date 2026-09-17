@extends('layouts.app')
@section('title', 'Reports')
@section('content')

<div class="card p-3 mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-4"><label class="form-label">From Date</label><input type="date" name="from_date" class="form-control" value="{{ request('from_date') }}"></div>
        <div class="col-md-4"><label class="form-label">To Date</label><input type="date" name="to_date" class="form-control" value="{{ request('to_date') }}"></div>
        <div class="col-md-4"><button class="btn btn-p w-100"><i class="bi bi-funnel"></i> Filter</button></div>
    </form>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Bill No.</th><th>Date</th><th>Guest</th><th>Company</th><th>Hotel Total</th><th>Food Total</th></tr></thead>
            <tbody>
            @forelse($bills as $b)
                <tr>
                    <td class="fw-semibold">{{ $b->bill_number }}</td>
                    <td>{{ optional($b->bill_date)->format('d-m-Y') }}</td>
                    <td>{{ $b->guest_name }}</td>
                    <td>{{ optional($b->company)->name }}</td>
                    <td>₹{{ number_format($b->total_hotel_amount, 2) }}</td>
                    <td>₹{{ number_format($b->total_food_amount, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-4">No bills match this range.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
