@extends('payroll.layout', [
    'title' => 'Advance Management',
    'subtitle' => 'Money paid out ahead of salary. Advances are recovered automatically during salary processing, and anything still outstanding carries into the next month.',
])

@section('page-actions')
    <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#addAdvance" data-write-only>
        <i class="bi bi-plus-lg"></i> Add Advance
    </button>
@endsection

@section('toolbar')
    <div class="search-field">
        <i class="bi bi-search"></i>
        <input type="search" class="form-control" placeholder="Search employee or remarks&hellip;"
               data-filter-target="#advanceTable" data-filter-count="#advanceCount" aria-label="Search advances">
    </div>
    <div class="toolbar-end">
        <span class="text-muted" style="font-size:12.5px;"><span id="advanceCount">{{ $advances->count() }}</span> shown</span>
    </div>
@endsection

@section('page')

<div class="pay-stats mb-3">
    <div class="pay-stat">
        <div class="ps-label">Total issued</div>
        <div class="ps-value">₹{{ number_format($summary['issued'], 2) }}</div>
        <div class="ps-sub">{{ $advances->count() }} {{ Str::plural('advance', $advances->count()) }} on record</div>
    </div>
    <div class="pay-stat good">
        <div class="ps-label">Recovered</div>
        <div class="ps-value">₹{{ number_format($summary['recovered'], 2) }}</div>
        <div class="ps-sub">Deducted through salary</div>
    </div>
    <div class="pay-stat {{ $summary['outstanding'] > 0 ? 'warn' : 'good' }}">
        <div class="ps-label">Outstanding</div>
        <div class="ps-value">₹{{ number_format($summary['outstanding'], 2) }}</div>
        <div class="ps-sub">Still to be recovered</div>
    </div>
    <div class="pay-stat">
        <div class="ps-label">Open advances</div>
        <div class="ps-value">{{ $summary['open'] }}</div>
        <div class="ps-sub">Not yet fully settled</div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="advanceTable" data-paginate="10" data-pager="#advancePager" data-sortable>
            <thead>
                <tr>
                    <th class="col-sno">S.No.</th>
                    <th data-sort="date">Date</th>
                    <th data-sort="text">Employee</th>
                    <th data-sort="num" class="money">Amount</th>
                    <th data-sort="text">Recovery</th>
                    <th data-sort="num" class="money">Recovered</th>
                    <th data-sort="num" class="money">Outstanding</th>
                    <th>Remarks</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($advances as $row)
                @php $outstanding = $row->outstanding(); @endphp
                <tr data-row="{{ optional($row->employee)->name }} {{ optional($row->employee)->employee_code }} {{ $row->remarks }}">
                    <td class="sno"></td>
                    <td data-sort-value="{{ $row->advance_date->format('YmdHi') }}">
                        {{ $row->advance_date->format('d M Y') }}
                        <span class="cell-sub">{{ $row->advance_date->format('H:i') }}</span>
                    </td>
                    <td>
                        <span class="cell-main">{{ optional($row->employee)->name ?: 'Removed employee' }}</span>
                        <span class="cell-sub">{{ optional($row->employee)->employee_code }}</span>
                    </td>
                    <td class="money" data-sort-value="{{ $row->amount }}">₹{{ number_format($row->amount, 2) }}</td>
                    <td>
                        {{ $row->deduction_type }}
                        @if($row->deduction_type === 'Monthly')
                            <span class="cell-sub">₹{{ number_format($row->deduction_amount, 2) }} / month</span>
                        @endif
                    </td>
                    <td class="money" data-sort-value="{{ $row->recovered_amount }}">₹{{ number_format($row->recovered_amount, 2) }}</td>
                    <td class="money" data-sort-value="{{ $outstanding }}">
                        <span class="{{ $outstanding > 0 ? 'balance-due' : 'text-muted' }}">₹{{ number_format($outstanding, 2) }}</span>
                    </td>
                    <td>
                        {{ \Illuminate\Support\Str::limit($row->remarks, 36) }}
                        @if($row->is_carry_forward)
                            <span class="badge bg-info text-dark">Carried forward</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn-icon" href="{{ route('payroll.advance.view', $row) }}" target="_blank"
                           title="Advance PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                        @unless($row->is_carry_forward)
                            <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#editAdvance{{ $row->id }}"
                                    data-open-record title="Open record"><i class="bi bi-pencil-square"></i></button>
                            <form method="POST" action="{{ route('payroll.advance.destroy', $row) }}" class="d-inline"
                                  data-confirm="Delete the ₹{{ number_format($row->amount, 2) }} advance for {{ optional($row->employee)->name }}?">
                                @csrf @method('DELETE')
                                <button class="btn-icon danger" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr data-empty>
                    <td colspan="9">
                        <div class="empty-state">
                            <div class="es-icon"><i class="bi bi-wallet2"></i></div>
                            <div class="es-title">No advances recorded</div>
                            <div class="es-text">Record an advance here and salary processing will recover it automatically, carrying any balance into the next month.</div>
                            <button class="btn btn-p mt-3" data-bs-toggle="modal" data-bs-target="#addAdvance" data-write-only>
                                <i class="bi bi-plus-lg"></i> Record the first advance
                            </button>
                        </div>
                    </td>
                </tr>
            @endforelse
            <tr data-no-match hidden>
                <td colspan="9">
                    <div class="empty-state">
                        <div class="es-icon"><i class="bi bi-search"></i></div>
                        <div class="es-title">No matching advances</div>
                        <div class="es-text">Try a different employee name or remark.</div>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    @include('payroll.partials._pager', ['id' => 'advancePager'])
