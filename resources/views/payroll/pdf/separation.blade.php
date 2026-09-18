@php
    $employee = $separation->employee;
    $company = $separation->company;
    $brandName = $company->name;
    $brandMeta = trim(collect([$company->address, $company->city, $company->state])->filter()->implode(', '));
    $docType = 'Exit Record';
    $docSub = optional($employee)->employee_code;
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
        <td class="k">Date of Joining</td><td class="v">{{ optional(optional($employee)->joining_date)->format('d M Y') ?: '-' }}</td>
        <td class="k">Total Service</td><td class="v">{{ $separation->tenureLabel() }}</td>
    </tr>
</table>

<h2 class="section">Exit Details</h2>
<table class="fields avoid-break">
    <tr>
        <td class="k">Exit Type</td><td class="v">{{ $separation->separation_type }}</td>
        <td class="k">Status</td>
        <td class="v">
            <span class="chip {{ $separation->status === 'Relieved' ? 'ok' : 'warn' }}">{{ $separation->status }}</span>
        </td>
    </tr>
    <tr>
        <td class="k">Resignation Date</td><td class="v">{{ $separation->resignation_date->format('d M Y') }}</td>
        <td class="k">Last Working Day</td><td class="v">{{ $separation->last_working_date->format('d M Y') }}</td>
    </tr>
    <tr>
        <td class="k">Notice Period</td>
        <td class="v">{{ $separation->resignation_date->diffInDays($separation->last_working_date) }} days</td>
        <td class="k">Recorded By</td><td class="v">{{ optional($separation->creator)->name ?: '-' }}</td>
    </tr>
    @if($separation->hasRejoined())
    <tr>
        <td class="k">Rejoined On</td>
        <td class="v" colspan="3"><span class="chip ok">{{ $separation->rejoined_at->format('d M Y') }}</span></td>
    </tr>
    @endif
</table>

<h2 class="section">Reason</h2>
<div style="font-size:9.5pt; line-height:1.7;">{{ $separation->reason }}</div>

@if($separation->remarks)
<h2 class="section">HR Remarks</h2>
<div style="font-size:9.5pt; line-height:1.7;">{{ $separation->remarks }}</div>
@endif

<h2 class="section">Acceptance Document</h2>
<div style="font-size:9pt;">
    @if($separation->document_path)
        <span class="chip ok">Attached</span>
        <span class="muted" style="font-size:8pt;">&nbsp;A signed acceptance / relieving document is on file against this record.</span>
    @else
        <span class="chip warn">Not attached</span>
        <span class="muted" style="font-size:8pt;">&nbsp;No signed acceptance has been uploaded yet.</span>
    @endif
</div>

<div class="sign-area">
    <table>
        <tr>
            <td style="width:50%; vertical-align:bottom;">
                <div class="sign-line" style="min-width:180px;">
                    <strong>{{ optional($employee)->name }}</strong><br>
                    <span class="muted" style="font-size:7.5pt;">Employee signature</span>
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
