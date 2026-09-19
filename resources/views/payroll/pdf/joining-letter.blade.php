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

{{-- Who it is for and when, in one compact row --}}
<table class="avoid-break" style="margin-bottom:8px;">
    <tr>
        <td style="vertical-align:top;">
            <div class="muted" style="font-size:7pt; text-transform:uppercase; letter-spacing:0.5pt; font-weight:bold;">To</div>
            <div style="font-size:10pt; font-weight:bold; margin-top:1px;">{{ $employee->name }}</div>
            <div class="muted" style="font-size:8.3pt;">
                {{ $employee->designation }}@if($employee->department), {{ $employee->department }}@endif
                @if($employee->address) &middot; {{ $employee->address }}@endif
            </div>
        </td>
        <td style="vertical-align:top; text-align:right; width:30%;">
            <div class="muted" style="font-size:7pt; text-transform:uppercase; letter-spacing:0.5pt; font-weight:bold;">Date</div>
            <div style="font-size:9.5pt; font-weight:bold; margin-top:1px;">{{ now()->format('d M Y') }}</div>
            <div class="muted" style="font-size:7.6pt;">ID {{ $employee->employee_code }}</div>
        </td>
    </tr>
</table>

@if($letter->introduction_content)
    <div class="prose" style="margin-bottom:6px; text-align:justify;">{!! $render($letter->introduction_content) !!}</div>
@endif

{{-- The offer's key terms, at a glance --}}
<h2 class="section"><span class="dot"></span>Appointment Details</h2>
<table class="fields avoid-break">
    <tr>
        <td class="k">Designation</td><td class="v">{{ $employee->designation ?: '-' }}</td>
        <td class="k">Department</td><td class="v">{{ $employee->department ?: '-' }}</td>
    </tr>
    <tr>
        <td class="k">Joining Date</td><td class="v">{{ optional($employee->joining_date)->format('d M Y') ?: '-' }}</td>
        <td class="k">Working Hours</td><td class="v">{{ rtrim(rtrim(number_format($employee->daily_working_hours, 2), '0'), '.') }} hrs / day</td>
    </tr>
    <tr>
        <td class="k">Monthly Salary</td><td class="v">₹{{ number_format($employee->salary, 2) }}</td>
        <td class="k">Payment Mode</td><td class="v">{{ $employee->payment_mode }}</td>
    </tr>
</table>

@if($letter->roles_responsibilities)
    <h2 class="section"><span class="dot"></span>Roles &amp; Responsibilities</h2>
    <div class="prose" style="text-align:justify;">{!! $render($letter->roles_responsibilities) !!}</div>
@endif

@if($letter->terms_conditions)
    <h2 class="section"><span class="dot"></span>Terms &amp; Conditions</h2>
    <div class="prose" style="text-align:justify;">{!! $render($letter->terms_conditions) !!}</div>
@endif

@if($letter->closing_message)
    <div class="prose" style="margin-top:8px; text-align:justify;">{!! $render($letter->closing_message) !!}</div>
@endif

{{-- Company signatory --}}
@include('payroll.pdf._signatures', [
    'top' => 14,
    'cells' => [[
        'caption' => $letter->authorized_closing_text ? $render($letter->authorized_closing_text) : null,
        'image' => $signaturePath,
        'name' => $signatoryName ?: 'Authorised Signatory',
        'lines' => [$signatoryDesignation, $company->name],
    ]],
])

{{-- Employee acceptance, kept whole: it is the part that gets signed and
     returned, so it must never be split across a page break --}}
@if($letter->acceptance_heading || $letter->acceptance_content)
<div style="margin-top:14px; padding-top:9px; border-top:0.8pt dashed #C9B8F8; page-break-inside:avoid;">
    <h2 class="section" style="margin-top:0;"><span class="dot"></span>{{ $letter->acceptance_heading ? strip_tags($letter->renderFor($employee, $letter->acceptance_heading)) : 'Employee Acceptance' }}</h2>

    @if($letter->acceptance_content)
        <div class="prose" style="text-align:justify;">{!! $render($letter->acceptance_content) !!}</div>
    @endif
    @if($letter->acceptance_closing_text)
        <div class="muted" style="font-size:8.2pt; margin-top:3px;">{!! $render($letter->acceptance_closing_text) !!}</div>
    @endif

    @include('payroll.pdf._signatures', [
        'top' => 6,
        'cells' => [
            ['name' => $employee->name, 'lines' => ['Employee signature']],
            ['lines' => ['Date']],
        ],
    ])
</div>
@endif

@endsection
