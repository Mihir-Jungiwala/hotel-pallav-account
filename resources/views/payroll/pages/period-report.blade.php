@extends('payroll.layout', [
    'title' => 'Period Report',
    'subtitle' => 'Salary for a date range inside a single month, for one employee or all of them.',
])

@section('page')

<div class="card p-3 mb-3">
    <form method="GET" action="{{ route('payroll.period-report.index') }}" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label" for="pr_from">From date</label>
            <input type="date" name="from_date" id="pr_from" class="form-control" max="{{ date('Y-m-d') }}" value="{{ $fromDate }}">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="pr_to">To date</label>
            <input type="date" name="to_date" id="pr_to" class="form-control" max="{{ date('Y-m-d') }}" value="{{ $toDate }}">
        </div>
        <div class="col-md-3">
            <label class="form-label" for="pr_emp">Employee</label>
            <select name="employee_id" id="pr_emp" class="form-select">
                <option value="">All employees</option>
                @foreach($reportableEmployees as $employee)
                    <option value="{{ $employee->id }}" @selected((string) $selectedEmployee === (string) $employee->id)>
                        {{ $employee->name }} ({{ $employee->employee_code }}){{ $employee->is_active ? '' : ' - inactive' }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-p flex-grow-1"><i class="bi bi-eye"></i> View</button>
            @if($rows->isNotEmpty())
                <a class="btn btn-outline-p" target="_blank" title="Download PDF"
                   href="{{ route('payroll.report.period', ['from_date' => $fromDate, 'to_date' => $toDate, 'employee_id' => $selectedEmployee]) }}">
                    <i class="bi bi-download"></i>
                </a>
            @endif
        </div>

        <div class="col-12">
            <div class="form-text">
                The range must sit inside one month and cannot go past today - 01 to 15 June is valid, 25 June to 05 July is not.
            </div>
        </div>
    </form>
</div>

@if($periodError)
    <div class="alert alert-danger d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill"></i> {{ $periodError }}
    </div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>Daily / Period-wise Salary Report</span>
        @if($rows->isNotEmpty() && $fromDate && $toDate)
            <span class="text-muted fw-normal" style="font-size:12.5px;">
                {{ \Illuminate\Support\Carbon::parse($fromDate)->format('d M') }}
                to {{ \Illuminate\Support\Carbon::parse($toDate)->format('d M Y') }}
                &middot; {{ $rows->count() }} {{ Str::plural('employee', $rows->count()) }}
            </span>
        @endif
    </div>
    @include('payroll.partials._report-table', ['emptyMessage' => 'Choose a date range within one month to view the report.'])
</div>

@endsection
