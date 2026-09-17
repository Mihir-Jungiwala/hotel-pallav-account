@php
    $s = $paymentSummary;
    $pct = $s['net'] > 0 ? min(100, round($s['paid'] / $s['net'] * 100)) : 0;
    $statuses = \App\Models\SalaryProcessing::PAYMENT_STATUSES;
    $canEdit = auth()->user()->role !== 'Viewer';
    $initials = fn ($name) => collect(explode(' ', trim($name)))->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
@endphp

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
            @include('payroll.partials._month-nav', ['category' => 'salary-payment', 'monthStart' => $monthStart])

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
                    This month is still running. Payments open up once salary is generated from Attendance.
                @else
                    Salary hasn't been generated for this month yet.
                @endif
            </div>
            <a class="btn btn-outline-p mt-3" href="{{ route('payroll.index', ['category' => 'attendance', 'year' => $monthStart->year, 'month' => $monthStart->month]) }}">
                <i class="bi bi-calendar3"></i> Open Attendance
            </a>
        </div>
    @endif
</div>

@if($s['total'])

{{-- At-a-glance position for the month --}}
<div class="pay-stats mb-3">
    <div class="pay-stat">
        <div class="ps-label">Net Payable</div>
        <div class="ps-value">₹{{ number_format($s['net'], 2) }}</div>
        <div class="ps-sub">{{ $s['total'] }} employees</div>
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

{{-- Status filter + search --}}
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="status-chips" role="tablist" aria-label="Filter by status">
        <button type="button" class="status-chip active" data-status="">All <span>{{ $s['total'] }}</span></button>
        @foreach($statuses as $label => $meta)
            @continue(! $s['byStatus'][$label])
            <button type="button" class="status-chip" data-status="{{ $label }}">
                <i class="bi {{ $meta['icon'] }} tone-{{ $meta['tone'] }}"></i>{{ $label }} <span>{{ $s['byStatus'][$label] }}</span>
            </button>
        @endforeach
    </div>

    <div class="search-field" style="max-width:280px;flex:1 1 220px;">
        <i class="bi bi-search"></i>
        <input type="search" class="form-control" id="paymentSearch" placeholder="Search employee or reference&hellip;" aria-label="Search payments">
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="paymentTable" data-paginate="25" data-pager="#paymentPager">
            <thead>
                <tr>
                    @if($canEdit)
                        <th style="width:36px;"><input type="checkbox" class="form-check-input" id="paySelectAll" aria-label="Select all visible"></th>
                    @endif
                    <th>Employee</th>
                    <th class="money">Net Salary</th>
                    <th class="money">Paid</th>
                    <th class="money">Balance</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            @foreach($payments as $row)
                <tr data-row="{{ $row->employee_name }} {{ $row->employee_code }} {{ $row->designation }} {{ $row->payment_reference }}"
                    data-status="{{ $row->payment_status }}">
                    @if($canEdit)
                        <td><input type="checkbox" class="form-check-input pay-select" value="{{ $row->id }}" aria-label="Select {{ $row->employee_name }}"></td>
                    @endif
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="avatar">{{ $initials($row->employee_name) }}</div>
                            <div style="min-width:0;">
                                <div class="fw-semibold text-nowrap">{{ $row->employee_name }}</div>
                                <div class="text-muted text-nowrap" style="font-size:11px;">
                                    {{ $row->employee_code }} &middot;
                                    <i class="bi {{ $row->payment_mode === 'Bank' ? 'bi-bank' : 'bi-cash' }}"></i> {{ $row->payment_mode }}
                                </div>
                            </div>
                        </div>
                    </td>
                    <td class="money fw-semibold text-nowrap">₹{{ number_format($row->net_salary, 2) }}</td>
                    <td class="money text-nowrap">₹{{ number_format($row->paid_amount, 2) }}</td>
                    <td class="money text-nowrap {{ $row->balance() > 0 ? 'balance-due' : 'text-muted' }}">₹{{ number_format($row->balance(), 2) }}</td>
                    <td>
                        <span class="pay-pill pay-{{ $row->paymentTone() }}"><i class="bi {{ $row->paymentIcon() }}"></i>{{ $row->payment_status }}</span>
                        @if($row->paid_at)
                            <div class="text-muted text-nowrap mt-1" style="font-size:10.5px;">
                                {{ $row->paid_at->format('d M Y') }}@if($row->payment_reference) &middot; {{ $row->payment_reference }}@endif
                            </div>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn-icon" href="{{ route('payroll.salary-slip.view', $row) }}" target="_blank" title="Salary slip"><i class="bi bi-receipt"></i></a>
                        @if($canEdit)
                            <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#pay{{ $row->id }}" data-open-record title="Update payment"><i class="bi bi-pencil-square"></i></button>
                        @endif
                    </td>
                </tr>
            @endforeach
                <tr data-no-match hidden>
                    <td colspan="7"><div class="empty-state"><div class="es-title">No payments match</div><div class="es-text">Try another status or search term.</div></div></td>
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
    <select name="payment_status" class="form-select form-select-sm" aria-label="New status" required>
        @foreach(['Paid', 'Processing', 'On Hold', 'Pending', 'Failed'] as $status)
            <option value="{{ $status }}">Mark {{ $status }}</option>
        @endforeach
    </select>
    <input type="date" name="paid_at" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" aria-label="Payment date">
    <input type="text" name="payment_reference" class="form-control form-control-sm" placeholder="Reference (optional)" aria-label="Reference">
    <button class="btn btn-sm btn-p" data-busy-label="Updating…"><i class="bi bi-check2-all"></i> Apply</button>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="bulkClear">Clear</button>
