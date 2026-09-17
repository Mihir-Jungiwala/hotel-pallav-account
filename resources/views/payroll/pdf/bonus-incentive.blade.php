@php
    $company = $entry->company;
    $employee = $entry->employee;
    $brandName = $company->name;
    $brandMeta = trim(collect([$company->address, $company->city, $company->state])->filter()->implode(', '));
    $docType = $entry->type.' Voucher';
    $docSub = 'No. '.strtoupper(substr($entry->type, 0, 3)).'-'.str_pad($entry->id, 5, '0', STR_PAD_LEFT);
@endphp

@extends('payroll.pdf._base')

@section('content')

<table class="fields avoid-break">
    <tr>
        <td class="k">Employee</td><td class="v">{{ optional($employee)->name ?: '—' }}</td>
        <td class="k">Employee ID</td><td class="v">{{ optional($employee)->employee_code ?: '—' }}</td>
    </tr>
    <tr>
        <td class="k">Designation</td><td class="v">{{ optional($employee)->designation ?: '—' }}</td>
        <td class="k">Department</td><td class="v">{{ optional($employee)->department ?: '—' }}</td>
    </tr>
    <tr>
        <td class="k">Date</td><td class="v">{{ $entry->entry_date->format('d M Y, H:i') }}</td>
        <td class="k">Recorded By</td><td class="v">{{ optional($entry->creator)->name ?: '—' }}</td>
    </tr>
</table>

<div class="panel avoid-break">
    <table>
        <tr>
            <td style="width:62%;">
                <div class="label">{{ $entry->type }} Amount</div>
                <div class="value">₹{{ number_format($entry->amount, 2) }}</div>
                <div class="words">{{ \App\Support\NumberToWords::convert($entry->amount) }}</div>
            </td>
            <td style="width:38%; vertical-align:top; text-align:right;">
                <span class="chip">{{ $entry->type }}</span>
                <div class="muted" style="font-size:7.5pt; margin-top:6px;">
                    Included automatically in the salary processing for
                    {{ $entry->entry_date->format('F Y') }}.
                </div>
            </td>
        </tr>
    </table>
</div>

@php
    $processing = \App\Models\SalaryProcessing::where('employee_id', $entry->employee_id)
        ->where('year', $entry->entry_date->year)
        ->where('month', $entry->entry_date->month)
        ->first();
@endphp

<h2 class="section">Payroll Status</h2>
<table class="grid avoid-break">
    <thead><tr><th>Payroll Month</th><th>Processing Status</th><th class="num">Net Salary Paid</th></tr></thead>
    <tbody>
        <tr>
            <td>{{ $entry->entry_date->format('F Y') }}</td>
            <td>
                @if($processing)
                    <span class="chip ok">Processed {{ $processing->processed_at->format('d M Y') }}</span>
                @else
                    <span class="chip warn">Not yet processed</span>
                @endif
            </td>
            <td class="num">{{ $processing ? '₹'.number_format($processing->net_salary, 2) : '—' }}</td>
        </tr>
    </tbody>
</table>

@if($entry->remarks)
<h2 class="section">Remarks</h2>
<div style="font-size:9pt; line-height:1.55;">{{ $entry->remarks }}</div>
@endif

<div class="sign-area">
    <table>
        <tr>
            <td style="width:50%; vertical-align:bottom;">
                <div class="sign-line" style="min-width:180px;">
                    <strong>Received by</strong><br>
                    <span class="muted" style="font-size:7.5pt;">{{ optional($employee)->name }}</span>
                </div>
            </td>
            <td style="width:50%; text-align:right; vertical-align:bottom;">
                @if($company->signature_image_path && file_exists(public_path('storage/'.$company->signature_image_path)))
                    <img src="{{ public_path('storage/'.$company->signature_image_path) }}" style="max-height:38px;">
                @endif
                <div class="sign-line" style="display:inline-block; min-width:180px; text-align:center;">
                    <strong>{{ $company->authorized_person_name ?: 'Authorised Signatory' }}</strong><br>
                    <span class="muted" style="font-size:7.5pt;">{{ $company->authorized_designation }}</span>
                </div>
            </td>
        </tr>
    </table>
</div>

@endsection
