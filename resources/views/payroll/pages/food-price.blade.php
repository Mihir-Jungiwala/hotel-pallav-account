{{-- Meal Price: Pallav Food's alone. What it charges per employee per month.
     A change starts on its day and the old price stays on record, like a
     salary revision; months whose salary is generated are closed. --}}
@extends('payroll.layout', [
    'title' => 'Meal Price',
    'subtitle' => 'What '.\App\Models\PayrollCompany::FOOD_PAYEE.' charges for one employee\'s meals for a month. It applies to staff of both companies who eat here.',
])

@php
    $payee = \App\Models\PayrollCompany::FOOD_PAYEE;
    $latest = $history->first();
@endphp

@section('page')

<div class="pay-stats mb-3">
    <div class="pay-stat {{ $current === null ? 'warn' : '' }}">
        <div class="ps-label">Price today</div>
        <div class="ps-value">{{ $current === null ? 'Not set' : '₹'.number_format($current, 2) }}</div>
        <div class="ps-sub">{{ $current === null ? 'Set it below to start' : 'Per employee, per month' }}</div>
    </div>
    <div class="pay-stat">
        <div class="ps-label">Last changed</div>
        <div class="ps-value" style="font-size:19px;">{{ $latest ? $latest->rate->effective_from->format('d M Y') : 'Never' }}</div>
        <div class="ps-sub">{{ $history->count() }} {{ Str::plural('price', $history->count()) }} on record</div>
    </div>
    <div class="pay-stat {{ $lockedThrough ? '' : 'good' }}">
        <div class="ps-label">Closed months</div>
        <div class="ps-value" style="font-size:19px;">{{ $lockedThrough ? 'Up to '.$lockedThrough->format('M Y') : 'None yet' }}</div>
        <div class="ps-sub">{{ $lockedThrough ? 'Salary generated, price fixed' : 'No salary generated yet' }}</div>
    </div>
    <div class="pay-stat">
        <div class="ps-label">Counted by</div>
        <div class="ps-value" style="font-size:19px;">Each day</div>
        <div class="ps-sub">At the price in force that day</div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><span><i class="bi bi-tag me-1"></i> Set a new price</span></div>

    <div class="px-3 pt-3">
        <div class="master-note">
            <i class="bi bi-info-circle"></i>
            <span>
                The new price starts on the day you choose and the old one stays on record - nothing is overwritten.
                Days before it keep the old price; days from it use the new one.
                @if($lockedThrough)
                    Salary is already generated up to <strong>{{ $lockedThrough->format('F Y') }}</strong>, so a price can start from
                    <strong>{{ $firstOpen->format('F Y') }}</strong> onwards.
                @endif
            </span>
        </div>
    </div>

    <div class="card-body">
        <form method="POST" action="{{ route('payroll.food-price.store') }}" class="row g-3 align-items-start">
            @csrf
            <div class="col-md-4">
                <label class="form-label" for="fp_amount">New price per employee<span class="req">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="number" step="0.01" min="0" name="monthly_amount" id="fp_amount" class="form-control"
                           value="{{ old('monthly_amount', $current) }}" placeholder="e.g. 3000" required>
                </div>
                <div class="form-text">For a whole month. A day is one day's share of it.</div>
                @error('monthly_amount')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label" for="fp_from">Starts on<span class="req">*</span></label>
                <input type="date" name="effective_from" id="fp_from" class="form-control"
                       value="{{ old('effective_from', $earliest->format('Y-m-d')) }}"
                       min="{{ $firstOpen->format('Y-m-d') }}" max="{{ $today->format('Y-m-d') }}" required>
                <div class="form-text">Today, or an earlier day in an open month. It cannot be later than today.</div>
                @error('effective_from')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4" style="padding-top:29px;">
                <button class="btn btn-p w-100"><i class="bi bi-check2"></i> Save price</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-clock-history me-1"></i> Price history</span>
        <span class="text-muted" style="font-size:12.5px;">Newest first. Every price stays on record.</span>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th class="col-sno">S.No.</th>
                    <th>Starts on</th>
                    <th class="money">Price per month</th>
                    <th class="money">Was</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($history as $row)
                <tr>
                    <td class="col-sno">{{ $loop->iteration }}</td>
                    <td class="text-nowrap">
                        <span class="cell-main">{{ $row->rate->effective_from->format('d M Y') }}</span>
                        <span class="cell-sub">Entered {{ $row->rate->created_at->format('d M Y') }}</span>
                    </td>
                    <td class="money"><strong>₹{{ number_format($row->rate->monthly_amount, 2) }}</strong></td>
                    <td class="money text-muted">{{ $row->was !== null ? '₹'.number_format($row->was, 2) : '-' }}</td>
                    <td>
                        @if($row->inForce)
                            <span class="pill pill-live"><span class="dot"></span> In force</span>
                        @elseif($row->rate->effective_from->isFuture())
                            <span class="pill pill-locked"><i class="bi bi-clock"></i> Starts later</span>
                        @else
                            <span class="pill pill-locked">Replaced</span>
                        @endif
                        @if($row->locked)
                            <span class="cell-sub"><i class="bi bi-lock-fill"></i> Closed - salary generated</span>
                        @endif
                    </td>
                    <td class="text-end">
                        @if($row->locked)
                            <span class="text-muted" style="font-size:12px;">Fixed</span>
                        @else
                            <form method="POST" action="{{ route('payroll.food-price.destroy', $row->rate) }}" class="d-inline"
                                  data-confirm-title="Remove this price?"
                                  data-confirm="Days from {{ $row->rate->effective_from->format('j F Y') }} fall back to the price before it."
                                  data-confirm-label="Remove">
                                @csrf @method('DELETE')
                                <button class="btn-icon danger" title="Remove"><i class="bi bi-trash"></i></button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        <div class="empty-state">
                            <div class="es-icon"><i class="bi bi-tag"></i></div>
                            <div class="es-title">No price set yet</div>
                            <div class="es-text">Set what {{ $payee }} charges above. Until then no meals bill can be worked out.</div>
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
