<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="search-field" style="max-width:320px;flex:1 1 240px;">
        <i class="bi bi-search"></i>
        <input type="search" class="form-control" placeholder="Search employee or remarks&hellip;"
               data-filter-target="#advanceTable" aria-label="Search advances">
    </div>
    <button class="btn btn-p" data-bs-toggle="modal" data-bs-target="#addAdvance"><i class="bi bi-plus-lg"></i> Add Advance</button>
</div>

<div class="card">
    <div class="card-header">Advance Management</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="advanceTable" data-paginate="10" data-pager="#advancePager">
            <thead><tr><th>Date</th><th>Employee</th><th>Amount</th><th>Type</th><th>Instalment</th><th>Recovered</th><th>Outstanding</th><th>Remarks</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            @forelse($advances as $row)
                <tr data-row="{{ optional($row->employee)->name }} {{ optional($row->employee)->employee_code }} {{ $row->remarks }}">
                    <td>{{ $row->advance_date->format('d-m-Y H:i') }}</td>
                    <td class="fw-semibold">{{ optional($row->employee)->name }}</td>
                    <td>₹{{ number_format($row->amount, 2) }}</td>
                    <td>{{ $row->deduction_type }}</td>
                    <td>₹{{ number_format($row->deduction_amount, 2) }}</td>
                    <td>₹{{ number_format($row->recovered_amount, 2) }}</td>
                    <td>₹{{ number_format($row->outstanding(), 2) }}</td>
                    <td>
                        {{ \Illuminate\Support\Str::limit($row->remarks, 40) }}
                        @if($row->is_carry_forward)
                            <span class="badge bg-info text-dark">Carry Forward</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-p" href="{{ route('payroll.advance.view', $row) }}" target="_blank"><i class="bi bi-eye"></i></a>
                        @unless($row->is_carry_forward)
                            <button class="btn btn-sm btn-outline-p" data-bs-toggle="modal" data-bs-target="#editAdvance{{ $row->id }}"><i class="bi bi-pencil"></i></button>
                            <form method="POST" action="{{ route('payroll.advance.destroy', $row) }}" class="d-inline" onsubmit="return confirm('Delete this advance?')">@csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        @endunless
                    </td>
                </tr>

                @unless($row->is_carry_forward)
                <div class="modal fade" id="editAdvance{{ $row->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
                    <form method="POST" action="{{ route('payroll.advance.update', $row) }}">@csrf @method('PUT')
                        <div class="modal-header"><h5 class="modal-title">Edit Advance</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body">@include('payroll.partials._advance-fields', ['target' => $row])</div>
                        <div class="modal-footer"><button class="btn btn-p">Save Changes</button></div>
                    </form>
                </div></div></div>
                @endunless
            @empty
                <tr data-empty>
                    <td colspan="9">
                        <div class="empty-state">
                            <div class="es-icon"><i class="bi bi-wallet2"></i></div>
                            <div class="es-title">No advances recorded</div>
                            <div class="es-text">Advances are recovered automatically from salary, with any unrecovered balance carried into the next month.</div>
                        </div>
                    </td>
                </tr>
            @endforelse
            <tr data-no-match hidden>
                <td colspan="9"><div class="empty-state"><div class="es-title">No matching advances</div></div></td>
            </tr>
            </tbody>
        </table>
    </div>

    @include('payroll.partials._pager', ['id' => 'advancePager'])
</div>

<div class="modal fade" id="addAdvance" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('payroll.advance.store') }}">@csrf
        <div class="modal-header"><h5 class="modal-title">Add Advance</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">@include('payroll.partials._advance-fields', ['target' => null])</div>
        <div class="modal-footer"><button class="btn btn-p">Save Advance</button></div>
    </form>
</div></div></div>