</div>

{{-- Modals live outside the table: a <form> is not valid content inside a
     <tbody>, and browsers repair that in ways that break the form. --}}
@foreach($advances as $row)
    @continue($row->is_carry_forward)
    <div class="modal fade pay-form-modal" id="editAdvance{{ $row->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
            <form method="POST" action="{{ route('payroll.advance.update', $row) }}">@csrf @method('PUT')
                <div class="modal-header">
                    <div>
                        <div class="pms-eyebrow">Advance</div>
                        <h5 class="modal-title">{{ optional($row->employee)->name }}</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">@include('payroll.partials._advance-fields', ['target' => $row])</div>
                <div class="modal-footer">
                    <a class="btn btn-outline-p me-auto" href="{{ route('payroll.advance.view', $row) }}" target="_blank">
                        <i class="bi bi-file-earmark-pdf"></i> Advance PDF
                    </a>
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-p">Save Changes</button>
                </div>
            </form>
        </div></div>
    </div>
@endforeach

<div class="modal fade pay-form-modal" id="addAdvance" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
        <form method="POST" action="{{ route('payroll.advance.store') }}">@csrf
            <div class="modal-header">
                <div>
                    <div class="pms-eyebrow">New &middot; {{ $company->name }}</div>
                    <h5 class="modal-title">Add Advance</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">@include('payroll.partials._advance-fields', ['target' => null])</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-p">Save Advance</button>
            </div>
        </form>
    </div></div>
</div>

@push('scripts')
<script>
(function(){
    // How long a monthly recovery takes to clear, worked out as they type
    const money = (n) => '₹' + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function refresh(form){
        const hint = form.querySelector('[data-instalment-hint]');
        if (!hint) return;

        const amount = parseFloat(form.querySelector('.advance-amount')?.value);
        const instalment = parseFloat(form.querySelector('.advance-instalment')?.value);
        const text = hint.querySelector('span');

        if (!(amount > 0) || !(instalment > 0)) {
            text.textContent = 'Enter the amount and instalment to see how long this takes to clear.';
            hint.classList.remove('warn');
            return;
        }
        if (instalment > amount) {
            text.textContent = 'The instalment is more than the advance itself.';
            hint.classList.add('warn');
            return;
        }

        hint.classList.remove('warn');
        const months = Math.ceil(amount / instalment);
        const last = amount - instalment * (months - 1);
        text.textContent = months === 1
            ? 'Clears in a single month.'
            : 'Clears in ' + months + ' months' + (Math.abs(last - instalment) > 0.005 ? ', the last one being ' + money(last) + '.' : '.');
    }

    document.addEventListener('input', function(event){
        if (!event.target.matches('.advance-amount, .advance-instalment')) return;
        refresh(event.target.closest('form'));
    });
    document.querySelectorAll('form').forEach(function(form){
        if (form.querySelector('[data-instalment-hint]')) refresh(form);
    });
})();
</script>
@endpush

@endsection
