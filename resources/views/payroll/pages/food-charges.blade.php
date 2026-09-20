{{-- Food Charges. One page for the whole arrangement: what Pallav Food charges,
     who eats there, and what is owed this month. The staff never pay any of it. --}}
@extends('payroll.layout', [
    'title' => 'Food Charges',
    'subtitle' => $company->name.' pays '.\App\Models\PayrollCompany::FOOD_PAYEE.' for its staff meals. Nothing here is taken from anyone\'s salary.',
])

@php
    $payee = \App\Models\PayrollCompany::FOOD_PAYEE;
    $included = $staff->where('eats_at_pallav_food', true);
@endphp

@section('page-actions')
    @if($food)
        <a class="btn btn-p" target="_blank"
           href="{{ route('payroll.report.food-charges', ['year' => $monthStart->year, 'month' => $monthStart->month]) }}">
            <i class="bi bi-file-earmark-pdf"></i> Statement PDF
        </a>
    @endif
@endsection

@section('page')

<div class="pay-stats mb-3">
    <div class="pay-stat {{ $rate === null ? 'warn' : '' }}">
        <div class="ps-label">Charge each, per month</div>
        <div class="ps-value">{{ $rate === null ? 'Not set' : '₹'.number_format($rate, 2) }}</div>
        <div class="ps-sub">{{ $rate === null ? 'Set it below to start' : 'Fixed by '.$payee }}</div>
    </div>
    <div class="pay-stat">
        <div class="ps-label">Staff included</div>
        <div class="ps-value">{{ $included->count() }}</div>
        <div class="ps-sub">of {{ $staff->count() }} on the payroll</div>
    </div>
    <div class="pay-stat {{ $food ? 'good' : '' }}">
        <div class="ps-label">{{ $monthStart->format('F Y') }}</div>
        <div class="ps-value">{{ $food ? '₹'.number_format($food['total'], 2) : '₹0.00' }}</div>
        <div class="ps-sub">{{ $food ? 'Payable to '.$payee : 'Nothing to pay yet' }}</div>
    </div>
    <div class="pay-stat">
        <div class="ps-label">Counted by</div>
        <div class="ps-value" style="font-size:19px;">Calendar days</div>
        <div class="ps-sub">{{ $monthStart->daysInMonth }} days in {{ $monthStart->format('F') }}</div>
    </div>
</div>

{{-- 1. The amount --}}
<div class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-cash-coin me-1"></i> What {{ $payee }} charges</span>
        @if($rateHistory->count() > 1)
            <button class="btn btn-sm btn-outline-p" data-bs-toggle="modal" data-bs-target="#rateHistory">
                <i class="bi bi-clock-history"></i> Past amounts ({{ $rateHistory->count() }})
            </button>
        @endif
    </div>

    <div class="card-body">
        <form method="POST" action="{{ route('payroll.food-charge.rate') }}" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-4">
                <label class="form-label" for="fc_amount">Amount per employee<span class="req">*</span></label>
                <div class="input-group">
                    <span class="input-group-text">₹</span>
                    <input type="number" step="0.01" min="0" name="monthly_amount" id="fc_amount" class="form-control"
                           value="{{ old('monthly_amount', $rate) }}" placeholder="e.g. 3000" required>
                </div>
                <div class="form-text">For a whole month. A part month is worked out from this.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="fc_from">Applies from<span class="req">*</span></label>
                <input type="month" name="effective_from" id="fc_from" class="form-control"
                       value="{{ old('effective_from', now()->format('Y-m')) }}" required>
                <div class="form-text">Months before this keep the amount they already had.</div>
            </div>
            <div class="col-md-4">
                <button class="btn btn-p w-100"><i class="bi bi-check2"></i> Save amount</button>
            </div>
        </form>
    </div>
</div>

