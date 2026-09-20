@extends('layouts.app')
@section('title', 'Expenses')

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/cashbook.css') }}?v=12">
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
    $scope = ['all' => 'All books', 'hotel' => 'Hotel Pallav', 'food' => 'Pallav Food'][$book];
@endphp

<div class="page-head reveal">
    <div>
        <div class="page-eyebrow">{{ now()->format('l, d F Y') }}</div>
        <h2 class="pms-title">Expenses</h2>
        <p class="pms-sub">Cash taken out of the books: withdrawals, miscellaneous spending and staff advances.</p>
    </div>
    <button type="button" class="btn btn-p" data-bs-toggle="modal" data-bs-target="#cashModal" data-write-only>
        <i class="bi bi-plus-lg"></i> New Entry
    </button>
</div>

<div class="kpi-grid">
    <div class="kpi reveal">
        <div class="kpi-top"><span class="kpi-icon out"><i class="bi bi-cash-stack"></i></span><span class="kpi-label">Paid out today</span></div>
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
        <div class="kpi-foot"><span class="kpi-chip">Withdrawals and misc.</span></div>
    </div>
    <div class="kpi reveal">
        <div class="kpi-top"><span class="kpi-icon in"><i class="bi bi-cup-hot"></i></span><span class="kpi-label">Pallav Food this month</span></div>
        <div class="kpi-value">{{ $money($stats['foodMonth']) }}</div>
        <div class="kpi-foot"><span class="kpi-chip">Withdrawals and misc.</span></div>
    </div>
</div>

@include('cashbook._filters', [
    'route' => 'expense.index', 'filter' => $book, 'kind' => $kind, 'q' => $q, 'searchHint' => 'Search name, head, amount or date',
    'kinds' => ['withdrawal' => 'Withdrawals', 'misc' => 'Misc. expenses', 'advance' => 'Staff advances'],
])

@if($records->isEmpty())
    <div class="card reveal">
        <div class="empty-state">
            <div class="es-icon"><i class="bi bi-cash-stack"></i></div>
            <div class="es-title">{{ $q !== '' ? 'No entries match "'.$q.'"' : 'No expenses '.($book === 'all' && $kind === 'all' ? 'yet' : 'match this view') }}</div>
            <div class="es-text">Record cash that leaves the till: what was taken out, who took it, and what it was for.</div>
            <button type="button" class="btn btn-p mt-3" data-bs-toggle="modal" data-bs-target="#cashModal" data-write-only><i class="bi bi-plus-lg"></i> New Entry</button>
        </div>
    </div>
@else
    @include('cashbook._table', ['direction' => 'out', 'noun' => 'entry', 'nouns' => 'entries', 'withKind' => true])
@endif

{{-- One pop-up for every kind of entry, new or edited, filled from the JSON below.
     Only the fields that belong to the chosen kind are shown and sent. --}}
