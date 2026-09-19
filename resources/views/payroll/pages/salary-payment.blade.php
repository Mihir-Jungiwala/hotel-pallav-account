@php
    $s = $paymentSummary;
    $pct = $s['net'] > 0 ? min(100, round($s['paid'] / $s['net'] * 100)) : 0;
    $statuses = \App\Models\SalaryProcessing::PAYMENT_STATUSES;
    $canEdit = auth()->user()->role !== 'Viewer';
    $initials = fn ($name) => collect(explode(' ', trim((string) $name)))
        ->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp

@extends('payroll.layout', [
    'title' => 'Salary Payment',
    'subtitle' => 'Pay out the salary already processed for a month, and keep a record of when and how each person was paid.',
])

@section('page')

<div class="card mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-credit-card-2-back"></i>
            <span>{{ strtoupper($monthStart->format('F Y')) }}</span>
            @if($s['total'])
                @if($s['fullyPaid'] === $s['total'])
                    <span class="pill pill-live"><span class="dot"></span> All paid</span>
                @else
                    <span class="pill pill-unlocked">{{ $s['fullyPaid'] }} of {{ $s['total'] }} paid</span>
                @endif
            @endif
        </div>

        <div class="d-flex flex-wrap gap-2 align-items-center">
            @include('payroll.partials._month-nav', [
                'route' => 'payroll.salary-payment.index',
                'monthStart' => $monthStart,
            ])

            @if($s['total'])
                <a class="btn btn-sm btn-p" target="_blank"
                   href="{{ route('payroll.salary-payment.download', ['year' => $monthStart->year, 'month' => $monthStart->month]) }}">
                    <i class="bi bi-download"></i> Download PDF
                </a>
            @endif
        </div>
    </div>

    @if(! $s['total'])
        <div class="empty-state">
            <div class="es-icon"><i class="bi bi-credit-card-2-back"></i></div>
            <div class="es-title">No salary to pay for {{ $monthStart->format('F Y') }}</div>
            <div class="es-text">
                @if($monthStart->isSameMonth(now()))
                    This month is still running. Payments open up once salary has been generated from Attendance.
                @else
                    Salary has not been generated for this month yet.
                @endif
            </div>
            <a class="btn btn-outline-p mt-3"
               href="{{ route('payroll.attendance.index', ['year' => $monthStart->year, 'month' => $monthStart->month]) }}">
                <i class="bi bi-calendar3"></i> Open Attendance
            </a>
        </div>
    @endif
</div>

@if($s['total'])

{{-- Where the month stands, before looking at any individual row --}}
<div class="pay-stats mb-3">
    <div class="pay-stat">
        <div class="ps-label">Net payable</div>
        <div class="ps-value">₹{{ number_format($s['net'], 2) }}</div>
        <div class="ps-sub">{{ $s['total'] }} {{ Str::plural('employee', $s['total']) }}</div>
    </div>
    <div class="pay-stat good">
        <div class="ps-label">Paid</div>
        <div class="ps-value">₹{{ number_format($s['paid'], 2) }}</div>
        <div class="ps-sub">{{ $s['fullyPaid'] }} fully settled</div>
    </div>
    <div class="pay-stat {{ $s['outstanding'] > 0 ? 'warn' : 'good' }}">
        <div class="ps-label">Outstanding</div>
        <div class="ps-value">₹{{ number_format($s['outstanding'], 2) }}</div>
        <div class="ps-sub">{{ $s['total'] - $s['fullyPaid'] }} still open</div>
    </div>
    <div class="pay-stat">
        <div class="ps-label">Settled</div>
        <div class="ps-value">{{ $pct }}%</div>
        <div class="progress-track w-100 mt-2"><span style="width: {{ max(2, $pct) }}%"></span></div>
    </div>
</div>

