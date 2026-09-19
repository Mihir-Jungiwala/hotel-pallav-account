@php
    $employee = $separation->employee;
    $brandName = $company->name;
    $brandMeta = trim(collect([$company->address, $company->city, $company->state, $company->pincode])->filter()->implode(', '))
        . ($company->mobile_number ? '  ·  '.$company->mobile_number : '');
    $docType = 'Experience Certificate';
    $docSub = $employee->employee_code;

    $signatoryName = $letter->use_company_signatory ? $company->authorized_person_name : $letter->authorized_name;
    $signatoryDesignation = $letter->use_company_signatory ? $company->authorized_designation : $letter->authorized_designation;
    $signaturePath = $letter->use_company_signatory ? $company->signature_image_path : $letter->signature_image_path;
    $render = fn ($text) => nl2br(e($letter->renderFor($separation, (string) $text)));
@endphp

@extends('payroll.pdf._base')

@section('content')

<div style="text-align:right; font-size:8.3pt; color:#6B6486; margin-bottom:6px;">
    Date: <strong style="color:#23193F;">{{ now()->format('d M Y') }}</strong>
</div>

@if($letter->subject)
    <div style="text-align:center; font-weight:bold; font-size:12pt; letter-spacing:.5pt; text-transform:uppercase;
                padding:7px 0; border-top:1.2pt solid #5B21B6; border-bottom:1.2pt solid #5B21B6; margin-bottom:12px;">
        {!! $render($letter->subject) !!}
    </div>
@endif

<div class="prose" style="margin-bottom:8px;">
    <strong>TO WHOMSOEVER IT MAY CONCERN</strong>
</div>

@if($letter->body_content)
    <div class="prose" style="margin-bottom:8px; text-align:justify;">
        {!! $render($letter->body_content) !!}
    </div>
@endif

{{-- Verified service record, so the certificate stands on its own --}}
<h2 class="section"><span class="dot"></span>Service Record</h2>
<table class="fields avoid-break">
    <tr>
        <td class="k">Employee Name</td><td class="v">{{ $employee->name }}</td>
        <td class="k">Employee ID</td><td class="v">{{ $employee->employee_code }}</td>
    </tr>
    <tr>
        <td class="k">Designation</td><td class="v">{{ $employee->designation ?: '-' }}</td>
        <td class="k">Department</td><td class="v">{{ $employee->department ?: '-' }}</td>
    </tr>
    <tr>
        <td class="k">Date of Joining</td><td class="v">{{ optional($employee->joining_date)->format('d M Y') ?: '-' }}</td>
        <td class="k">Last Working Day</td><td class="v">{{ $separation->last_working_date->format('d M Y') }}</td>
    </tr>
    <tr>
        <td class="k">Total Service</td><td class="v">{{ $separation->tenureLabel() }}</td>
        <td class="k">Reason for Leaving</td><td class="v">{{ $separation->separation_type }}</td>
    </tr>
</table>

@if($letter->conduct_remarks)
    <div class="prose" style="margin-top:10px; text-align:justify;">
        {!! $render($letter->conduct_remarks) !!}
    </div>
@endif

@if($letter->closing_message)
    <div class="prose" style="margin-top:7px; text-align:justify;">
        {!! $render($letter->closing_message) !!}
    </div>
@endif

@include('payroll.pdf._signatures', [
    'top' => 16,
    'cells' => [[
        'caption' => $letter->authorized_closing_text ? $render($letter->authorized_closing_text) : null,
        'image' => $signaturePath,
        'name' => $signatoryName ?: 'Authorised Signatory',
        'lines' => [$signatoryDesignation, $company->name],
    ]],
])

<div class="muted" style="font-size:7.2pt; margin-top:12px; text-align:center;">
    This certificate is issued on request and reflects the service record held by {{ $company->name }}.
</div>

@endsection
