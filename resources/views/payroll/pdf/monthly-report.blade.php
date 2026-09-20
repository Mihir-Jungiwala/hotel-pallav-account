@php
    $brandName = $company->name;
    $brandMeta = trim(collect([$company->address, $company->city, $company->state])->filter()->implode(', '));
    $docType = 'Monthly Salary Report';
    $docSub = $start->format('F Y');

    $totalNet = $rows->sum('net_salary');
    $totalGross = $rows->sum('attendance_salary') + $rows->sum('overtime_amount') + $rows->sum('bonus_amount') + $rows->sum('incentive_amount');
    $totalDed = $rows->sum('deduction_amount') + $rows->sum('advance_deduction');
@endphp

@extends('payroll.pdf._base')

@section('content')

<style>
    /* Dense tables so a full month for the whole team lands on one landscape page */
    table.tight th { font-size: 6.5pt !important; padding: 4px 4px !important; letter-spacing: 0.2pt !important; }
    table.tight td { font-size: 7pt !important; padding: 3px 4px !important; white-space: nowrap; }
    table.att td { height: 13px; }
    h2.section { margin: 9px 0 4px !important; }
</style>

<table class="stats avoid-break" style="margin-bottom:4px;">
    <tr>
        <td><div class="s-label">Employees</div><div class="s-value">{{ $rows->count() }} processed</div></td>
        <td><div class="s-label">Gross Earnings</div><div class="s-value">&#8377;{{ number_format($totalGross, 2) }}</div></td>
        <td><div class="s-label">Total Deductions</div><div class="s-value">&#8377;{{ number_format($totalDed, 2) }}</div></td>
        <td><div class="s-label">Net Payout</div><div class="s-value accent">&#8377;{{ number_format($totalNet, 2) }}</div></td>
    </tr>
</table>

<h2 class="section"><span class="dot"></span>Attendance</h2>
<table class="att" style="border-collapse:collapse; width:100%; font-size:6pt;">
    <thead>
        <tr>
            <th style="background:#EFE9FE; color:#5B21B6; text-align:left; padding:4px 5px; width:92px;">Employee</th>
            @for($day = 1; $day <= $daysInMonth; $day++)
                @php $d = $start->copy()->day($day); @endphp
                <th style="background:{{ $d->isSunday() ? '#FFEDD5' : '#EFE9FE' }}; color:{{ $d->isSunday() ? '#C2410C' : '#5B21B6' }};
                           text-align:center; padding:3px 0; {{ $d->isMonday() && $day > 1 ? 'border-left:1.4pt solid #8B5CF6;' : '' }}">
                    {{ $day }}<br><span style="font-size:5pt;">{{ substr($d->format('D'), 0, 2) }}</span>
                </th>
            @endfor
            <th style="background:#EFE9FE; color:#5B21B6; text-align:right; padding:3px 4px;">Days</th>
        </tr>
    </thead>
    <tbody>
    @foreach($rows as $row)
        @php $employeeEntries = $entries[$row->employee_id] ?? collect(); @endphp
        <tr>
            <td style="padding:1px 5px; border-bottom:0.5pt solid #ECE6FB; white-space:nowrap;">
                <strong>{{ \Illuminate\Support\Str::limit($row->employee_name, 16) }}</strong>
                <span style="color:#6B6486; font-size:5.5pt;">{{ $row->employee_code }}</span>
            </td>
            @for($day = 1; $day <= $daysInMonth; $day++)
                @php
                    $d = $start->copy()->day($day);
                    $key = ($employeeEntries[$day] ?? null)?->shortcut_key;
                    $color = $key && isset($statuses[strtoupper($key)]) ? $statuses[strtoupper($key)]['color'] : null;
                @endphp
                <td style="text-align:center; padding:2px 0; border-bottom:0.5pt solid #ECE6FB;
                           {{ $d->isMonday() && $day > 1 ? 'border-left:1.4pt solid #8B5CF6;' : '' }}
                           {{ $color ? 'background:'.$color.'; color:#fff; font-weight:bold;' : 'color:#C9C3DC;' }}">
                    {{ $key ?: '·' }}
                </td>
            @endfor
            <td style="text-align:right; padding:3px 4px; border-bottom:0.5pt solid #ECE6FB;"><strong>{{ number_format($row->total_payable_days, 2) }}</strong></td>
        </tr>
    @endforeach
    </tbody>
