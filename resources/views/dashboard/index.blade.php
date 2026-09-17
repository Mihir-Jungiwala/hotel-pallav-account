@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')

@php $bothUnits = $showHotel && $showFood; @endphp

<div class="d-flex align-items-center gap-2 mb-3">
    <span class="unit-tag"><i class="bi bi-eye"></i> {{ $unitLabel }}</span>
    <span class="text-muted" style="font-size:12.5px;">Switch business from the sidebar to change what today's figures cover.</span>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat-card"><div class="stat-label">Total Staff</div><div class="stat-value">{{ $totalStaff }}</div></div></div>
    <div class="col-md-3"><div class="stat-card ink"><div class="stat-label">Total Companies</div><div class="stat-value">{{ $totalCompany }}</div></div></div>
    @if($showHotel)
        <div class="col-md-3"><div class="stat-card gold"><div class="stat-label">Debit Hotel Bills (Today)</div><div class="stat-value">{{ $totalDebitHotelBills }}</div></div></div>
    @endif
    @if($showFood)
        <div class="col-md-3"><div class="stat-card gold"><div class="stat-label">Debit Food Bills (Today)</div><div class="stat-value">{{ $totalDebitFoodBills }}</div></div></div>
    @endif
</div>

<div class="row g-3 mb-4">
    @if($showHotel)
    <div class="{{ $bothUnits ? 'col-md-6' : 'col-12' }}">
        <div class="card p-3">
            <h6 class="text-uppercase d-flex align-items-center gap-2" style="color:var(--p800); font-weight:800; letter-spacing:.03em;">
                <span class="unit-tag"><i class="bi bi-building"></i> Hotel Pallav</span> Today
            </h6>
            <div class="row text-center mt-3">
                <div class="col-4"><div class="text-muted small">Income</div><div class="fw-bold fs-5">₹{{ number_format($hotelIncome, 2) }}</div></div>
                <div class="col-4"><div class="text-muted small">Expense</div><div class="fw-bold fs-5">₹{{ number_format($hotelExpense, 2) }}</div></div>
                <div class="col-4"><div class="text-muted small">Balance</div><div class="fw-bold fs-5" style="color:var(--p700);">₹{{ number_format($hotelBalance, 2) }}</div></div>
            </div>
        </div>
    </div>
    @endif
    @if($showFood)
    <div class="{{ $bothUnits ? 'col-md-6' : 'col-12' }}">
        <div class="card p-3">
            <h6 class="text-uppercase d-flex align-items-center gap-2" style="color:var(--p800); font-weight:800; letter-spacing:.03em;">
                <span class="unit-tag food"><i class="bi bi-cup-hot"></i> Pallav Food</span> Today
            </h6>
            <div class="row text-center mt-3">
                <div class="col-4"><div class="text-muted small">Income</div><div class="fw-bold fs-5">₹{{ number_format($foodIncome, 2) }}</div></div>
                <div class="col-4"><div class="text-muted small">Expense</div><div class="fw-bold fs-5">₹{{ number_format($foodExpense, 2) }}</div></div>
                <div class="col-4"><div class="text-muted small">Balance</div><div class="fw-bold fs-5" style="color:var(--p700);">₹{{ number_format($foodBalance, 2) }}</div></div>
            </div>
        </div>
    </div>
    @endif
</div>

@if($bothUnits)
    {{-- The two businesses are run separately but owned together --}}
    <div class="card p-3 mb-4">
        <h6 class="text-uppercase" style="color:var(--p800); font-weight:800; letter-spacing:.03em;">Group &mdash; Today</h6>
        <div class="row text-center mt-3">
            <div class="col-4"><div class="text-muted small">Income</div><div class="fw-bold fs-5">₹{{ number_format($hotelIncome + $foodIncome, 2) }}</div></div>
            <div class="col-4"><div class="text-muted small">Expense</div><div class="fw-bold fs-5">₹{{ number_format($hotelExpense + $foodExpense, 2) }}</div></div>
            <div class="col-4"><div class="text-muted small">Balance</div><div class="fw-bold fs-5" style="color:var(--p700);">₹{{ number_format(($hotelIncome + $foodIncome) - ($hotelExpense + $foodExpense), 2) }}</div></div>
        </div>
    </div>
@endif

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
