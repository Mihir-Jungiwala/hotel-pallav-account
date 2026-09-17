@php
    $brandName = $company->name;
    $brandMeta = trim(collect([$company->address, $company->city, $company->state, $company->pincode])->filter()->implode(', '))
        . ($company->mobile_number ? '  ·  '.$company->mobile_number : '');
    $docType = 'Appointment Letter';
    $docSub = $employee->employee_code;

    $signatoryName = $letter->use_company_signatory ? $company->authorized_person_name : $letter->authorized_name;
    $signatoryDesignation = $letter->use_company_signatory ? $company->authorized_designation : $letter->authorized_designation;
    $signaturePath = $letter->use_company_signatory ? $company->signature_image_path : $letter->signature_image_path;
    $render = fn ($text) => nl2br(e($letter->renderFor($employee, (string) $text)));
@endphp

@extends('payroll.pdf._base')

@section('content')

<table class="avoid-break" style="margin-bottom:10px;">
    <tr>
        <td style="vertical-align:top;">
            <div class="muted" style="font-size:7.5pt; text-transform:uppercase; letter-spacing:0.5pt; font-weight:bold;">To</div>
            <div style="font-size:10pt; font-weight:bold; margin-top:2px;">{{ $employee->name }}</div>
            <div class="muted" style="font-size:8.5pt;">
                {{ $employee->designation }}@if($employee->department), {{ $employee->department }}@endif
            </div>
            @if($employee->address)
                <div class="muted" style="font-size:8pt;">{{ $employee->address }}</div>
            @endif
        </td>
        <td style="vertical-align:top; text-align:right;">
            <div class="muted" style="font-size:7.5pt; text-transform:uppercase; letter-spacing:0.5pt; font-weight:bold;">Date</div>
            <div style="font-size:9.5pt; font-weight:bold; margin-top:2px;">{{ now()->format('d M Y') }}</div>
            <div class="muted" style="font-size:7.5pt; margin-top:5px;">Employee ID</div>
            <div style="font-size:9pt; font-weight:bold;">{{ $employee->employee_code }}</div>
        </td>
    </tr>
</table>

@if($letter->subject)
    <div style="font-weight:bold; font-size:10pt; padding:8px 0; border-top:0.8pt solid #DFD3FD; border-bottom:0.8pt solid #DFD3FD; margin-bottom:10px;">
        Subject: {!! $render($letter->subject) !!}
    </div>
@endif

@if($letter->introduction_content)
    <div style="font-size:9.5pt; line-height:1.6; margin-bottom:10px;">{!! $render($letter->introduction_content) !!}</div>
@endif

{{-- Key terms as a compact table, so the reader sees the offer at a glance --}}
<h2 class="section">Appointment Details</h2>
<table class="fields avoid-break">
    <tr>
        <td class="k">Designation</td><td class="v">{{ $employee->designation ?: '—' }}</td>
        <td class="k">Department</td><td class="v">{{ $employee->department ?: '—' }}</td>
    </tr>
    <tr>
        <td class="k">Joining Date</td><td class="v">{{ optional($employee->joining_date)->format('d M Y') ?: '—' }}</td>
        <td class="k">Working Hours</td><td class="v">{{ rtrim(rtrim(number_format($employee->daily_working_hours, 2), '0'), '.') }} hrs / day</td>
    </tr>
    <tr>
        <td class="k">Monthly Salary</td><td class="v">₹{{ number_format($employee->salary, 2) }}</td>
        <td class="k">Payment Mode</td><td class="v">{{ $employee->payment_mode }}</td>
    </tr>
</table>

@if($letter->roles_responsibilities)
    <h2 class="section">Roles &amp; Responsibilities</h2>
    <div style="font-size:9.5pt; line-height:1.6;">{!! $render($letter->roles_responsibilities) !!}</div>
@endif

@if($letter->terms_conditions)
    <h2 class="section">Terms &amp; Conditions</h2>
    <div style="font-size:9.5pt; line-height:1.6;">{!! $render($letter->terms_conditions) !!}</div>
@endif

@if($letter->closing_message)
    <div style="font-size:9.5pt; line-height:1.6; margin-top:10px;">{!! $render($letter->closing_message) !!}</div>
@endif

<div class="sign-area">
    @if($letter->authorized_closing_text)
        <div style="font-size:9.5pt; margin-bottom:6px;">{!! $render($letter->authorized_closing_text) !!}</div>
    @endif
    @if($signaturePath && file_exists(public_path('storage/'.$signaturePath)))
        <img src="{{ public_path('storage/'.$signaturePath) }}" style="max-height:42px;">
    @endif
    <div class="sign-line" style="display:inline-block; min-width:200px;">
        <strong>{{ $signatoryName ?: 'Authorised Signatory' }}</strong><br>
        <span class="muted" style="font-size:8pt;">{{ $signatoryDesignation }}</span><br>
        <span class="muted" style="font-size:8pt;">{{ $company->name }}</span>
    </div>
</div>

@if($letter->acceptance_heading || $letter->acceptance_content)
<div style="margin-top:20px; padding-top:12px; border-top:0.8pt dashed #C6B0FB; page-break-inside:avoid;">
    <h2 class="section" style="margin-top:0;">{{ $letter->acceptance_heading ? strip_tags($letter->renderFor($employee, $letter->acceptance_heading)) : 'Employee Acceptance' }}</h2>

    @if($letter->acceptance_content)
        <div style="font-size:9.5pt; line-height:1.6;">{!! $render($letter->acceptance_content) !!}</div>
    @endif
    @if($letter->acceptance_closing_text)
        <div class="muted" style="font-size:8.5pt; margin-top:6px;">{!! $render($letter->acceptance_closing_text) !!}</div>
    @endif

    <table style="margin-top:26px;">
        <tr>
            <td style="width:55%;">
                <div class="sign-line" style="min-width:190px;">
                    <strong>{{ $employee->name }}</strong><br>
                    <span class="muted" style="font-size:7.5pt;">Employee signature</span>
                </div>
            </td>
            <td style="width:45%;">
                <div class="sign-line" style="min-width:150px;">
                    <span class="muted" style="font-size:7.5pt;">Date</span>
                </div>
            </td>
        </tr>
    </table>
</div>
@endif

@endsection