</form>
@endif

{{-- Per-employee update --}}
@if($canEdit)
@foreach($payments as $row)
<div class="modal fade" id="pay{{ $row->id }}" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST" action="{{ route('payroll.salary-payment.update', $row) }}" class="pay-form">
        @csrf @method('PUT')
        <div class="modal-header">
            <div>
                <h5 class="modal-title mb-0">{{ $row->employee_name }}</h5>
                <div class="text-muted" style="font-size:12px;">{{ $row->employee_code }} &middot; {{ $row->periodLabel() }}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
            <div class="pay-summary mb-3">
                <div><span>Net salary</span><strong>₹{{ number_format($row->net_salary, 2) }}</strong></div>
                <div><span>Paid so far</span><strong>₹{{ number_format($row->paid_amount, 2) }}</strong></div>
                <div><span>Balance</span><strong>₹{{ number_format($row->balance(), 2) }}</strong></div>
                <div><span>Mode</span><strong>{{ $row->payment_mode }}</strong></div>
            </div>

            <label class="form-label">Payment status</label>
            <div class="status-options mb-3">
                @foreach($statuses as $label => $meta)
                    <label class="status-option tone-{{ $meta['tone'] }}">
                        <input type="radio" name="payment_status" value="{{ $label }}" @checked($row->payment_status === $label) required>
                        <span class="so-body">
                            <i class="bi {{ $meta['icon'] }}"></i>
                            <span class="so-title">{{ $label }}</span>
                            <span class="so-hint">{{ $meta['hint'] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>

            <div class="row g-3">
                <div class="col-md-4 when-partial">
                    <label class="form-label">Amount paid *</label>
                    <div class="input-group">
                        <span class="input-group-text">₹</span>
                        <input type="number" step="0.01" min="0.01" max="{{ $row->net_salary }}" name="paid_amount" class="form-control"
                               value="{{ $row->payment_status === 'Partially Paid' ? $row->paid_amount : '' }}">
                    </div>
                    <div class="form-text">Less than ₹{{ number_format($row->net_salary, 2) }}.</div>
                </div>
                <div class="col-md-4 when-settled">
                    <label class="form-label">Paid on *</label>
                    <input type="date" name="paid_at" class="form-control" max="{{ date('Y-m-d') }}"
                           value="{{ optional($row->paid_at)->format('Y-m-d') ?: date('Y-m-d') }}">
                </div>
                <div class="col-md-4 when-settled">
                    <label class="form-label">Reference <span class="wz-optional">optional</span></label>
                    <input type="text" name="payment_reference" class="form-control" maxlength="100"
                           placeholder="{{ $row->payment_mode === 'Bank' ? 'UTR / transaction ID' : 'Voucher / receipt no.' }}"
                           value="{{ $row->payment_reference }}">
                </div>
                <div class="col-12">
                    <label class="form-label">Remarks <span class="wz-optional">optional</span></label>
                    <textarea name="payment_remarks" class="form-control" rows="2" maxlength="500"
                              placeholder="Why it's on hold, why it failed, anything finance should know…">{{ $row->payment_remarks }}</textarea>
                </div>
            </div>

            @if($row->payment_updated_at)
                <div class="text-muted mt-3" style="font-size:11.5px;">
                    <i class="bi bi-clock-history"></i>
                    Last updated {{ $row->payment_updated_at->format('d M Y, H:i') }} by {{ optional($row->paymentUpdater)->name ?? 'System' }}
                </div>
            @endif
        </div>

        <div class="modal-footer"><button class="btn btn-p" data-busy-label="Saving…">Save Payment</button></div>
    </form>
</div></div></div>
@endforeach
@endif

@push('scripts')
<script>
(function(){
    const table = document.getElementById('paymentTable');
    if (!table) return;

    // --- Status chips + search, feeding the shared pager ---------------------
    const search = document.getElementById('paymentSearch');
    const chips = document.querySelectorAll('.status-chip');
    let status = '';

    function applyFilter(){
        const term = (search.value || '').trim().toLowerCase();
        let shown = 0;
        table.querySelectorAll('tbody tr[data-row]').forEach(function(row){
            const ok = (!status || row.dataset.status === status) && (!term || row.dataset.row.toLowerCase().includes(term));
            if (ok) { delete row.dataset.filteredOut; shown++; } else { row.dataset.filteredOut = '1'; }
        });
        table.querySelector('tr[data-no-match]').hidden = shown !== 0;
        table.dispatchEvent(new CustomEvent('pms:filtered'));
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

    // --- Bulk selection -------------------------------------------------------
    const bar = document.getElementById('bulkBar');
    const all = document.getElementById('paySelectAll');
    const boxes = () => [...table.querySelectorAll('.pay-select')];

    function syncSelection(){
        if (!bar) return;
        const picked = boxes().filter(b => b.checked && !b.closest('tr').dataset.filteredOut);
        document.getElementById('bulkCount').textContent = picked.length;
        document.getElementById('bulkIds').innerHTML = picked.map(b => '<input type="hidden" name="ids[]" value="' + b.value + '">').join('');
        bar.hidden = picked.length === 0;
        if (all) {
            const visible = boxes().filter(b => !b.closest('tr').hidden);
            all.checked = visible.length > 0 && visible.every(b => b.checked);
            all.indeterminate = !all.checked && visible.some(b => b.checked);
        }
    }

    if (bar) {
        table.addEventListener('change', function(e){ if (e.target.classList.contains('pay-select')) syncSelection(); });
        all && all.addEventListener('change', function(){
            boxes().forEach(b => { if (!b.closest('tr').hidden) b.checked = all.checked; });
            syncSelection();
        });
        document.getElementById('bulkClear').addEventListener('click', function(){
            boxes().forEach(b => b.checked = false);
            syncSelection();
        });

        const bulkStatus = bar.querySelector('[name=payment_status]');
        const bulkDate = bar.querySelector('[name=paid_at]');
        const bulkRef = bar.querySelector('[name=payment_reference]');
        function syncBulk(){
            const settles = bulkStatus.value === 'Paid';
            bulkDate.hidden = bulkRef.hidden = !settles;
        }
        bulkStatus.addEventListener('change', syncBulk);
        syncBulk();
    }

    // --- Modal: show only the fields the chosen status needs -------------------
    document.querySelectorAll('.pay-form').forEach(function(form){
        const partial = form.querySelectorAll('.when-partial');
        const settled = form.querySelectorAll('.when-settled');
        const amount = form.querySelector('[name=paid_amount]');
        const date = form.querySelector('[name=paid_at]');

        function sync(){
            const value = (form.querySelector('[name=payment_status]:checked') || {}).value;
            const isPartial = value === 'Partially Paid';
            const settles = isPartial || value === 'Paid';
            partial.forEach(el => el.hidden = !isPartial);
            settled.forEach(el => el.hidden = !settles);
            amount.required = isPartial;
            date.required = settles;
        }

        form.querySelectorAll('[name=payment_status]').forEach(r => r.addEventListener('change', sync));
        sync();
    });
})();
</script>
@endpush
@endif