<div class="modal fade pay-form-modal cb-modal" id="cashModal" tabindex="-1" aria-labelledby="cashModalTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <form method="POST" action="#" id="cashForm">
            @csrf
            <input type="hidden" name="_method" value="PUT" disabled data-method>
            <input type="hidden" name="_form" value="cashbook">
            <input type="hidden" name="_record_id" value="" data-record-id>
            <input type="hidden" name="_type" value="" data-type-field>

            <div class="modal-header">
                <div>
                    <div class="pms-eyebrow" data-subtitle>Expenses &middot; New</div>
                    <h5 class="modal-title" id="cashModalTitle" data-title>New Entry</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="form-section">
                    <div class="fs-head"><div class="fs-title"><i class="bi bi-ui-radios-grid"></i> What kind of entry</div></div>
                    <div class="cb-choice cb-choice-3" role="radiogroup" aria-label="Kind of entry" data-choice="kind">
                        <label><input type="radio" name="_kind" value="withdrawal" checked><span><i class="bi bi-box-arrow-up-right"></i> Cash withdrawal</span></label>
                        <label><input type="radio" name="_kind" value="misc"><span><i class="bi bi-receipt"></i> Misc. expense</span></label>
                        <label><input type="radio" name="_kind" value="advance"><span><i class="bi bi-people"></i> Staff advance</span></label>
                    </div>

                    <div class="cb-book-pick" data-show-for="withdrawal misc">
                        <div class="fs-hint mb-2">Which book it comes out of</div>
                        <div class="cb-choice" role="radiogroup" aria-label="Which book" data-choice="book">
                            <label><input type="radio" name="_book" value="hotel" checked><span><i class="bi bi-building"></i> Hotel Pallav</span></label>
                            <label><input type="radio" name="_book" value="food"><span><i class="bi bi-cup-hot"></i> Pallav Food</span></label>
                        </div>
                    </div>
                    <div class="fs-hint mt-3" data-show-for="advance"><i class="bi bi-info-circle"></i> Staff advances belong to the business as a whole, not to one book.</div>
                </div>

                <div class="form-section">
                    <div class="fs-head"><div class="fs-title"><i class="bi bi-person"></i> Who and when</div></div>
                    <div class="row g-3">
                        @include('partials._entry-stamp')
                        <div class="col-md-6" data-show-for="withdrawal">@include('cashbook._person-field', ['name' => 'withdrawer', 'label' => 'Received by'])</div>
                        <div class="col-md-6" data-show-for="advance">
                            <label class="form-label" for="cash_employee">Staff member<span class="req">*</span></label>
                            <select name="employee_id" id="cash_employee" class="form-select" required>
                                <option value="">Choose an employee&hellip;</option>
                                @foreach($activeStaff as $sp)
                                    <option value="{{ $sp->id }}">{{ $sp->name }} ({{ $sp->employee_code }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section" data-show-for="misc">
                    <div class="fs-head"><div class="fs-title"><i class="bi bi-receipt"></i> The expense</div></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="cash_expense_name">Expense name<span class="req">*</span></label>
                            <input name="expense_name" id="cash_expense_name" class="form-control" maxlength="100" required placeholder="What the money was spent on">
                        </div>
                        <div class="col-md-6">@include('partials._option-field', ['key' => 'expense_head', 'name' => 'expense_head', 'value' => null, 'label' => 'Expense Head'])</div>
                    </div>
                </div>

                <div class="form-section" data-show-for="advance">
                    <div class="fs-head"><div class="fs-title"><i class="bi bi-calendar-month"></i> Recovered from</div></div>
                    <label class="form-label" for="cash_month">For month<span class="req">*</span></label>
                    <input type="month" name="year_month" id="cash_month" class="form-control" value="{{ date('Y-m') }}" required>
                    <div class="form-text">Taken back from this month's salary.</div>
                </div>

                <div class="form-section">
                    <div class="fs-head"><div class="fs-title"><i class="bi bi-cash-stack"></i> Amount</div></div>
                    @include('partials._amount-field', ['label' => 'Amount'])
                </div>

                <div class="form-section" data-show-for="misc advance">
                    <div class="fs-head"><div class="fs-title"><i class="bi bi-chat-left-text"></i> Instruction</div></div>
                    <label class="form-label visually-hidden" for="cash_instruction">Instruction</label>
                    <textarea name="instruction" id="cash_instruction" class="form-control" rows="2" maxlength="500" placeholder="Anything to note about this entry (optional)"></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-p" data-busy-label="Saving…" data-save-label><i class="bi bi-check2"></i> Save Entry</button>
            </div>
        </form>
    </div></div>
</div>

<script type="application/json" id="cashData">{!! json_encode(['mode' => 'expense', 'blank' => $blank, 'records' => $forms, 'reopen' => $reopen, 'actions' => $actions], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!}</script>

@push('scripts')
<script src="{{ asset('assets/cashbook.js') }}?v=5"></script>
@endpush
@endsection
