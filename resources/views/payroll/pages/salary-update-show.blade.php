@extends('payroll.layout', [
    'title' => $row->name,
    'subtitle' => 'Current terms and every revision on record, newest first. Nothing is ever overwritten.',
])

@section('page-actions')
    <a class="btn btn-outline-p" href="{{ route('payroll.salary-update.index') }}"><i class="bi bi-arrow-left"></i> All staff</a>
    <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#updateSalary{{ $row->id }}" data-write-only>
        <i class="bi bi-pencil-square"></i> Revise salary
    </button>
@endsection

@section('page')
@php
    $hours = rtrim(rtrim(number_format($row->daily_working_hours, 2), '0'), '.');
    $last = $history->first()?->first();
@endphp

{{-- Where this person stands today --}}
<div class="pay-stats mb-3">
    <div class="pay-stat">
        <div class="ps-label">Current salary</div>
        <div class="ps-value">₹{{ number_format($row->salary, 2) }}</div>
        <div class="ps-sub">{{ $hours }} hrs / day</div>
    </div>
    <div class="pay-stat">
        <div class="ps-label">Role</div>
        <div class="ps-value" style="font-size:19px;">{{ $row->designation }}</div>
        <div class="ps-sub">{{ $row->department ?: 'No department' }} &middot; {{ $row->employee_code }}</div>
    </div>
    <div class="pay-stat">
        <div class="ps-label">Paid by</div>
        <div class="ps-value" style="font-size:19px;">{{ $row->payment_mode }}</div>
        <div class="ps-sub">{{ $row->payment_mode === 'Bank' && $row->bank_name ? $row->bank_name : 'Salary payment mode' }}</div>
    </div>
    <div class="pay-stat">
        <div class="ps-label">Last revised</div>
        <div class="ps-value" style="font-size:19px;">{{ $last ? $last->effective_date->format('d M Y') : 'Never' }}</div>
        <div class="ps-sub">{{ $row->update_histories_count }} {{ Str::plural('change', $row->update_histories_count) }} on record</div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span><i class="bi bi-clock-history me-1"></i> Revision history</span>
        <span class="text-muted" style="font-size:12.5px;">Every save, with what it was and what it became</span>
    </div>
    <div class="card-body">
        @include('payroll.partials.salary-update-history')

        {{-- The starting point: what the record was before the first revision --}}
        <div class="history-entry">
            <div class="fw-bold" style="color:var(--p700);font-size:13px;">Joined {{ optional($row->joining_date)->format('d M Y') }}</div>
            <div class="text-muted" style="font-size:12px;">
                @if($history->isEmpty())
                    Still on the terms recorded when {{ $row->name }} joined.
                @else
                    The first revision above changed the terms recorded at joining.
                @endif
            </div>
        </div>
    </div>
</div>

@include('payroll.partials._salary-revise')
@endsection
