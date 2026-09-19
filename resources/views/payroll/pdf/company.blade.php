@php
    $brandName = $company->name;
    $brandMeta = 'Company Code '.$company->code;
    $docType = 'Company Profile';
    $docSub = $company->is_active ? 'Active' : 'Inactive';
@endphp

@extends('payroll.pdf._base')

@section('content')

<h2 class="section"><span class="dot"></span>Basic Information</h2>
<table class="fields avoid-break">
    <tr>
        <td class="k">Company Name</td><td class="v">{{ $company->name }}</td>
        <td class="k">Company Code</td><td class="v">{{ $company->code }}</td>
    </tr>
    <tr>
        <td class="k">Owner</td><td class="v">{{ $company->owner_name ?: '-' }}</td>
        <td class="k">Status</td>
        <td class="v"><span class="chip {{ $company->is_active ? 'ok' : 'warn' }}">{{ $company->is_active ? 'Active' : 'Inactive' }}</span></td>
    </tr>
    <tr>
        <td class="k">Mobile</td><td class="v">{{ $company->mobile_number ?: '-' }}</td>
        <td class="k">Email</td><td class="v">{{ $company->email ?: '-' }}</td>
    </tr>
    <tr>
        <td class="k">Address</td>
        <td class="v" colspan="3">{{ trim(collect([$company->address, $company->city, $company->state, $company->pincode])->filter()->implode(', ')) ?: '-' }}</td>
    </tr>
</table>

<h2 class="section"><span class="dot"></span>Legal &amp; Registration</h2>
<table class="fields avoid-break">
    <tr>
        <td class="k">PAN</td><td class="v">{{ $company->pan_number ?: '-' }}</td>
        <td class="k">TAN</td><td class="v">{{ $company->tan_number ?: '-' }}</td>
    </tr>
    <tr>
        <td class="k">PF Reg. No.</td><td class="v">{{ $company->pf_registration_number ?: '-' }}</td>
        <td class="k">ESIC Reg. No.</td><td class="v">{{ $company->esic_registration_number ?: '-' }}</td>
    </tr>
    <tr>
        <td class="k">Professional Tax</td><td class="v" colspan="3">{{ $company->professional_tax_registration_number ?: '-' }}</td>
    </tr>
</table>

<h2 class="section"><span class="dot"></span>Authorised Signatory</h2>
<table class="fields avoid-break">
    <tr>
        <td class="k">Name</td><td class="v">{{ $company->authorized_person_name ?: '-' }}</td>
        <td class="k">Designation</td><td class="v">{{ $company->authorized_designation ?: '-' }}</td>
    </tr>
    <tr>
        <td class="k">Mobile</td><td class="v">{{ $company->authorized_mobile ?: '-' }}</td>
        <td class="k">Email</td><td class="v">{{ $company->authorized_email ?: '-' }}</td>
    </tr>
</table>

<h2 class="section"><span class="dot"></span>Banking</h2>
<table class="fields avoid-break">
    <tr>
        <td class="k">Bank</td><td class="v">{{ $company->bank_name ?: '-' }}</td>
        <td class="k">Branch</td><td class="v">{{ $company->branch_name ?: '-' }}</td>
    </tr>
    <tr>
        <td class="k">Account No.</td><td class="v">{{ $company->account_number ?: '-' }}</td>
        <td class="k">IFSC</td><td class="v">{{ $company->ifsc_code ?: '-' }}</td>
    </tr>
</table>

@php
    $employeeCount = $company->employees()->count();
    $activeCount = $company->employees()->where('is_active', true)->count();
    $monthlyCost = $company->employees()->where('is_active', true)->sum('salary');
@endphp

<h2 class="section"><span class="dot"></span>Payroll Footprint</h2>
<table class="grid avoid-break">
    <thead><tr><th>Employees</th><th class="num">Active</th><th class="num">Inactive</th><th class="num">Monthly Salary Commitment</th></tr></thead>
    <tbody>
        <tr>
            <td>{{ $employeeCount }} on record</td>
            <td class="num">{{ $activeCount }}</td>
            <td class="num">{{ $employeeCount - $activeCount }}</td>
            <td class="num"><strong>₹{{ number_format($monthlyCost, 2) }}</strong></td>
        </tr>
    </tbody>
</table>

<div class="sign-area">
    <table>
        <tr>
            <td style="width:55%; vertical-align:bottom;" class="muted">
                <div style="font-size:7.5pt;">Company master record. Payroll data recorded under this company is kept separate from every other company.</div>
            </td>
            <td style="width:45%; text-align:right; vertical-align:bottom;">
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
