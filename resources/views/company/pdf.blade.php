{{-- A company profile as a sheet to share or print. It uses the payroll
     document shell, so it reads as one family with the rest of the paperwork. --}}
@php
    $profile = $record;

    $brandName = $profile->name;
    $brandMeta = collect([$profile->address, $profile->pincode, $profile->country])->filter()->implode(', ') ?: 'Company profile';
    $docType = 'Company Profile';
    $docSub = $profile->gst_number ? 'GST '.$profile->gst_number : 'No GST number';

    $people = collect($profile->contacts ?? []);
    $rates = ['discount_percentage' => 'Discount', 'gst_percentage' => 'GST', 'tcs_percentage' => 'TCS', 'tds_percentage' => 'TDS'];
    $rate = fn ($v) => $v === null || (float) $v <= 0 ? null : rtrim(rtrim(number_format((float) $v, 2), '0'), '.').'%';
@endphp

@extends('payroll.pdf._base')

@section('content')

<h2 class="section"><span class="dot"></span>Billing Rates</h2>
<table class="stats avoid-break">
    <tr>
        @foreach($rates as $field => $label)
            <td>
                <div class="s-label">{{ $label }}</div>
                <div class="s-value {{ $rate($profile->{$field}) ? 'accent' : '' }}">{{ $rate($profile->{$field}) ?? 'None' }}</div>
            </td>
        @endforeach
    </tr>
</table>

<h2 class="section"><span class="dot"></span>Company Details</h2>
<table class="fields avoid-break">
    <tr>
        <td class="k">Company Name</td><td class="v">{{ $profile->name }}</td>
        <td class="k">GST Number</td><td class="v">{{ $profile->gst_number ?: '-' }}</td>
    </tr>
    <tr>
        <td class="k">Email</td><td class="v">{{ $profile->email ?: '-' }}</td>
        <td class="k">Nationality</td><td class="v">{{ $profile->nationality ?: '-' }}</td>
    </tr>
    <tr>
        <td class="k">Mobile</td><td class="v">{{ $profile->mobile_number ?: '-' }}</td>
        <td class="k">Landline</td><td class="v">{{ $profile->phone_number ?: '-' }}</td>
    </tr>
</table>

<h2 class="section"><span class="dot"></span>Address</h2>
<table class="fields avoid-break">
    <tr>
        <td class="k">Address</td><td class="v" colspan="3">{{ $profile->address ?: '-' }}</td>
    </tr>
    <tr>
        <td class="k">Pincode</td><td class="v">{{ $profile->pincode ?: '-' }}</td>
        <td class="k">Country</td><td class="v">{{ $profile->country ?: '-' }}</td>
    </tr>
</table>

<h2 class="section"><span class="dot"></span>People to Contact</h2>
<table class="grid avoid-break">
    <thead><tr><th style="width:6%;" class="center">#</th><th style="width:26%;">Name</th><th style="width:24%;">Role</th><th style="width:28%;">Email</th><th style="width:16%;">Mobile</th></tr></thead>
    <tbody>
    @forelse($people as $index => $person)
        <tr>
            <td class="center">{{ $index + 1 }}</td>
            <td><strong>{{ $person['name'] ?: '-' }}</strong>@if($index === 0) <span class="chip">Main</span>@endif</td>
            <td>{{ $person['role'] ?: '-' }}</td>
            <td>{{ $person['email'] ?: '-' }}</td>
            <td>{{ $person['mobile'] ?: '-' }}</td>
        </tr>
    @empty
        <tr><td colspan="5" class="center muted">No one recorded for this company.</td></tr>
    @endforelse
    </tbody>
</table>

@if($profile->instruction)
<h2 class="section"><span class="dot"></span>Instruction</h2>
<div class="prose">{{ $profile->instruction }}</div>
@endif

<div class="sign-area">
    <table>
        <tr>
            <td style="width:55%; vertical-align:bottom;" class="muted">
                <div style="font-size:7.5pt;">
                    Company master record held by {{ config('app.name', 'Hotel Pallav') }}.
                    @if($profile->creator) Added by {{ $profile->creator->name }}@if($profile->created_at) on {{ $profile->created_at->format('d M Y') }}@endif.@endif
                </div>
            </td>
            <td style="width:45%; text-align:right; vertical-align:bottom;">
                <div class="sign-line" style="display:inline-block; min-width:180px; text-align:center;">
                    <strong>Authorised Signatory</strong><br>
                    <span class="muted" style="font-size:7.5pt;">{{ config('app.name', 'Hotel Pallav') }}</span>
                </div>
            </td>
        </tr>
    </table>
</div>

@endsection
