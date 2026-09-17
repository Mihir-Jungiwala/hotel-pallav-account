<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="search-field" style="max-width:320px;flex:1 1 240px;">
        <i class="bi bi-search"></i>
        <input type="search" class="form-control" placeholder="Search employee or period&hellip;"
               data-filter-target="#slipTable" aria-label="Search salary slips">
    </div>
</div>

<div class="card">
    <div class="card-header">Employee Salary Slip</div>
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="slipTable" data-paginate="10" data-pager="#slipPager">
            <thead><tr><th>Period</th><th>Employee ID</th><th>Name</th><th>Designation</th><th>Net Salary</th><th>Processed</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            @forelse($processings as $row)
                <tr data-row="{{ $row->employee_name }} {{ $row->employee_code }} {{ $row->designation }} {{ $row->periodLabel() }}">
                    <td class="fw-semibold">{{ $row->periodLabel() }}</td>
                    <td>{{ $row->employee_code }}</td>
                    <td>{{ $row->employee_name }}</td>
                    <td>{{ $row->designation }}</td>
                    <td>₹{{ number_format($row->net_salary, 2) }}</td>
                    <td class="small text-muted">{{ $row->processed_at->format('d-m-Y H:i') }}</td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-p" href="{{ route('payroll.salary-slip.view', $row) }}" target="_blank"><i class="bi bi-file-earmark-pdf"></i> Salary Slip</a>
                    </td>
                </tr>
            @empty
                <tr data-empty>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="es-icon"><i class="bi bi-receipt"></i></div>
                            <div class="es-title">No salary slips yet</div>
                            <div class="es-text">Slips appear here once you generate salary for a completed month from Attendance.</div>
                        </div>
                    </td>
                </tr>
            @endforelse
            <tr data-no-match hidden>
                <td colspan="7"><div class="empty-state"><div class="es-title">No matching slips</div></div></td>
            </tr>
            </tbody>
        </table>
    </div>

    @include('payroll.partials._pager', ['id' => 'slipPager'])
</div>
