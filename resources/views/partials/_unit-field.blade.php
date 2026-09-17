{{-- Which business a new record belongs to. Only asked while both are in view;
     otherwise the sidebar choice decides it. --}}
@php use App\Support\UnitContext; @endphp

@if(UnitContext::isBoth())
    <div class="col-md-6">
        <label class="form-label">Business *</label>
        <select name="business_unit" class="form-select" required>
            @foreach(UnitContext::units() as $unit)
                <option value="{{ $unit->slug }}" @selected(($selected ?? 'hotel') === $unit->slug)>{{ $unit->name }}</option>
            @endforeach
        </select>
        <div class="form-text">Which side of the business this belongs to.</div>
    </div>
@endif
