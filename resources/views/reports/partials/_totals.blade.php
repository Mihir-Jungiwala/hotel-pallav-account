{{-- The headline figures above a report table. Pass [label => value] pairs. --}}
<div class="report-totals reveal">
    @foreach($cards as $label => $value)
        <div class="rt-card">
            <span class="rt-label">{{ $label }}</span>
            <span class="rt-value {{ $loop->first ? 'accent' : '' }}">{{ $value }}</span>
        </div>
    @endforeach
</div>
