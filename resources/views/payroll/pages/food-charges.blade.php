{{-- Staff Meals: who in this company eats at Pallav Food, and the month's bill.
     The price is Pallav Food's to set (Meal Price) and is only read here. The
     staff never pay any of it. --}}
@php
    $payee = \App\Models\PayrollCompany::FOOD_PAYEE;
    $owed = $company->paysFoodCharges();
    $included = $staff->where('eats_at_pallav_food', true);
@endphp
@extends('payroll.layout', [
    'title' => 'Staff Meals',
    'subtitle' => $company->paysFoodCharges()
        ? $company->name.' pays '.\App\Models\PayrollCompany::FOOD_PAYEE.' for its staff meals. Nothing here is taken from anyone\'s salary.'
        : 'Meals for '.$company->name.'\'s own staff, at the same price. Nothing here is taken from anyone\'s salary.',
])

@section('page-actions')
    @if($company->providesFood())
        <a class="btn btn-outline-p" href="{{ route('payroll.food-price.index') }}"><i class="bi bi-tag"></i> Meal price</a>
    @endif
    @if($food)
        {{-- The bill is printed as part of the month's report, with the salary --}}
        <a class="btn btn-outline-p" href="{{ route('payroll.monthly-report.index', ['year' => $monthStart->year, 'month' => $monthStart->month]) }}">
            <i class="bi bi-bar-chart"></i> Monthly report
        </a>
    @endif
@endsection

@section('page')

<div class="pay-stats mb-3">
    <div class="pay-stat {{ $rate === null ? 'warn' : '' }}">
        <div class="ps-label">Price per employee</div>
        <div class="ps-value">{{ $rate === null ? 'Not set' : '₹'.number_format($rate, 2) }}</div>
        <div class="ps-sub">
            @if($rate === null)
                {{ $company->providesFood() ? 'Set it in Meal Price' : $payee.' has not set it yet' }}
            @else
                Per month, set by {{ $payee }}
            @endif
        </div>
    </div>
    <div class="pay-stat">
        <div class="ps-label">Staff included</div>
        <div class="ps-value">{{ $included->count() }}</div>
        <div class="ps-sub">of {{ $staff->count() }} on the payroll</div>
    </div>
    <div class="pay-stat {{ $food ? 'good' : '' }}">
        <div class="ps-label">{{ $monthStart->format('F Y') }}</div>
        <div class="ps-value">{{ $food ? '₹'.number_format($food['total'], 2) : '₹0.00' }}</div>
        <div class="ps-sub">{{ $food ? ($owed ? 'Payable to '.$payee : 'Cost of staff meals') : ($generated ? 'Nothing to count' : 'After salary is generated') }}</div>
    </div>
    <div class="pay-stat">
        <div class="ps-label">Counted by</div>
        <div class="ps-value" style="font-size:19px;">Each day</div>
        <div class="ps-sub">{{ $monthStart->daysInMonth }} days in {{ $monthStart->format('F') }}</div>
    </div>
</div>

@if($rate === null && ! $company->providesFood())
    <div class="alert alert-warning d-flex align-items-start gap-2">
        <i class="bi bi-exclamation-triangle mt-1"></i>
        <span>{{ $payee }} has not set a price yet, so no bill can be worked out. It is set in <strong>Meal Price</strong> under the {{ $payee }} payroll.</span>
    </div>
@endif

{{-- 1. Who it covers --}}
<div class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-cup-hot me-1"></i> Who eats at {{ $payee }}</span>
        <span class="text-muted" style="font-size:12.5px;">{{ $included->count() }} switched on</span>
    </div>

    <div class="px-3 pt-3">
        <div class="master-note">
            <i class="bi bi-info-circle"></i>
            <span>
                Switch someone on and their meals are counted from their joining date. It is the same switch as the one on their staff record.
                A day marked with an attendance status that says <strong>meals are not counted</strong> is left out.
            </span>
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

{{-- 2. The month's bill --}}
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-receipt me-1"></i> {{ $owed ? 'Pay to '.$payee : 'Staff meals' }} &middot; {{ strtoupper($monthStart->format('F Y')) }}</span>
        @include('payroll.partials._month-nav', ['route' => 'payroll.food-charge.index', 'monthStart' => $monthStart])
    </div>

    @if($food)
        @include('payroll.partials._food-table')
    @else
        <div class="empty-state">
            <div class="es-icon"><i class="bi bi-cup-hot"></i></div>
            <div class="es-title">
                {{ ! $generated ? 'Not worked out yet for '.$monthStart->format('F Y') : 'Nothing to count for '.$monthStart->format('F Y') }}
            </div>
            <div class="es-text">
                @if(! $generated)
                    The meals bill is calculated once salary has been generated for the month. Generate it in
                    <a href="{{ route('payroll.attendance.index', ['year' => $monthStart->year, 'month' => $monthStart->month]) }}" class="fw-semibold">Attendance Management</a>
                    and it appears here and in the Monthly Report.
                @elseif($rate === null)
                    No price has been set{{ $company->providesFood() ? ' - set it in Meal Price' : ' by '.$payee.' yet' }}.
                @elseif($included->isEmpty())
                    Switch on the staff who eat at {{ $payee }}, above.
                @else
                    Nobody on the list was on the payroll during this month.
                @endif
            </div>
        </div>
    @endif
</div>

@endsection
