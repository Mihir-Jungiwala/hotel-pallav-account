@php
    $brandName = config('app.name', 'Hotel Pallav');
    $brandMeta = 'Hotel Management Suite';
    $docType = $title;
    $docSub = $reference ?? null;

    // Pull the money line out so it can be shown as the hero figure
    $amountKey = collect(array_keys($rows))->first(fn ($k) => str_contains(strtolower($k), 'amount') && ! str_contains(strtolower($k), 'words'));
    $wordsKey = collect(array_keys($rows))->first(fn ($k) => str_contains(strtolower($k), 'words'));
    $detailRows = collect($rows)->except(array_filter([$amountKey, $wordsKey]));
@endphp

@extends('payroll.pdf._base')

@section('content')

@if($amountKey)
<div class="panel avoid-break" style="margin-top:0;">
    <div class="label">{{ $amountKey }}</div>
    <div class="value">{{ $rows[$amountKey] }}</div>
    @if($wordsKey && $rows[$wordsKey])
        <div class="words">{{ $rows[$wordsKey] }}</div>
    @endif
</div>
@endif

<h2 class="section">Details</h2>
<table class="fields avoid-break">
    @foreach($detailRows->chunk(2) as $pair)
        <tr>
            @foreach($pair as $label => $value)
                <td class="k">{{ $label }}</td>
                <td class="v">{{ $value !== null && $value !== '' ? $value : '-' }}</td>
            @endforeach
            @if($pair->count() === 1)
                <td class="k"></td><td class="v"></td>
            @endif
        </tr>
    @endforeach
</table>

<div class="sign-area">
    <table>
        <tr>
            <td style="width:50%; vertical-align:bottom;">
                <div class="sign-line" style="min-width:180px;">
                    <strong>Received by</strong>
                </div>
            </td>
            <td style="width:50%; text-align:right; vertical-align:bottom;">
                <div class="sign-line" style="display:inline-block; min-width:180px; text-align:center;">
                    <strong>Authorised Signatory</strong><br>
                    <span class="muted" style="font-size:7.5pt;">{{ $brandName }}</span>
                </div>
            </td>
        </tr>
    </table>
</div>

@endsection