</table>

<div style="margin-top:5px; font-size:6.5pt; color:#6B6486;">
    @foreach($statuses as $key => $meta)
        <span style="margin-right:8px;">
            <span style="padding:1px 4px; border-radius:3px; background:{{ $meta['color'] }}; color:#fff; font-weight:bold;">{{ $key }}</span>
            {{ $meta['name'] }}
        </span>
    @endforeach
</div>

<h2 class="section"><span class="dot"></span>Salary</h2>
<table class="grid tight">
    <thead>
        <tr>
            <th>ID</th><th>Name</th><th>Designation</th>
            <th class="num">Salary</th><th class="num">Days</th><th class="num">Attendance</th>
            <th class="num">OT</th><th class="num">Bonus</th><th class="num">Incentive</th>
            <th class="num">Deductions</th><th class="num">Advance</th><th class="num">Net</th>
            <th>Mode</th><th>Status</th>
        </tr>
    </thead>
    <tbody>
    @foreach($rows as $row)
        <tr>
            <td>{{ $row->employee_code }}</td>
            <td><strong>{{ \Illuminate\Support\Str::limit($row->employee_name, 20) }}</strong></td>
            <td>{{ \Illuminate\Support\Str::limit($row->designation, 28) }}</td>
            <td class="num">{{ number_format($row->monthly_salary, 2) }}</td>
            <td class="num">{{ number_format($row->total_payable_days, 2) }}</td>
            <td class="num">{{ number_format($row->attendance_salary, 2) }}</td>
            <td class="num">{{ number_format($row->overtime_amount, 2) }}</td>
            <td class="num">{{ number_format($row->bonus_amount, 2) }}</td>
            <td class="num">{{ number_format($row->incentive_amount, 2) }}</td>
            <td class="num">{{ number_format($row->deduction_amount, 2) }}</td>
            <td class="num">{{ number_format($row->advance_deduction, 2) }}</td>
            <td class="num"><strong>{{ number_format($row->net_salary, 2) }}</strong></td>
            <td>{{ $row->payment_mode }}</td>
            <td>{{ $row->payment_status }}</td>
        </tr>
    @endforeach
        <tr class="total">
            <td colspan="11">Total net payout &middot; {{ \App\Support\NumberToWords::convert($totalNet) }}</td>
            <td class="num">{{ number_format($totalNet, 2) }}</td>
            <td colspan="2"></td>
        </tr>
    </tbody>
</table>

@if($food ?? null)
    <h2 class="section"><span class="dot"></span>Pay to {{ $food['payee'] }}</h2>
    <table class="grid tight avoid-break">
        <thead>
            <tr><th style="width:30px;">#</th><th>Employee</th><th>Designation</th><th class="num">Days counted</th><th class="num">Amount</th></tr>
        </thead>
        <tbody>
        @foreach($food['rows'] as $row)
            <tr>
                <td>{{ $loop->iteration }}</td>
                <td><strong>{{ $row['name'] }}</strong> {{ $row['code'] }}</td>
                <td>{{ $row['designation'] }}</td>
                <td class="num">{{ $row['days'] }} / {{ $food['daysInMonth'] }}</td>
                <td class="num">{{ number_format($row['amount'], 2) }}</td>
            </tr>
        @endforeach
            <tr class="total">
                <td colspan="4">Total payable to {{ $food['payee'] }} &middot; {{ \App\Support\NumberToWords::convert($food['total']) }}</td>
                <td class="num">{{ number_format($food['total'], 2) }}</td>
            </tr>
        </tbody>
    </table>
    <div class="muted" style="font-size:6.5pt; margin-top:3px;">Paid by {{ $company->name }}, not deducted from salary. Counted by calendar days from the joining date.</div>
@endif

@endsection