<div class="pay-toolbar">
    <div class="status-chips" role="group" aria-label="Filter by payment status">
        <button type="button" class="status-chip active" data-status="">All <span>{{ $s['total'] }}</span></button>
        @foreach($statuses as $label => $meta)
            @continue(! $s['byStatus'][$label])
            <button type="button" class="status-chip tone-{{ $meta['tone'] }}" data-status="{{ $label }}">
                <i class="bi {{ $meta['icon'] }}"></i>{{ $label }} <span>{{ $s['byStatus'][$label] }}</span>
            </button>
        @endforeach
    </div>

    <div class="toolbar-end">
        <div class="search-field">
            <i class="bi bi-search"></i>
            <input type="search" class="form-control" id="paymentSearch"
                   placeholder="Search employee or reference&hellip;" aria-label="Search payments">
        </div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="paymentTable" data-paginate="25" data-pager="#paymentPager" data-sortable>
            <thead>
                <tr>
                    @if($canEdit)
                        <th style="width:36px;">
                            <input type="checkbox" class="form-check-input" id="paySelectAll" aria-label="Select all visible">
                        </th>
                    @endif
                    <th class="col-sno">S.No.</th>
                    <th data-sort="text">Employee</th>
                    <th data-sort="num" class="money">Net salary</th>
                    <th data-sort="num" class="money">Paid</th>
                    <th data-sort="num" class="money">Balance</th>
                    <th data-sort="date">Paid on</th>
                    <th data-sort="text">Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            @foreach($payments as $row)
                <tr data-row="{{ $row->employee_name }} {{ $row->employee_code }} {{ $row->designation }} {{ $row->payment_reference }}"
                    data-status="{{ $row->payment_status }}">
                    @if($canEdit)
                        <td>
                            <input type="checkbox" class="form-check-input pay-select" value="{{ $row->id }}"
                                   aria-label="Select {{ $row->employee_name }}">
                        </td>
                    @endif
                    <td class="sno"></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar">{{ $initials($row->employee_name) }}</div>
                            <div style="min-width:0;">
                                <div class="cell-main text-nowrap">{{ $row->employee_name }}</div>
                                <div class="cell-sub text-nowrap">
                                    {{ $row->employee_code }} &middot;
                                    <i class="bi {{ $row->payment_mode === 'Bank' ? 'bi-bank' : 'bi-cash' }}"></i> {{ $row->payment_mode }}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="money fw-semibold text-nowrap" data-sort-value="{{ $row->net_salary }}">₹{{ number_format($row->net_salary, 2) }}</td>
                    <td class="money text-nowrap" data-sort-value="{{ $row->paid_amount }}">₹{{ number_format($row->paid_amount, 2) }}</td>
                    <td class="money text-nowrap {{ $row->balance() > 0 ? 'balance-due' : 'text-muted' }}" data-sort-value="{{ $row->balance() }}">
                        ₹{{ number_format($row->balance(), 2) }}
                    </td>
                    <td class="text-nowrap" data-sort-value="{{ $row->paid_at?->format('Ymd') ?? '0' }}">
                        @if($row->paid_at)
                            {{ $row->paid_at->format('d M Y') }}
                            @if($row->payment_reference)<span class="cell-sub">{{ $row->payment_reference }}</span>@endif
                        @else
                            <span class="text-muted">Not yet</span>
                        @endif
                    </td>
                    <td>
                        <span class="pay-pill pay-{{ $row->paymentTone() }}">
                            <i class="bi {{ $row->paymentIcon() }}"></i>{{ $row->payment_status }}
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn-icon" href="{{ route('payroll.salary-slip.view', $row) }}" target="_blank"
                           title="Salary slip"><i class="bi bi-receipt"></i></a>
                        @if($canEdit)
                            <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#pay{{ $row->id }}"
                                    data-open-record title="Update payment"><i class="bi bi-pencil-square"></i></button>
                        @endif
                    </td>
                </tr>
            @endforeach
                <tr data-no-match hidden>
                    <td colspan="{{ $canEdit ? 9 : 8 }}">
                        <div class="empty-state">
                            <div class="es-icon"><i class="bi bi-search"></i></div>
                            <div class="es-title">No payments match</div>
                            <div class="es-text">Try another status or a different search term.</div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    @include('payroll.partials._pager', ['id' => 'paymentPager'])
</div>

{{-- Bulk actions surface only once something is selected --}}
@if($canEdit)
<form method="POST" action="{{ route('payroll.salary-payment.bulk') }}" class="bulk-bar" id="bulkBar" hidden>
    @csrf
    <div id="bulkIds"></div>
    <div class="bb-count"><strong id="bulkCount">0</strong> selected</div>
    <select name="payment_status" class="form-select form-select-sm" aria-label="New status" data-native required>
        @foreach(['Paid', 'Processing', 'On Hold', 'Pending', 'Failed'] as $status)
            <option value="{{ $status }}">Mark {{ $status }}</option>
        @endforeach
    </select>
    <input type="date" name="paid_at" class="form-control form-control-sm" data-native
           value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" aria-label="Payment date">
    <input type="text" name="payment_reference" class="form-control form-control-sm"
           placeholder="Reference (optional)" aria-label="Reference">
    <button class="btn btn-sm btn-p" data-busy-label="Updating…"><i class="bi bi-check2-all"></i> Apply</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="bulkClear">Clear</button>
