@php
    $s = $stats;
    $settled = $s['net'] > 0 ? min(100, (int) round($s['paid'] / $s['net'] * 100)) : 0;
@endphp

@extends('payroll.layout', [
    'title' => 'Dashboard',
    'subtitle' => 'Where payroll stands for '.$company->name.' today.',
])

@section('page-actions')
    <a class="btn btn-outline-p" href="{{ route('payroll.attendance.index') }}">
        <i class="bi bi-calendar-check"></i> Take attendance
    </a>
    <a class="btn btn-p" href="{{ route('payroll.staff.index') }}" data-write-only>
        <i class="bi bi-person-plus"></i> Add staff
    </a>
@endsection

@section('page')

{{-- Four figures. Each is a link to the page that can act on it. --}}
<div class="dash-figures">
    <a class="dash-figure" href="{{ route('payroll.staff.index') }}">
        <span class="df-label">Active staff <i class="bi bi-people"></i></span>
        <span class="df-value">{{ number_format($s['staff']) }}</span>
        <span class="df-sub">
            {{ $s['formerStaff'] ? $s['formerStaff'].' former '.Str::plural('employee', $s['formerStaff']) : 'Nobody has left' }}
        </span>
    </a>

    <a class="dash-figure" href="{{ route('payroll.attendance.index') }}">
        <span class="df-label">Attendance, {{ $month->format('F') }} <i class="bi bi-calendar3"></i></span>
        <span class="df-value">{{ $s['attendance']['percent'] }}<small>%</small></span>
        <span class="df-meter"><span style="width: {{ max(2, $s['attendance']['percent']) }}%"></span></span>
        <span class="df-sub">{{ number_format($s['attendance']['filled']) }} of {{ number_format($s['attendance']['expected']) }} days entered</span>
    </a>

    <a class="dash-figure" href="{{ route('payroll.salary-payment.index') }}">
        <span class="df-label">
            {{ $s['payrollMonth'] ? 'Salary due, '.$s['payrollMonth']->format('M Y') : 'Salary due' }}
            <i class="bi bi-credit-card-2-back"></i>
        </span>
        @if($s['payrollCount'])
            <span class="df-value">₹{{ number_format($s['outstanding'], 0) }}</span>
            <span class="df-meter"><span style="width: {{ max(2, $settled) }}%"></span></span>
            <span class="df-sub">{{ $settled }}% paid &middot; {{ $s['unpaidCount'] }} still open</span>
        @else
            <span class="df-value muted">&mdash;</span>
            <span class="df-sub">Nothing processed yet</span>
        @endif
    </a>

    <a class="dash-figure" href="{{ route('payroll.advance.index') }}">
        <span class="df-label">Advances out <i class="bi bi-wallet2"></i></span>
        <span class="df-value">₹{{ number_format($s['advanceOutstanding'], 0) }}</span>
        <span class="df-sub">{{ $s['advanceOpen'] }} {{ Str::plural('advance', $s['advanceOpen']) }} still recovering</span>
    </a>
</div>

<div class="dash-columns">
    <section class="card dash-panel">
        <header class="dp-head">
            <h3>Needs attention</h3>
            @if(count($attention))<span class="dp-count">{{ count($attention) }}</span>@endif
        </header>

        @forelse($attention as $item)
            <a class="dp-row" href="{{ $item['link'] }}">
                <span class="dp-dot tone-{{ $item['tone'] }}"></span>
                <span class="dp-text">{{ $item['text'] }}</span>
                <span class="dp-go">{{ $item['action'] }} <i class="bi bi-arrow-right-short"></i></span>
            </a>
        @empty
            <div class="dp-empty">
                <i class="bi bi-check2-circle"></i>
                <div>
                    <strong>All clear</strong>
                    <span>Setup is complete and every processed salary is settled.</span>
                </div>
            </div>
        @endforelse
    </section>

    <section class="card dash-panel">
        <header class="dp-head">
            <h3>Recent activity</h3>
            <span class="dp-note">Latest first</span>
        </header>

        @forelse($recent as $item)
            <a class="dp-row" href="{{ $item['link'] }}">
                <span class="dp-icon"><i class="bi {{ $item['icon'] }}"></i></span>
                <span class="dp-text">
                    <span class="dp-title">{{ $item['title'] }}</span>
                    <span class="dp-detail">{{ $item['detail'] }}</span>
                </span>
                <span class="dp-when" title="{{ $item['at']->format('d M Y, H:i') }}">{{ $item['at']->diffForHumans(null, true) }}</span>
            </a>
        @empty
            <div class="dp-empty">
                <i class="bi bi-activity"></i>
                <div>
                    <strong>Nothing yet</strong>
                    <span>Advances, bonuses and exits appear here as they happen.</span>
                </div>
            </div>
        @endforelse
    </section>
</div>

@endsection
