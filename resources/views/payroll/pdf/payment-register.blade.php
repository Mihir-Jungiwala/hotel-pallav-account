@php
    $brandName = $company->name;
    $brandMeta = trim(collect([$company->address, $company->city, $company->state])->filter()->implode(', '));
    $docType = 'Salary Payment Register';
    $docSub = $start->format('F Y');

    $net = $payments->sum('net_salary');
    $paid = $payments->sum('paid_amount');
    $outstanding = max(0, $net - $paid);
    $fullyPaid = $payments->where('payment_status', 'Paid')->count();

    $tones = [
        'Pending' => ['#FEF3C7', '#B45309'], 'Processing' => ['#DBEAFE', '#1D4ED8'], 'On Hold' => ['#E2E8F0', '#475569'],
        'Partially Paid' => ['#EDE9FE', '#7C3AED'], 'Paid' => ['#DCFCE7', '#15803D'], 'Failed' => ['#FEE2E2', '#B91C1C'],
    ];
@endphp

@extends('payroll.pdf._base')

@section('content')

<style>
    table.tight th { font-size: 7pt !important; padding: 5px 5px !important; white-space: nowrap; }
    table.tight td { font-size: 7.5pt !important; padding: 4px 5px !important; white-space: nowrap; }
    table.tight td.wrap { white-space: normal; }
</style>

<table class="grid avoid-break tight" style="margin-bottom:10px;">
    <thead><tr>
        <th class="num">Net Payable</th><th class="num">Paid</th><th class="num">Outstanding</th><th class="num">Fully Settled</th>
    </tr></thead>
    <tbody><tr>
        <td class="num"><strong>{{ number_format($net, 2) }}</strong></td>
        <td class="num">{{ number_format($paid, 2) }}</td>
        <td class="num">{{ number_format($outstanding, 2) }}</td>
        <td class="num">{{ $fullyPaid }} of {{ $payments->count() }}</td>
    </tr></tbody>
</table>

<table class="grid tight">
    <thead>
        <tr>
            <th>#</th><th>ID</th><th>Employee</th><th>Mode</th>
            <th class="num">Net Salary</th><th class="num">Paid</th><th class="num">Balance</th>
            <th>Status</th><th>Paid On</th><th>Reference</th><th>Remarks</th>
        </tr>
    </thead>
    <tbody>
    @foreach($payments as $i => $row)
        @php [$bg, $fg] = $tones[$row->payment_status] ?? ['#EFE9FE', '#5B21B6']; @endphp
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $row->employee_code }}</td>
            <td><strong>{{ \Illuminate\Support\Str::limit($row->employee_name, 22) }}</strong></td>
            <td>{{ $row->payment_mode }}</td>
            <td class="num">{{ number_format($row->net_salary, 2) }}</td>
            <td class="num">{{ number_format($row->paid_amount, 2) }}</td>
            <td class="num">{{ number_format($row->balance(), 2) }}</td>
            <td><span style="padding:2px 7px; border-radius:8px; background:{{ $bg }}; color:{{ $fg }}; font-weight:bold; font-size:7pt;">{{ $row->payment_status }}</span></td>
            <td>{{ optional($row->paid_at)->format('d M Y') ?: '—' }}</td>
            <td>{{ $row->payment_reference ?: '—' }}</td>
            <td class="wrap">{{ \Illuminate\Support\Str::limit($row->payment_remarks, 60) ?: '—' }}</td>
        </tr>
    @endforeach
        <tr class="total">
            <td colspan="4" class="wrap">Total &middot; {{ \App\Support\NumberToWords::convert($net) }}</td>
            <td class="num">{{ number_format($net, 2) }}</td>
            <td class="num">{{ number_format($paid, 2) }}</td>
            <td class="num">{{ number_format($outstanding, 2) }}</td>
            <td colspan="4"></td>
        </tr>
    </tbody>
</table>

<div class="sign-area">
    <table>
        <tr>
            <td style="width:50%; vertical-align:bottom;">
                <div class="sign-line" style="min-width:180px;">
                    <strong>Prepared by</strong><br>
                    <span class="muted" style="font-size:7.5pt;">{{ auth()->user()->name }}</span>
                </div>
            </td>
            <td style="width:50%; text-align:right; vertical-align:bottom;">
                <div class="sign-line" style="display:inline-block; min-width:180px; text-align:center;">
                    <strong>{{ $company->authorized_person_name ?: 'Authorised Signatory' }}</strong><br>
                    <span class="muted" style="font-size:7.5pt;">{{ $company->authorized_designation }}</span>
                </div>
            </td>
        </tr>
    </table>
</div>

@endsection
