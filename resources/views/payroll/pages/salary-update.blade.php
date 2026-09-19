@extends('payroll.layout', [
    'title' => 'Salary Update',
    'subtitle' => 'Everyone\'s current pay and role. Open a person to revise them and to see every change ever made, newest first.',
])

@section('toolbar')
    <div class="search-field">
        <i class="bi bi-search"></i>
        <input type="search" class="form-control" placeholder="Search name, ID or designation&hellip;"
               data-filter-target="#salaryUpdateTable" data-filter-count="#suCount" aria-label="Search employees">
    </div>
    <div class="toolbar-end">
        <span class="text-muted" style="font-size:12.5px;"><span id="suCount">{{ $employees->count() }}</span> shown</span>
    </div>
@endsection

@section('page')

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="salaryUpdateTable" data-paginate="10" data-pager="#suPager" data-sortable>
            <thead>
                <tr>
                    <th class="col-sno">S.No.</th>
                    <th data-sort="text">Employee</th>
                    <th data-sort="text">Designation</th>
                    <th data-sort="num" class="money">Current salary</th>
                    <th data-sort="text">Mode</th>
                    <th data-sort="date">Last revised</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($employees as $row)
                @php $last = $row->updateHistories->first(); @endphp
                <tr data-row="{{ $row->name }} {{ $row->employee_code }} {{ $row->designation }} {{ $row->department }}">
                    <td class="sno"></td>
                    <td>
                        <a class="cell-main cell-link" href="{{ route('payroll.salary-update.show', $row) }}">{{ $row->name }}</a>
                        <span class="cell-sub">{{ $row->employee_code }}</span>
                    </td>
                    <td>
                        {{ $row->designation }}
                        @if($row->department)<span class="cell-sub">{{ $row->department }}</span>@endif
                    </td>
                    <td class="money" data-sort-value="{{ $row->salary }}">
                        <span class="cell-main">₹{{ number_format($row->salary, 2) }}</span>
                        <span class="cell-sub">{{ rtrim(rtrim(number_format($row->daily_working_hours, 2), '0'), '.') }} hrs / day</span>
                    </td>
                    <td>{{ $row->payment_mode }}</td>
                    <td data-sort-value="{{ $last?->effective_date?->format('Ymd') ?? $row->updated_at?->format('Ymd') }}">
                        @if($last)
                            {{ $last->effective_date->format('d M Y') }}
                            <span class="cell-sub">{{ $row->update_histories_count }} {{ Str::plural('change', $row->update_histories_count) }} on record</span>
                        @else
                            <span class="text-muted">Never revised</span>
                            <span class="cell-sub">Since joining</span>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-p" href="{{ route('payroll.salary-update.show', $row) }}">Open <i class="bi bi-chevron-right"></i></a>
                    </td>
                </tr>
            @empty
                <tr data-empty>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="es-icon"><i class="bi bi-clock-history"></i></div>
                            <div class="es-title">No staff to revise</div>
                            <div class="es-text">Salary revisions apply to active employees. Add someone in Staff Management first.</div>
                            <a class="btn btn-p mt-3" href="{{ route('payroll.staff.index') }}">
                                <i class="bi bi-people"></i> Go to Staff Management
                            </a>
                        </div>
                    </td>
                </tr>
            @endforelse
            <tr data-no-match hidden>
                <td colspan="7">
                    <div class="empty-state">
                        <div class="es-icon"><i class="bi bi-search"></i></div>
                        <div class="es-title">No matching staff</div>
                        <div class="es-text">Try a different name, employee ID or designation.</div>
                    </div>
                </td>
            </tr>
            </tbody>
        </table>
    </div>

    @include('payroll.partials._pager', ['id' => 'suPager'])
</div>

@endsection
