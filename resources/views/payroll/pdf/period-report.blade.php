@php
    $brandName = $company->name;
    $brandMeta = trim(collect([$company->address, $company->city, $company->state])->filter()->implode(', '));
    $docType = 'Period Salary Report';
    $docSub = $from->format('d M').' - '.$to->format('d M Y');
    $totalNet = $rows->sum('net_salary');
    $totalGross = $rows->sum('attendance_salary') + $rows->sum('overtime_amount') + $rows->sum('bonus_amount') + $rows->sum('incentive_amount');
    $totalDed = $rows->sum('deduction_amount') + $rows->sum('advance_deduction');
    $sum = fn ($column) => number_format($rows->sum($column), 2);
@endphp

@extends('payroll.pdf._base')

@section('content')

<table class="stats avoid-break">
    <tr>
        <td><div class="s-label">Period</div><div class="s-value">{{ $from->format('d M') }} - {{ $to->format('d M Y') }}</div></td>
        <td><div class="s-label">Scope</div><div class="s-value">{{ $rows->count() === 1 ? $rows->first()->employee_name : $rows->count().' employees' }}</div></td>
        <td><div class="s-label">Gross Earnings</div><div class="s-value">&#8377;{{ number_format($totalGross, 2) }}</div></td>
        <td><div class="s-label">Deductions &amp; Advances</div><div class="s-value">&#8377;{{ number_format($totalDed, 2) }}</div></td>
        <td><div class="s-label">Net Payout</div><div class="s-value accent">&#8377;{{ number_format($totalNet, 2) }}</div></td>
    </tr>
</table>

<table class="grid tight">
    <thead>
        <tr>
            <th>ID</th><th>Name</th><th>Designation</th>
            <th class="num">Salary</th><th class="num">Days</th><th class="num">Attendance</th>
            <th class="num">OT Hrs</th><th class="num">OT</th><th class="num">Bonus</th><th class="num">Incentive</th>
            <th class="num">Deductions</th><th class="num">Advance</th><th class="num">Pending Adv.</th><th class="num">Net</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
    @forelse($rows as $row)
        <tr>
            <td>{{ $row->employee_code }}</td>
            <td><strong>{{ \Illuminate\Support\Str::limit($row->employee_name, 18) }}</strong></td>
            <td>{{ \Illuminate\Support\Str::limit($row->designation, 18) }}</td>
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
            <td>{{ $row->payment_status }}</td>
        </tr>
    @empty
        <tr><td colspan="15" class="center muted" style="padding:14px;">No salary has been processed for this period.</td></tr>
    @endforelse
    @if($rows->isNotEmpty())
        <tr class="total">
            <td colspan="3">Total</td>
            <td class="num">{{ $sum('monthly_salary') }}</td>
            <td></td>
            <td class="num">{{ $sum('attendance_salary') }}</td>
            <td class="num">{{ $sum('overtime_hours') }}</td>
            <td class="num">{{ $sum('overtime_amount') }}</td>
            <td class="num">{{ $sum('bonus_amount') }}</td>
            <td class="num">{{ $sum('incentive_amount') }}</td>
            <td class="num">{{ $sum('deduction_amount') }}</td>
            <td class="num">{{ $sum('advance_deduction') }}</td>
            <td class="num">{{ $sum('pending_advance_amount') }}</td>
            <td class="num">{{ number_format($totalNet, 2) }}</td>
            <td></td>
        </tr>
    @endif
    </tbody>
</table>

<div class="muted" style="font-size:7.5pt; margin-top:8px;">
    In words: {{ \App\Support\NumberToWords::convert($totalNet) }}.
    Figures are taken from the salary processed for {{ $from->format('F Y') }}.
</div>

@endsection
