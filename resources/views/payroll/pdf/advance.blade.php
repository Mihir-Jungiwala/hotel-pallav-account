@php
    $company = $advance->company;
    $employee = $advance->employee;
    $brandName = $company->name;
    $brandMeta = trim(collect([$company->address, $company->city, $company->state])->filter()->implode(', '));
    $docType = 'Advance Voucher';
    $docSub = 'No. ADV-'.str_pad($advance->id, 5, '0', STR_PAD_LEFT);

    $outstanding = $advance->outstanding();
    $recovered = (float) $advance->recovered_amount;
    $progress = (float) $advance->amount > 0 ? min(100, round($recovered / (float) $advance->amount * 100)) : 0;
@endphp

@extends('payroll.pdf._base')

@section('content')

<table class="fields avoid-break">
    <tr>
        <td class="k">Employee</td><td class="v">{{ optional($employee)->name ?: '-' }}</td>
        <td class="k">Employee ID</td><td class="v">{{ optional($employee)->employee_code ?: '-' }}</td>
    </tr>
    <tr>
        <td class="k">Designation</td><td class="v">{{ optional($employee)->designation ?: '-' }}</td>
        <td class="k">Department</td><td class="v">{{ optional($employee)->department ?: '-' }}</td>
    </tr>
    <tr>
        <td class="k">Advance Date</td><td class="v">{{ $advance->advance_date->format('d M Y, H:i') }}</td>
        <td class="k">Recorded By</td><td class="v">{{ optional($advance->creator)->name ?: '-' }}</td>
    </tr>
</table>

<div class="panel avoid-break">
    <table>
        <tr>
            <td style="width:58%;">
                <div class="label">Advance Amount</div>
                <div class="value">₹{{ number_format($advance->amount, 2) }}</div>
                <div class="words">{{ \App\Support\NumberToWords::convert($advance->amount) }}</div>
            </td>
            <td style="width:42%; vertical-align:top;">
                <table>
                    <tr>
                        <td class="muted" style="font-size:8pt; padding:1.5px 0;">Recovery Type</td>
                        <td class="num" style="font-size:8pt; padding:1.5px 0;"><strong>{{ $advance->deduction_type }}</strong></td>
                    </tr>
                    <tr>
                        <td class="muted" style="font-size:8pt; padding:1.5px 0;">Per Cycle</td>
                        <td class="num" style="font-size:8pt; padding:1.5px 0;">₹{{ number_format($advance->deduction_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="muted" style="font-size:8pt; padding:1.5px 0;">Recovered</td>
                        <td class="num" style="font-size:8pt; padding:1.5px 0;">₹{{ number_format($recovered, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="muted" style="font-size:8pt; padding:1.5px 0;">Outstanding</td>
                        <td class="num" style="font-size:8pt; padding:1.5px 0;"><strong>₹{{ number_format($outstanding, 2) }}</strong></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>

<h2 class="section">Recovery Status</h2>
<table class="grid avoid-break">
    <thead><tr><th>Stage</th><th class="num">Amount</th><th class="num">Share</th><th>Status</th></tr></thead>
    <tbody>
        <tr>
            <td>Recovered to date</td>
            <td class="num">{{ number_format($recovered, 2) }}</td>
            <td class="num">{{ $progress }}%</td>
            <td>{{ $recovered > 0 ? 'Deducted from salary' : 'Not started' }}</td>
        </tr>
        <tr>
            <td>Outstanding balance</td>
            <td class="num">{{ number_format($outstanding, 2) }}</td>
            <td class="num">{{ 100 - $progress }}%</td>
            <td>
                @if($advance->is_settled)
                    <span class="chip ok">Settled</span>
                @else
                    <span class="chip warn">Pending recovery</span>
                @endif
            </td>
        </tr>
        <tr class="total">
            <td>Total advance</td>
            <td class="num">{{ number_format($advance->amount, 2) }}</td>
            <td class="num">100%</td>
            <td></td>
        </tr>
    </tbody>
</table>

@if($advance->is_carry_forward)
    <div class="muted" style="font-size:8pt; margin-top:8px;">
        This is a system-generated carry-forward record created during salary processing for the unrecovered balance of an earlier advance.
    </div>
@endif

@if($advance->remarks)
<h2 class="section">Remarks</h2>
<div style="font-size:9pt; line-height:1.55;">{{ $advance->remarks }}</div>
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
