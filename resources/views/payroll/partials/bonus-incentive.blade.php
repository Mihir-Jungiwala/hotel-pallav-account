<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="search-field" style="max-width:320px;flex:1 1 240px;">
        <i class="bi bi-search"></i>
        <input type="search" class="form-control" placeholder="Search employee or remarks&hellip;"
               data-filter-target="#bonusTable" aria-label="Search entries">
    </div>
    <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#addBonus"><i class="bi bi-plus-lg"></i> Add Bonus / Incentive</button>
</div>

<div class="card">
    <div class="card-header">Bonus &amp; Incentive Management</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="bonusTable" data-paginate="10" data-pager="#bonusPager">
            <thead><tr><th>Date</th><th>Employee</th><th>Type</th><th>Amount</th><th>Remarks</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            @forelse($entries as $row)
                <tr data-row="{{ optional($row->employee)->name }} {{ $row->type }} {{ $row->remarks }}">
                    <td>{{ $row->entry_date->format('d-m-Y H:i') }}</td>
                    <td class="fw-semibold">{{ optional($row->employee)->name }}</td>
                    <td><span class="badge-p px-2 py-1 rounded">{{ $row->type }}</span></td>
                    <td>₹{{ number_format($row->amount, 2) }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($row->remarks, 50) }}</td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-p" data-bs-toggle="modal" data-bs-target="#editBonus{{ $row->id }}" data-open-record title="Open"><i class="bi bi-pencil-square"></i></button>
                        <form method="POST" action="{{ route('payroll.bonus-incentive.destroy', $row) }}" class="d-inline" onsubmit="return confirm('Delete this entry?')">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>

                <div class="modal fade" id="editBonus{{ $row->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                    <form method="POST" action="{{ route('payroll.bonus-incentive.update', $row) }}">@csrf @method('PUT')
                        <div class="modal-header"><h5 class="modal-title">{{ $row->type }} &mdash; {{ optional($row->employee)->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">@include('payroll.partials._bonus-fields', ['target' => $row])</div>
                        <div class="modal-footer">
                            <a class="btn btn-outline-p me-auto" href="{{ route('payroll.bonus-incentive.view', $row) }}" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Entry PDF</a>
                            <button class="btn btn-p">Save Changes</button>
                        </div>
                    </form>
                </div></div></div>
            @empty
                <tr data-empty>
                    <td colspan="6">
                        <div class="empty-state">
                            <div class="es-icon"><i class="bi bi-gift"></i></div>
                            <div class="es-title">No bonus or incentive entries</div>
                            <div class="es-text">Anything recorded here is added automatically to that month's salary processing.</div>
                        </div>
                    </td>
                </tr>
            @endforelse
            <tr data-no-match hidden>
                <td colspan="6"><div class="empty-state"><div class="es-title">No matching entries</div></div></td>
            </tr>
            </tbody>
        </table>
    </div>

    @include('payroll.partials._pager', ['id' => 'bonusPager'])
</div>

<div class="modal fade" id="addBonus" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('payroll.bonus-incentive.store') }}">@csrf
        <div class="modal-header"><h5 class="modal-title">Add Bonus / Incentive</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">@include('payroll.partials._bonus-fields', ['target' => null])</div>
        <div class="modal-footer"><button class="btn btn-p">Save Entry</button></div>
    </form>
</div></div></div>
