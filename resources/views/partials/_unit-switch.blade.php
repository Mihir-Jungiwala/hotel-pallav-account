{{-- Which business the screens show: one unit, or both side by side. --}}
@php
    use App\Support\UnitContext;
    $units = UnitContext::units();
    $currentKey = UnitContext::currentKey();
    $current = UnitContext::current();
@endphp

<div class="unit-switch" id="unitSwitch">
    <button type="button" class="unit-switch-btn" aria-expanded="false" aria-haspopup="true" title="Switch business">
        <span class="us-mark" @if($current) style="background:{{ $current->accent }};" @endif>
            <i class="bi {{ $current->icon ?? 'bi-buildings' }}"></i>
        </span>
        <span class="us-text">
            <span class="us-label">Viewing</span>
            <span class="us-name">{{ $current->name ?? 'Both businesses' }}</span>
        </span>
        <i class="bi bi-chevron-expand us-caret"></i>
    </button>

    <div class="unit-menu" role="menu">
        <div class="um-head">Business</div>

        <form method="POST" action="{{ route('unit.switch') }}" data-no-busy="true">
            @csrf
            @foreach($units as $unit)
                <button type="submit" name="unit" value="{{ $unit->slug }}"
                        class="um-item {{ $currentKey === $unit->slug ? 'active' : '' }}" role="menuitem">
                    <span class="um-mark" style="background:{{ $unit->accent }};"><i class="bi {{ $unit->icon }}"></i></span>
                    <span class="um-body">
                        <span class="um-name">{{ $unit->name }}</span>
                        <span class="um-hint">{{ $unit->code }} &middot; its own cash, bills and expenses</span>
                    </span>
                    @if($currentKey === $unit->slug)<i class="bi bi-check-lg"></i>@endif
                </button>
            @endforeach

            <button type="submit" name="unit" value="{{ UnitContext::BOTH }}"
                    class="um-item {{ $currentKey === UnitContext::BOTH ? 'active' : '' }}" role="menuitem">
                <span class="um-mark both"><i class="bi bi-layout-split"></i></span>
                <span class="um-body">
                    <span class="um-name">Both businesses</span>
                    <span class="um-hint">Everything together, split per business</span>
                </span>
                @if($currentKey === UnitContext::BOTH)<i class="bi bi-check-lg"></i>@endif
            </button>
        </form>
    </div>
</div>