{{-- 2. Who it covers --}}
<div class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-cup-hot me-1"></i> Who eats at {{ $payee }}</span>
        <span class="text-muted" style="font-size:12.5px;">{{ $included->count() }} switched on</span>
    </div>

    <div class="px-3 pt-3">
        <div class="master-note">
            <i class="bi bi-info-circle"></i>
            <span>Switch someone on and {{ $company->name }} starts paying for their meals from their joining date. It is the same switch as the one on their staff record.</span>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="foodStaffTable">
            <thead>
                <tr>
                    <th class="col-sno">S.No.</th>
                    <th>Employee</th>
                    <th>Designation</th>
                    <th>Joined</th>
                    <th class="text-center">Eats there</th>
                </tr>
            </thead>
            <tbody>
            @forelse($staff as $row)
                <tr class="{{ $row->is_active ? '' : 'row-inherited' }}">
                    <td class="col-sno">{{ $loop->iteration }}</td>
                    <td>
                        <span class="cell-main">{{ $row->name }}</span>
                        <span class="cell-sub">{{ $row->employee_code }}@unless($row->is_active) &middot; inactive @endunless</span>
                    </td>
                    <td>{{ $row->designation }}</td>
                    <td class="text-nowrap">{{ optional($row->joining_date)->format('d M Y') ?: '-' }}</td>
                    <td class="text-center">
                        <form method="POST" action="{{ route('payroll.food-charge.toggle', $row) }}" data-status-toggle>
                            @csrf
                            <button class="btn btn-sm {{ $row->eats_at_pallav_food ? 'btn-outline-success' : 'btn-outline-secondary' }}">
                                {{ $row->eats_at_pallav_food ? 'Yes' : 'No' }}
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">
                        <div class="empty-state">
                            <div class="es-icon"><i class="bi bi-people"></i></div>
                            <div class="es-title">No staff yet</div>
                            <div class="es-text">Add people in Staff Management first.</div>
                            <a class="btn btn-p mt-3" href="{{ route('payroll.staff.index') }}"><i class="bi bi-people"></i> Go to Staff Management</a>
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- 3. The month's bill --}}
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-receipt me-1"></i> Pay to {{ $payee }} &middot; {{ strtoupper($monthStart->format('F Y')) }}</span>
        @include('payroll.partials._month-nav', ['route' => 'payroll.food-charge.index', 'monthStart' => $monthStart])
    </div>

    @if($food)
        @include('payroll.partials._food-table')
    @else
        <div class="empty-state">
            <div class="es-icon"><i class="bi bi-cup-hot"></i></div>
            <div class="es-title">Nothing to pay for {{ $monthStart->format('F Y') }}</div>
            <div class="es-text">
                @if($rate === null)
                    Set the amount {{ $payee }} charges, above.
                @elseif($included->isEmpty())
                    Switch on the staff who eat at {{ $payee }}, above.
                @else
                    Nobody on the list was on the payroll during this month.
                @endif
            </div>
        </div>
    @endif
</div>

@if($rateHistory->count() > 1)
    <div class="modal fade pay-form-modal" id="rateHistory" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header">
                <div>
                    <div class="pms-eyebrow">Newest first</div>
                    <h5 class="modal-title">What {{ $payee }} has charged</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>From</th><th class="money">Amount</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                    @foreach($rateHistory as $past)
                        <tr>
                            <td>{{ $past->effective_from->format('F Y') }}</td>
                            <td class="money">₹{{ number_format($past->monthly_amount, 2) }}</td>
                            <td class="text-end">
                                <form method="POST" action="{{ route('payroll.food-charge.rate.destroy', $past) }}" class="d-inline"
                                      data-confirm-title="Remove this amount?"
                                      data-confirm="Months from {{ $past->effective_from->format('F Y') }} fall back to the amount before it."
                                      data-confirm-label="Remove">
                                    @csrf @method('DELETE')
                                    <button class="btn-icon danger" title="Remove"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div></div>
    </div>
@endif

@endsection
