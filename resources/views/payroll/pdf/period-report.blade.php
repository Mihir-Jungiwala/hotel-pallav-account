@php
    $brandName = $company->name;
    $brandMeta = trim(collect([$company->address, $company->city, $company->state])->filter()->implode(', '));
    $docType = 'Period Salary Report';
    $docSub = $from->format('d M').' – '.$to->format('d M Y');
    $totalNet = $rows->sum('net_salary');
@endphp

@extends('payroll.pdf._base')

@section('content')

<table class="fields avoid-break" style="margin-bottom:6px;">
    <tr>
        <td class="k">Period</td><td class="v">{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</td>
        <td class="k">Scope</td><td class="v">{{ $rows->count() === 1 ? $rows->first()->employee_name : $rows->count().' employees' }}</td>
    </tr>
</table>

<table class="grid" style="font-size:7.5pt;">
    <thead>
        <tr>
            <th>ID</th><th>Name</th><th>Designation</th>
            <th class="num">Salary</th><th class="num">Payable Days</th><th class="num">Attendance</th>
            <th class="num">OT hrs</th><th class="num">OT</th><th class="num">Bonus</th><th class="num">Incentive</th>
            <th class="num">Deductions</th><th class="num">Advance</th><th class="num">Pending Adv.</th><th class="num">Net</th>
            <th>Mode</th><th>Status</th>
        </tr>
    </thead>
    <tbody>
    @foreach($rows as $row)
        <tr>
            <td>{{ $row->employee_code }}</td>
            <td><strong>{{ $row->employee_name }}</strong></td>
            <td>{{ $row->designation }}</td>
            <td class="num">{{ number_format($row->monthly_salary, 2) }}</td>
            <td class="num">{{ number_format($row->total_payable_days, 2) }}</td>
            <td class="num">{{ number_format($row->attendance_salary, 2) }}</td>
            <td class="num">{{ number_format($row->overtime_hours, 2) }}</td>
            <td class="num">{{ number_format($row->overtime_amount, 2) }}</td>
            <td class="num">{{ number_format($row->bonus_amount, 2) }}</td>
            <td class="num">{{ number_format($row->incentive_amount, 2) }}</td>
            <td class="num">{{ number_format($row->deduction_amount, 2) }}</td>
            <td class="num">{{ number_format($row->advance_deduction, 2) }}</td>
            <td class="num">{{ number_format($row->pending_advance_amount, 2) }}</td>
            <td class="num"><strong>{{ number_format($row->net_salary, 2) }}</strong></td>
            <td>{{ $row->payment_mode }}</td>
            <td>{{ $row->payment_status }}</td>
        </tr>
    @endforeach
        <tr class="total">
            <td colspan="13">Total net payout</td>
            <td class="num">{{ number_format($totalNet, 2) }}</td>
            <td colspan="2"></td>
        </tr>
    </tbody>
</table>

<div class="muted" style="font-size:7.5pt; margin-top:8px;">
    In words: {{ \App\Support\NumberToWords::convert($totalNet) }}.
    Figures are taken from the salary processed for {{ $from->format('F Y') }}.
</div>

@endsection
