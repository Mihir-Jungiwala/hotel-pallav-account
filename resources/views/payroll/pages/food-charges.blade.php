{{-- Staff Meals: who in this company takes meals at Pallav Food, and the month's
     bill. The price is Pallav Food's to set (Meal Price) and is only read here.
     A person's meals are dated records, from a day to a day, so starting or
     stopping someone touches only those days. The staff never pay any of it. --}}
@php
    $payee = \App\Models\PayrollCompany::FOOD_PAYEE;
    $owed = $company->paysFoodCharges();
    $onMealsCount = count($onMeals);
    $ongoing = fn ($person) => $person->mealPeriods->first(fn ($p) => $p->isOngoing());
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
    @if($food && $food['final'])
        {{-- Once salary is generated the bill is printed in the month's report, with the salary --}}
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
        <div class="ps-label">On meals today</div>
        <div class="ps-value">{{ $onMealsCount }}</div>
        <div class="ps-sub">of {{ $staff->count() }} on the payroll</div>
    </div>
    <div class="pay-stat {{ $food ? 'good' : '' }}">
        <div class="ps-label">{{ $monthStart->format('F Y') }}</div>
        <div class="ps-value">{{ $food ? '₹'.number_format($food['total'], 2) : '₹0.00' }}</div>
        <div class="ps-sub">
            @if(! $food)
                Nothing to count
            @elseif(! $food['final'])
                {{ $food['asOf'] ? 'So far, to '.$food['asOf']->format('j M') : 'Not final yet' }}
            @else
                {{ $owed ? 'Payable to '.$payee : 'Cost of staff meals' }}
            @endif
        </div>
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

{{-- 1. Who takes meals, and when --}}
<div class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span><i class="bi bi-cup-hot me-1"></i> Who takes meals at {{ $payee }}</span>
        <span class="text-muted" style="font-size:12.5px;">{{ $onMealsCount }} on meals today</span>
    </div>

    <div class="px-3 pt-3">
        <div class="master-note">
            <i class="bi bi-info-circle"></i>
            <span>
                Each person has dated records: meals <strong>from</strong> a day, and <strong>to</strong> a day when they stop. Starting or stopping
                someone adds or closes a record and leaves every other month as it was. A day cannot be after today, and months whose salary is
                already generated{{ $lockedThrough ? ' (up to '.$lockedThrough->format('F Y').')' : '' }} are closed.
                A day marked with a status that says <strong>meals are not counted</strong> is left out.
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
                    <th>Meals</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($staff as $row)
                @php
                    $current = $row->mealPeriods->first(fn ($p) => $p->covers($today));
                    $last = $row->mealPeriods->first();
                    $open = $ongoing($row);
                @endphp
                <tr class="{{ $row->is_active ? '' : 'row-inherited' }}">
                    <td class="col-sno">{{ $loop->iteration }}</td>
                    <td>
                        <span class="cell-main">{{ $row->name }}</span>
                        <span class="cell-sub">{{ $row->employee_code }} &middot; joined {{ optional($row->joining_date)->format('d M Y') }}@unless($row->is_active) &middot; inactive @endunless</span>
                    </td>
                    <td>{{ $row->designation }}</td>
                    <td>
                        @if($current)
                            <span class="pill pill-live"><span class="dot"></span> On meals</span>
                            <span class="cell-sub">since {{ $current->starts_on->format('d M Y') }}{{ $current->ends_on ? ', until '.$current->ends_on->format('d M Y') : '' }}</span>
                        @elseif($last)
                            <span class="pill pill-locked">Stopped</span>
                            <span class="cell-sub">{{ $last->label() }}</span>
                        @else
                            <span class="text-muted">Not on meals</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        @if($open)
                            <button class="btn btn-sm btn-outline-p" data-bs-toggle="modal" data-bs-target="#stopMeals{{ $open->id }}" data-write-only>
                                <i class="bi bi-stop-circle"></i> Stop
                            </button>
                        @else
                            <button class="btn btn-sm btn-p" data-bs-toggle="modal" data-bs-target="#startMeals{{ $row->id }}" data-write-only>
                                <i class="bi bi-play-circle"></i> Start
                            </button>
                        @endif
                        @if($row->mealPeriods->isNotEmpty())
                            <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#mealRecords{{ $row->id }}" title="Meal records"><i class="bi bi-clock-history"></i></button>
                        @endif
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
        <span class="d-inline-flex align-items-center gap-2 flex-wrap">
            <span><i class="bi bi-receipt me-1"></i> {{ $owed ? 'Pay to '.$payee : 'Staff meals' }} &middot; {{ strtoupper($monthStart->format('F Y')) }}</span>
            @if($food && $food['final'])
                <span class="pill pill-locked"><i class="bi bi-lock-fill"></i> Final</span>
            @elseif($food)
                <span class="pill pill-live"><span class="dot"></span> Live</span>
            @endif
        </span>
        @include('payroll.partials._month-nav', ['route' => 'payroll.food-charge.index', 'monthStart' => $monthStart])
    </div>

    @if($food)
        @include('payroll.partials._food-table')
    @else
        <div class="empty-state">
            <div class="es-icon"><i class="bi bi-cup-hot"></i></div>
            <div class="es-title">Nothing to count for {{ $monthStart->format('F Y') }}</div>
            <div class="es-text">
                @if($rate === null)
                    No price has been set{{ $company->providesFood() ? ' - set it in Meal Price' : ' by '.$payee.' yet' }}.
                @elseif($onMealsCount === 0 && $staff->every(fn ($p) => $p->mealPeriods->isEmpty()))
                    Nobody has meals recorded yet. Start someone above.
                @else
                    Nobody had meals recorded for this month.
                @endif
            </div>
        </div>
    @endif
</div>

{{-- Start meals: one small dialog per person, so the date rules sit beside the field --}}
@foreach($staff as $row)
    @php
        $from = max($firstOpen, $row->joining_date ?? $firstOpen);
    @endphp
    <div class="modal fade pay-form-modal" id="startMeals{{ $row->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="{{ route('payroll.food-charge.start', $row) }}">
                @csrf
                <div class="modal-header">
                    <div>
                        <div class="pms-eyebrow">{{ $row->employee_code }}</div>
                        <h5 class="modal-title">Start meals for {{ $row->name }}</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="ms_from_{{ $row->id }}">Meals start on<span class="req">*</span></label>
                            <input type="date" name="starts_on" id="ms_from_{{ $row->id }}" class="form-control" required
                                   value="{{ old('starts_on', $today->format('Y-m-d')) }}"
                                   min="{{ $from->format('Y-m-d') }}" max="{{ $today->format('Y-m-d') }}">
                            <div class="form-text">Today, or an earlier day in an open month.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="ms_to_{{ $row->id }}">Meals end on<span class="opt">optional</span></label>
                            <input type="date" name="ends_on" id="ms_to_{{ $row->id }}" class="form-control"
                                   value="{{ old('ends_on') }}" min="{{ $from->format('Y-m-d') }}" max="{{ $today->format('Y-m-d') }}">
                            <div class="form-text">Leave empty while the meals carry on.</div>
                        </div>
                    </div>
                    @if($errors->has('starts_on') && (int) old('_person') === $row->id)
                        <div class="text-danger small mt-2">{{ $errors->first('starts_on') }}</div>
                    @endif
                    <input type="hidden" name="_person" value="{{ $row->id }}">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-p"><i class="bi bi-play-circle"></i> Start meals</button>
                </div>
            </form>
        </div></div>
    </div>

    {{-- The record that is still going, to be stopped --}}
    @if($open = $ongoing($row))
        @php $stopFrom = max($open->starts_on, $firstOpen->copy()->subDay()); @endphp
        <div class="modal fade pay-form-modal" id="stopMeals{{ $open->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog"><div class="modal-content">
                <form method="POST" action="{{ route('payroll.food-charge.stop', $open) }}">
                    @csrf @method('PUT')
                    <div class="modal-header">
                        <div>
                            <div class="pms-eyebrow">{{ $row->employee_code }} &middot; on meals since {{ $open->starts_on->format('d M Y') }}</div>
                            <h5 class="modal-title">Stop meals for {{ $row->name }}</h5>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label" for="me_to_{{ $open->id }}">Last day of meals<span class="req">*</span></label>
                        <input type="date" name="ends_on" id="me_to_{{ $open->id }}" class="form-control" required
                               value="{{ $today->format('Y-m-d') }}" min="{{ $stopFrom->format('Y-m-d') }}" max="{{ $today->format('Y-m-d') }}">
                        <div class="form-text">
                            This day is the last one charged. The record from {{ $open->starts_on->format('j M Y') }} stays on file, now with an end date.
                            Earlier months are not affected.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-p"><i class="bi bi-stop-circle"></i> Stop meals</button>
                    </div>
                </form>
            </div></div>
        </div>
    @endif

    {{-- Every record, like a salary history --}}
    @if($row->mealPeriods->isNotEmpty())
        <div class="modal fade pay-form-modal" id="mealRecords{{ $row->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
                <div class="modal-header">
                    <div>
                        <div class="pms-eyebrow">Newest first</div>
                        <h5 class="modal-title">Meal records - {{ $row->name }}</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr><th class="col-sno">S.No.</th><th>From</th><th>To</th><th>Entered</th><th class="text-end">Action</th></tr>
                        </thead>
                        <tbody>
                        @foreach($row->mealPeriods as $period)
                            @php $closed = \App\Support\FoodCharges::isClosedFor($company, $period->starts_on); @endphp
                            <tr>
                                <td class="col-sno">{{ $loop->iteration }}</td>
                                <td class="text-nowrap fw-semibold">{{ $period->starts_on->format('d M Y') }}</td>
                                <td class="text-nowrap">
                                    @if($period->ends_on){{ $period->ends_on->format('d M Y') }}@else<span class="pill pill-live"><span class="dot"></span> Ongoing</span>@endif
                                </td>
                                <td>
                                    {{ $period->created_at->format('d M Y') }}
                                    <span class="cell-sub">{{ optional($period->creator)->name ?? 'System' }}</span>
                                </td>
                                <td class="text-end">
                                    @if($closed)
                                        <span class="text-muted" style="font-size:12px;"><i class="bi bi-lock-fill"></i> Closed</span>
                                    @else
                                        <form method="POST" action="{{ route('payroll.food-charge.destroy', $period) }}" class="d-inline"
                                              data-confirm-title="Remove this record?"
                                              data-confirm="Every day it covers stops being charged, from {{ $period->starts_on->format('j F Y') }}."
                                              data-confirm-label="Remove">
                                            @csrf @method('DELETE')
                                            <button class="btn-icon danger" title="Remove"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <div class="form-text mt-2">A record in a month whose salary is generated is closed. To stop meals from a later day, end the record instead.</div>
                </div>
            </div></div>
        </div>
    @endif
@endforeach

@if($errors->any())
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            @if($errors->has('ends_on'))
                var open = document.querySelector('.modal[id^="stopMeals"]');
                if (open && window.bootstrap) { bootstrap.Modal.getOrCreateInstance(open).show(); }
            @elseif(old('_person'))
                var m = document.getElementById('startMeals{{ (int) old('_person') }}');
                if (m && window.bootstrap) { bootstrap.Modal.getOrCreateInstance(m).show(); }
            @endif
        });
    </script>
@endif

@endsection
