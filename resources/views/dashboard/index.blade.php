@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Total Staff</div><div class="stat-value">{{ $totalStaff }}</div></div></div>
    <div class="col-md-3"><div class="stat-card ink"><div class="stat-label">Total Companies</div><div class="stat-value">{{ $totalCompany }}</div></div></div>
    <div class="col-md-3"><div class="stat-card gold"><div class="stat-label">Debit Hotel Bills (Today)</div><div class="stat-value">{{ $totalDebitHotelBills }}</div></div></div>
    <div class="col-md-3"><div class="stat-card gold"><div class="stat-label">Debit Food Bills (Today)</div><div class="stat-value">{{ $totalDebitFoodBills }}</div></div></div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card p-3">
            <h6 class="text-uppercase" style="color:var(--p800); font-weight:800; letter-spacing:.03em;">Hotel &mdash; Today</h6>
            <div class="row text-center mt-3">
                <div class="col-4"><div class="text-muted small">Income</div><div class="fw-bold fs-5">₹{{ number_format($hotelIncome, 2) }}</div></div>
                <div class="col-4"><div class="text-muted small">Expense</div><div class="fw-bold fs-5">₹{{ number_format($hotelExpense, 2) }}</div></div>
                <div class="col-4"><div class="text-muted small">Balance</div><div class="fw-bold fs-5" style="color:var(--p700);">₹{{ number_format($hotelBalance, 2) }}</div></div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card p-3">
            <h6 class="text-uppercase" style="color:var(--p800); font-weight:800; letter-spacing:.03em;">Food &mdash; Today</h6>
            <div class="row text-center mt-3">
                <div class="col-4"><div class="text-muted small">Income</div><div class="fw-bold fs-5">₹{{ number_format($foodIncome, 2) }}</div></div>
                <div class="col-4"><div class="text-muted small">Expense</div><div class="fw-bold fs-5">₹{{ number_format($foodExpense, 2) }}</div></div>
                <div class="col-4"><div class="text-muted small">Balance</div><div class="fw-bold fs-5" style="color:var(--p700);">₹{{ number_format($foodBalance, 2) }}</div></div>
            </div>
        </div>
    </div>
</div>

<div class="card p-3">
    <h6 class="text-uppercase" style="color:var(--p800); font-weight:800; letter-spacing:.03em;">Latest Shift Handover</h6>
    @if($shiftHandover)
        <div class="row mt-2">
            <div class="col-md-3"><div class="text-muted small">Date</div><div class="fw-semibold">{{ optional($shiftHandover->date)->format('d M Y') }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Shift</div><div class="fw-semibold">{{ $shiftHandover->shift }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Handed by</div><div class="fw-semibold">{{ $shiftHandover->full_name }}</div></div>
            <div class="col-md-3"><div class="text-muted small">Total Cash</div><div class="fw-semibold">₹{{ number_format($shiftHandover->total, 2) }}</div></div>
        </div>
    @else
        <p class="text-muted mb-0">No shift handover recorded yet.</p>
    @endif
</div>

@endsection