</form>
@endif

@if($canEdit)
@foreach($payments as $row)
<div class="modal fade pay-form-modal" id="pay{{ $row->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
        <form method="POST" action="{{ route('payroll.salary-payment.update', $row) }}" class="pay-form">
            @csrf @method('PUT')
            <div class="modal-header">
                <div>
                    <div class="pms-eyebrow">{{ $row->employee_code }} &middot; {{ $row->periodLabel() }}</div>
                    <h5 class="modal-title">{{ $row->employee_name }}</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="pay-summary mb-4">
                    <div><span>Net salary</span><strong>₹{{ number_format($row->net_salary, 2) }}</strong></div>
                    <div><span>Paid so far</span><strong>₹{{ number_format($row->paid_amount, 2) }}</strong></div>
                    <div><span>Balance</span><strong>₹{{ number_format($row->balance(), 2) }}</strong></div>
                    <div><span>Mode</span><strong>{{ $row->payment_mode }}</strong></div>
                </div>

                <div class="form-section">
                    <div class="fs-head">
                        <div class="fs-title"><i class="bi bi-check2-circle"></i> Payment status</div>
                    </div>
                    <div class="status-options mb-3">
                        @foreach($statuses as $label => $meta)
                            <label class="status-option tone-{{ $meta['tone'] }}">
                                <input type="radio" name="payment_status" value="{{ $label }}"
                                       @checked($row->payment_status === $label) required>
                                <span class="so-body">
                                    <i class="bi {{ $meta['icon'] }}"></i>
                                    <span class="so-title">{{ $label }}</span>
                                    <span class="so-hint">{{ $meta['hint'] }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="form-section">
                    <div class="fs-head">
                        <div class="fs-title"><i class="bi bi-receipt-cutoff"></i> Payment details</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4" data-show-when="payment_status=Partially Paid">
                            <label class="form-label" for="pa_amt_{{ $row->id }}">Amount paid<span class="req">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">₹</span>
                                <input type="number" step="0.01" min="0.01" max="{{ $row->net_salary }}"
                                       name="paid_amount" id="pa_amt_{{ $row->id }}" class="form-control"
                                       value="{{ $row->payment_status === 'Partially Paid' ? $row->paid_amount : '' }}" required>
                            </div>
                            <div class="form-text">Less than ₹{{ number_format($row->net_salary, 2) }}.</div>
                        </div>
                        <div class="col-md-4" data-show-when="payment_status=Paid|Partially Paid">
                            <label class="form-label" for="pa_date_{{ $row->id }}">Paid on<span class="req">*</span></label>
                            <input type="date" name="paid_at" id="pa_date_{{ $row->id }}" class="form-control"
                                   max="{{ date('Y-m-d') }}"
                                   value="{{ optional($row->paid_at)->format('Y-m-d') ?: date('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-4" data-show-when="payment_status=Paid|Partially Paid">
                            <label class="form-label" for="pa_ref_{{ $row->id }}">Reference<span class="opt">optional</span></label>
                            <input type="text" name="payment_reference" id="pa_ref_{{ $row->id }}" class="form-control" maxlength="100"
                                   placeholder="{{ $row->payment_mode === 'Bank' ? 'UTR / transaction ID' : 'Voucher / receipt no.' }}"
                                   value="{{ $row->payment_reference }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="pa_rem_{{ $row->id }}">Remarks<span class="opt">optional</span></label>
                            <textarea name="payment_remarks" id="pa_rem_{{ $row->id }}" class="form-control" rows="2" maxlength="500"
                                      placeholder="Why it is on hold, why it failed, anything finance should know&hellip;">{{ $row->payment_remarks }}</textarea>
                        </div>
                    </div>
                </div>

                @if($row->payment_updated_at)
                    <div class="text-muted mt-3" style="font-size:11.5px;">
                        <i class="bi bi-clock-history"></i>
                        Last updated {{ $row->payment_updated_at->format('d M Y, H:i') }}
                        by {{ optional($row->paymentUpdater)->name ?? 'System' }}
                    </div>
                @endif
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-p" data-busy-label="Saving…">Save Payment</button>
            </div>
        </form>
    </div></div>
</div>
@endforeach
@endif

@push('scripts')
<script>
(function(){
    const table = document.getElementById('paymentTable');
    if (!table) return;

    // --- Status chips + search, both feeding the shared pager ----------------
    const search = document.getElementById('paymentSearch');
    const chips = document.querySelectorAll('.status-chip');
    let status = '';

    const smart = window.PMSSearch || null;
    if (smart) smart.enhanceInput(search, table);

    function applyFilter(){
        const raw = (search.value || '').trim();
        const query = smart ? smart.compileFor(raw, table) : null;
        const plain = raw.toLowerCase();
        let shown = 0;
        table.querySelectorAll('tbody tr[data-row]').forEach(function(row){
            const textOk = query
                ? (query.empty || query.test(smart.index(row)))
                : (!plain || row.dataset.row.toLowerCase().includes(plain));
            const ok = (!status || row.dataset.status === status) && textOk;
            if (ok) { delete row.dataset.filteredOut; shown++; } else { row.dataset.filteredOut = '1'; }
        });
        table.querySelector('tr[data-no-match]').hidden = shown !== 0;
        table.dispatchEvent(new CustomEvent('pms:filtered'));
        if (smart) smart.decorate(table, search, query, shown);
        syncSelection();
    }

    chips.forEach(function(chip){
        chip.addEventListener('click', function(){
            chips.forEach(c => c.classList.remove('active'));
            chip.classList.add('active');
            status = chip.dataset.status;
            applyFilter();
        });
    });
    search.addEventListener('input', applyFilter);

    // --- Bulk selection ------------------------------------------------------
    const bar = document.getElementById('bulkBar');
    const all = document.getElementById('paySelectAll');
    const boxes = () => [...table.querySelectorAll('.pay-select')];

    function syncSelection(){
        if (!bar) return;
        const picked = boxes().filter(b => b.checked && !b.closest('tr').dataset.filteredOut);
        document.getElementById('bulkCount').textContent = picked.length;
        document.getElementById('bulkIds').innerHTML =
            picked.map(b => '<input type="hidden" name="ids[]" value="' + b.value + '">').join('');
        bar.hidden = picked.length === 0;
        if (all) {
            const visible = boxes().filter(b => !b.closest('tr').hidden);
            all.checked = visible.length > 0 && visible.every(b => b.checked);
            all.indeterminate = !all.checked && visible.some(b => b.checked);
        }
    }

    if (bar) {
        table.addEventListener('change', function(e){
            if (e.target.classList.contains('pay-select')) syncSelection();
        });
        all && all.addEventListener('change', function(){
            boxes().forEach(b => { if (!b.closest('tr').hidden) b.checked = all.checked; });
            syncSelection();
        });
        document.getElementById('bulkClear').addEventListener('click', function(){
            boxes().forEach(b => b.checked = false);
            syncSelection();
        });

        const bulkStatus = bar.querySelector('[name=payment_status]');

        // Marking several people at once is worth a second look. The shared
        // confirmation reads its wording off the form, so it only has to be
        // kept current with what is selected.
        function syncConfirm(){
            const count = parseInt(document.getElementById('bulkCount').textContent, 10) || 0;
            const status = bulkStatus.value;
            bar.dataset.confirmTitle = 'Mark ' + count + ' ' + (count === 1 ? 'payment' : 'payments') + ' as ' + status + '?';
            bar.dataset.confirm = status === 'Paid'
                ? 'Each is recorded as paid in full on the date given.'
                : 'Each one\'s status changes to ' + status + '.';
            bar.dataset.confirmLabel = 'Mark ' + status;
            bar.dataset.confirmTone = status === 'Failed' ? 'danger' : 'primary';
        }
        bar.setAttribute('data-confirm', '');
        bar.addEventListener('pms:before-confirm', syncConfirm);
        syncConfirm();
        const bulkDate = bar.querySelector('[name=paid_at]');
        const bulkRef = bar.querySelector('[name=payment_reference]');
        function syncBulk(){
            const settles = bulkStatus.value === 'Paid';
            bulkDate.hidden = bulkRef.hidden = !settles;
        }
        bulkStatus.addEventListener('change', syncBulk);
        syncBulk();
    }

    // Which fields each status needs is declared on the fields themselves with
    // data-show-when, so there is nothing to wire up here.
})();
</script>
@endpush

@endif

@endsection
