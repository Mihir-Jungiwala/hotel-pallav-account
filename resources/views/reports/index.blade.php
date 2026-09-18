@extends('layouts.app')
@section('title', 'Reports')

@section('content')

<div class="page-head reveal">
    <div>
        <div class="page-eyebrow">Accounts</div>
        <h2 class="pms-title">{{ $meta['name'] }}</h2>
        <p class="pms-sub">
            <span class="unit-tag"><i class="bi bi-eye"></i> {{ $unitLabel }}</span>
            <span class="ms-2">{{ $meta['hint'] }}</span>
        </p>
    </div>
    <a class="btn btn-p" target="_blank"
       href="{{ route('reports.download', ['type' => $type, 'from_date' => $from->toDateString(), 'to_date' => $to->toDateString()]) }}">
        <i class="bi bi-file-earmark-pdf"></i> Download PDF
    </a>
</div>

{{-- Pick a report --}}
<div class="report-picker reveal">
    @foreach($types as $key => $item)
        <a class="rp-card {{ $key === $type ? 'active' : '' }}"
           href="{{ route('reports.index', ['type' => $key, 'from_date' => $from->toDateString(), 'to_date' => $to->toDateString()]) }}">
            <span class="rp-icon"><i class="bi {{ $item['icon'] }}"></i></span>
            <span class="rp-name">{{ $item['name'] }}</span>
        </a>
    @endforeach
</div>

{{-- Period --}}
<form method="GET" action="{{ route('reports.index') }}" class="card period-bar reveal">
    <input type="hidden" name="type" value="{{ $type }}">
    <div class="pb-field">
        <label class="form-label">From</label>
        <input type="date" name="from_date" class="form-control" value="{{ $from->toDateString() }}">
    </div>
    <div class="pb-field">
        <label class="form-label">To</label>
        <input type="date" name="to_date" class="form-control" value="{{ $to->toDateString() }}">
    </div>
    <div class="pb-actions">
        <button class="btn btn-p"><i class="bi bi-arrow-repeat"></i> Update</button>
        <a class="btn btn-outline-p" href="{{ route('reports.index', ['type' => $type, 'from_date' => now()->startOfMonth()->toDateString(), 'to_date' => now()->toDateString()]) }}">This month</a>
        <a class="btn btn-outline-p" href="{{ route('reports.index', ['type' => $type, 'from_date' => now()->subMonth()->startOfMonth()->toDateString(), 'to_date' => now()->subMonth()->endOfMonth()->toDateString()]) }}">Last month</a>
        <a class="btn btn-outline-p" href="{{ route('reports.index', ['type' => $type, 'from_date' => now()->toDateString(), 'to_date' => now()->toDateString()]) }}">Today</a>
    </div>
</form>

@include('reports.partials.'.$type)

@endsection
