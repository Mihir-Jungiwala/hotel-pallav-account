@extends('layouts.app')
@section('title', 'Revenue')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/cashbook.css') }}?v=4">
@endpush

@section('content')
@php
    $money = fn ($n) => '₹'.number_format((float) $n, 2);
    $dayLabel = function ($date) {
        if (! $date) return 'No date';
        if ($date->isToday()) return 'Today';
        if ($date->isYesterday()) return 'Yesterday';
        return $date->format('l, d M Y');
    };
    $scope = ['all' => 'Both books', 'hotel' => 'Hotel Pallav', 'food' => 'Pallav Food'][$filter];
@endphp

<div class="page-head reveal">
    <div>
        <div class="page-eyebrow">{{ now()->format('l, d F Y') }}</div>
        <h2 class="pms-title">Revenue</h2>
        <p class="pms-sub">Cash deposited into the Hotel Pallav and Pallav Food books.</p>
    </div>
    <button type="button" class="btn btn-p" data-bs-toggle="modal" data-bs-target="#cashModal" data-write-only>
        <i class="bi bi-plus-lg"></i> New Deposit
    </button>
</div>

<div class="kpi-grid">
    <div class="kpi reveal">
        <div class="kpi-top"><span class="kpi-icon in"><i class="bi bi-cash-coin"></i></span><span class="kpi-label">Deposited today</span></div>
        <div class="kpi-value">{{ $money($stats['today']) }}</div>
        <div class="kpi-foot"><span class="kpi-chip">{{ $scope }}</span></div>
    </div>
    <div class="kpi reveal">
        <div class="kpi-top"><span class="kpi-icon month"><i class="bi bi-calendar3"></i></span><span class="kpi-label">This month</span></div>
        <div class="kpi-value">{{ $money($stats['month']) }}</div>
        <div class="kpi-foot"><span class="kpi-chip">{{ now()->format('F Y') }} &middot; {{ $scope }}</span></div>
    </div>
    <div class="kpi reveal">
        <div class="kpi-top"><span class="kpi-icon due"><i class="bi bi-building"></i></span><span class="kpi-label">Hotel Pallav this month</span></div>
        <div class="kpi-value">{{ $money($stats['hotelMonth']) }}</div>
        <div class="kpi-foot"><span class="kpi-chip">Hotel Pallav cash book</span></div>
    </div>
    <div class="kpi reveal">
        <div class="kpi-top"><span class="kpi-icon out"><i class="bi bi-cup-hot"></i></span><span class="kpi-label">Pallav Food this month</span></div>
        <div class="kpi-value">{{ $money($stats['foodMonth']) }}</div>
        <div class="kpi-foot"><span class="kpi-chip">Pallav Food cash book</span></div>
    </div>
</div>

@include('cashbook._filters', ['route' => 'revenue.index', 'filter' => $filter, 'q' => $q, 'searchHint' => 'Search depositor, source, amount or date'])

@if($records->isEmpty())
    <div class="card reveal">
        <div class="empty-state">
            <div class="es-icon"><i class="bi bi-cash-coin"></i></div>
            <div class="es-title">{{ $q !== '' ? 'No deposits match "'.$q.'"' : 'No deposits '.($filter === 'all' ? 'yet' : 'in '.$scope.' yet') }}</div>
            <div class="es-text">When cash is handed in, record it here with who deposited it and where it came from.</div>
            <button type="button" class="btn btn-p mt-3" data-bs-toggle="modal" data-bs-target="#cashModal" data-write-only><i class="bi bi-plus-lg"></i> New Deposit</button>
        </div>
    </div>
@else
    @include('cashbook._table', ['direction' => 'in', 'noun' => 'deposit', 'nouns' => 'deposits', 'withKind' => false])
@endif

{{-- One pop-up for a new deposit or an edit, filled from the JSON below --}}
<div class="modal fade pay-form-modal cb-modal" id="cashModal" tabindex="-1" aria-labelledby="cashModalTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <form method="POST" action="#" id="cashForm">
            @csrf
            <input type="hidden" name="_method" value="PUT" disabled data-method>
            <input type="hidden" name="_form" value="cashbook">
            <input type="hidden" name="_record_id" value="" data-record-id>

            <div class="modal-header">
                <div>
                    <div class="pms-eyebrow" data-subtitle>Revenue &middot; New</div>
                    <h5 class="modal-title" id="cashModalTitle" data-title>New Deposit</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="form-section">
                    <div class="fs-head"><div class="fs-title"><i class="bi bi-journal-bookmark"></i> Which book</div><div class="fs-hint">Where this cash is booked.</div></div>
                    <div class="cb-choice" role="radiogroup" aria-label="Which book" data-choice="book">
                        <label><input type="radio" name="_book" value="hotel" checked><span><i class="bi bi-building"></i> Hotel Pallav</span></label>
                        <label><input type="radio" name="_book" value="food"><span><i class="bi bi-cup-hot"></i> Pallav Food</span></label>
                    </div>
                </div>

                <div class="form-section">
                    <div class="fs-head"><div class="fs-title"><i class="bi bi-person"></i> Who and when</div></div>
                    <div class="row g-3">
                        @include('partials._entry-stamp')
                        <div class="col-md-6">@include('cashbook._person-field', ['name' => 'depositor', 'label' => 'Depositor'])</div>
                        <div class="col-12">@include('partials._option-field', ['key' => 'revenue_source', 'name' => 'revenue_source', 'value' => null, 'label' => 'Source'])</div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="fs-head"><div class="fs-title"><i class="bi bi-cash-stack"></i> Amount</div></div>
                    @include('partials._amount-field', ['label' => 'Amount deposited'])
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-p" data-busy-label="Saving…" data-save-label><i class="bi bi-check2"></i> Save Deposit</button>
            </div>
        </form>
    </div></div>
</div>

<script type="application/json" id="cashData">{!! json_encode(['mode' => 'revenue', 'blank' => $blank, 'records' => $forms, 'reopen' => $reopen, 'actions' => $actions], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>

@push('scripts')
<script src="{{ asset('assets/cashbook.js') }}?v=2"></script>
@endpush
@endsection
