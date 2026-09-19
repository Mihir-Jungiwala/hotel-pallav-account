@extends('payroll.layout', [
    'title' => 'Bonus & Incentive Management',
    'subtitle' => 'Payments made on top of salary. Each entry is added to the salary of the month its date falls in, and appears as its own line on the salary slip.',
])

@section('page-actions')
    <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#addBonus" data-write-only>
        <i class="bi bi-plus-lg"></i> Add Entry
    </button>
@endsection

@section('toolbar')
    <div class="search-field">
        <i class="bi bi-search"></i>
        <input type="search" class="form-control" placeholder="Search employee, type or reason&hellip;"
               data-filter-target="#bonusTable" data-filter-count="#bonusCount" aria-label="Search entries">
    </div>
    <div class="toolbar-end">
        <span class="text-muted" style="font-size:12.5px;"><span id="bonusCount">{{ $entries->count() }}</span> shown</span>
    </div>
@endsection

@section('page')

<div class="pay-stats mb-3">
    <div class="pay-stat">
        <div class="ps-label">Bonus</div>
        <div class="ps-value">₹{{ number_format($summary['bonus'], 2) }}</div>
        <div class="ps-sub">Awarded in total</div>
    </div>
    <div class="pay-stat">
        <div class="ps-label">Incentive</div>
        <div class="ps-value">₹{{ number_format($summary['incentive'], 2) }}</div>
        <div class="ps-sub">Awarded in total</div>
    </div>
    <div class="pay-stat good">
        <div class="ps-label">Combined</div>
        <div class="ps-value">₹{{ number_format($summary['bonus'] + $summary['incentive'], 2) }}</div>
        <div class="ps-sub">Paid above salary</div>
    </div>
    <div class="pay-stat">
        <div class="ps-label">People</div>
        <div class="ps-value">{{ $summary['people'] }}</div>
        <div class="ps-sub">Employees who have received one</div>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="bonusTable" data-paginate="10" data-pager="#bonusPager" data-sortable>
            <thead>
                <tr>
                    <th class="col-sno">S.No.</th>
                    <th data-sort="date">Date</th>
                    <th data-sort="text">Employee</th>
                    <th data-sort="text">Type</th>
                    <th data-sort="num" class="money">Amount</th>
                    <th>Reason</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($entries as $row)
                <tr data-row="{{ optional($row->employee)->name }} {{ optional($row->employee)->employee_code }} {{ $row->type }} {{ $row->remarks }}">
                    <td class="sno"></td>
                    <td data-sort-value="{{ $row->entry_date->format('YmdHi') }}">
                        {{ $row->entry_date->format('d M Y') }}
                        <span class="cell-sub">{{ $row->entry_date->format('M Y') }} salary</span>
                    </td>
                    <td>
                        <span class="cell-main">{{ optional($row->employee)->name ?: 'Removed employee' }}</span>
                        <span class="cell-sub">{{ optional($row->employee)->employee_code }}</span>
                    </td>
                    <td><span class="badge-p px-2 py-1 rounded">{{ $row->type }}</span></td>
                    <td class="money" data-sort-value="{{ $row->amount }}">₹{{ number_format($row->amount, 2) }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($row->remarks, 44) ?: '-' }}</td>
                    <td class="text-end text-nowrap">
                        <a class="btn-icon" href="{{ route('payroll.bonus-incentive.view', $row) }}" target="_blank"
                           title="Entry PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                        <button class="btn-icon" data-bs-toggle="modal" data-bs-target="#editBonus{{ $row->id }}"
                                data-open-record title="Open record"><i class="bi bi-pencil-square"></i></button>
                        <form method="POST" action="{{ route('payroll.bonus-incentive.destroy', $row) }}" class="d-inline"
                              data-confirm="Delete the ₹{{ number_format($row->amount, 2) }} {{ strtolower($row->type) }} for {{ optional($row->employee)->name }}?">
                            @csrf @method('DELETE')
                            <button class="btn-icon danger" title="Delete"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr data-empty>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="es-icon"><i class="bi bi-gift"></i></div>
                            <div class="es-title">No bonus or incentive entries</div>
                            <div class="es-text">Anything recorded here is added automatically to that month's salary processing.</div>
                            <button class="btn btn-p mt-3" data-bs-toggle="modal" data-bs-target="#addBonus" data-write-only>
                                <i class="bi bi-plus-lg"></i> Add the first entry
                            </button>
                        </div>
                    </td>
                </tr>
            @endforelse
            <tr data-no-match hidden>
                <td colspan="7">
                    <div class="empty-state">
                        <div class="es-icon"><i class="bi bi-search"></i></div>
                        <div class="es-title">No matching entries</div>
                        <div class="es-text">Try a different employee, type or reason.</div>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    @include('payroll.partials._pager', ['id' => 'bonusPager'])
</div>

@foreach($entries as $row)
    <div class="modal fade pay-form-modal" id="editBonus{{ $row->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
            <form method="POST" action="{{ route('payroll.bonus-incentive.update', $row) }}">@csrf @method('PUT')
                <div class="modal-header">
                    <div>
                        <div class="pms-eyebrow">{{ $row->type }}</div>
                        <h5 class="modal-title">{{ optional($row->employee)->name }}</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">@include('payroll.partials._bonus-fields', ['target' => $row])</div>
                <div class="modal-footer">
                    <a class="btn btn-outline-p me-auto" href="{{ route('payroll.bonus-incentive.view', $row) }}" target="_blank">
                        <i class="bi bi-file-earmark-pdf"></i> Entry PDF
                    </a>
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-p">Save Changes</button>
                </div>
            </form>
        </div></div>
    </div>
@endforeach

<div class="modal fade pay-form-modal" id="addBonus" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
        <form method="POST" action="{{ route('payroll.bonus-incentive.store') }}">@csrf
            <div class="modal-header">
                <div>
                    <div class="pms-eyebrow">New &middot; {{ $company->name }}</div>
                    <h5 class="modal-title">Add Bonus or Incentive</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">@include('payroll.partials._bonus-fields', ['target' => null])</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-p">Save Entry</button>
            </div>
        </form>
    </div></div>
</div>

@endsection
