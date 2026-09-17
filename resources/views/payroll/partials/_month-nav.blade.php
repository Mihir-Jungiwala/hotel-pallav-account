{{-- Prev / jump-to-month / next, capped at the current month. Same controls as Attendance. --}}
@php
    $previous = $monthStart->copy()->subMonthNoOverflow();
    $next = $monthStart->copy()->addMonthNoOverflow();
    $canGoNext = ! $next->greaterThan(now()->startOfMonth());
    $link = fn ($d) => route('payroll.index', ['category' => $category, 'year' => $d->year, 'month' => $d->month]);
@endphp

<div class="d-flex flex-wrap gap-2 align-items-center">
    <a class="btn btn-sm btn-outline-p" href="{{ $link($previous) }}">
        <i class="bi bi-chevron-left"></i> {{ $previous->format('M Y') }}
    </a>

    <form method="GET" action="{{ route('payroll.index') }}" class="month-jump" data-no-busy="true">
        <input type="hidden" name="category" value="{{ $category }}">
        <input type="hidden" name="year" value="{{ $monthStart->year }}">
        <input type="hidden" name="month" value="{{ $monthStart->month }}">
        <input type="month" class="form-control form-control-sm" aria-label="Jump to month"
               value="{{ $monthStart->format('Y-m') }}" max="{{ now()->format('Y-m') }}"
               onchange="if(this.value){const [y,m]=this.value.split('-');this.form.year.value=+y;this.form.month.value=+m;this.form.submit();}">
    </form>

    @if($canGoNext)
        <a class="btn btn-sm btn-outline-p" href="{{ $link($next) }}">
            {{ $next->format('M Y') }} <i class="bi bi-chevron-right"></i>
        </a>
    @else
        <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="You can't go past the current month">
            {{ $next->format('M Y') }} <i class="bi bi-chevron-right"></i>
        </button>
    @endif
</div>
