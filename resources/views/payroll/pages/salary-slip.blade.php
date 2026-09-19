@extends('payroll.layout', [
    'title' => 'Salary Slips',
    'subtitle' => 'Every slip processed for '.$company->name.', most recent pay period first. Slips appear here once salary is generated for a completed month from Attendance.',
])

@section('toolbar')
    <div class="search-field">
        <i class="bi bi-search"></i>
        <input type="search" class="form-control" placeholder="Search employee, ID or period&hellip;"
               data-filter-target="#slipTable" data-filter-count="#slipCount" aria-label="Search salary slips">
    </div>
    <div class="toolbar-end">
        <span class="text-muted" style="font-size:12.5px;"><span id="slipCount">{{ $processings->count() }}</span> shown</span>
    </div>
@endsection

@section('page')

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="slipTable" data-paginate="10" data-pager="#slipPager" data-sortable>
            <thead>
                <tr>
                    <th class="col-sno">S.No.</th>
                    <th data-sort="num">Period</th>
                    <th data-sort="text">Employee</th>
                    <th data-sort="text">Designation</th>
                    <th data-sort="num" class="money">Net salary</th>
                    <th data-sort="date">Processed</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($processings as $row)
                <tr data-row="{{ $row->employee_name }} {{ $row->employee_code }} {{ $row->designation }} {{ $row->periodLabel() }}">
                    <td class="sno"></td>
                    <td class="cell-main" data-sort-value="{{ $row->year }}{{ str_pad($row->month, 2, '0', STR_PAD_LEFT) }}">
                        {{ $row->periodLabel() }}
                    </td>
                    <td>
                        <span class="cell-main">{{ $row->employee_name }}</span>
                        <span class="cell-sub">{{ $row->employee_code }}</span>
                    </td>
                    <td>
                        {{ $row->designation ?: '-' }}
                        @if($row->department)<span class="cell-sub">{{ $row->department }}</span>@endif
                    </td>
                    <td class="money" data-sort-value="{{ $row->net_salary }}">₹{{ number_format($row->net_salary, 2) }}</td>
                    <td data-sort-value="{{ $row->processed_at?->format('YmdHi') }}">
                        {{ $row->processed_at?->format('d M Y') }}
                        <span class="cell-sub">{{ $row->processed_at?->format('H:i') }}</span>
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-p" href="{{ route('payroll.salary-slip.view', $row) }}" target="_blank">
                            <i class="bi bi-file-earmark-pdf"></i> Salary Slip
                        </a>
                    </td>
                </tr>
            @empty
                <tr data-empty>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="es-icon"><i class="bi bi-receipt"></i></div>
                            <div class="es-title">No salary slips yet</div>
                            <div class="es-text">Slips are created when you generate salary for a completed month. Fill in that month's attendance first, then generate from the Attendance page.</div>
                            <a class="btn btn-p mt-3" href="{{ route('payroll.attendance.index') }}">
                                <i class="bi bi-calendar3"></i> Go to Attendance
                            </a>
                        </div>
                    </td>
                </tr>
            @endforelse
            <tr data-no-match hidden>
                <td colspan="7">
                    <div class="empty-state">
                        <div class="es-icon"><i class="bi bi-search"></i></div>
                        <div class="es-title">No matching slips</div>
                        <div class="es-text">Try a different employee, ID or pay period.</div>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    @include('payroll.partials._pager', ['id' => 'slipPager'])
</div>

@endsection
