{{-- A field backed by Master Data. Pass the list key, the input name and the
     current value; the SuperAdmin decides what the choices are. --}}
@php
    use App\Support\Masters;

    $set = Masters::set($key);
    $items = Masters::items($key);
    $selected = old($name, $value ?? null) ?? Masters::default($key);
    $input = $set->input ?? 'select';
    $id = $name.'_'.Str::random(4);
@endphp

<label class="form-label">{{ $label ?? ($set->name ?? Str::headline($key)) }} @if($required ?? false)*@endif</label>

@if($items->isEmpty())
    <input name="{{ $name }}" class="form-control" value="{{ $selected }}" @required($required ?? false)>
    <div class="form-text">No options set up yet. Master Data holds this list.</div>
@elseif($input === 'radio')
    <div class="option-chips">
        @foreach($items as $item)
            <label class="option-chip">
                <input type="radio" name="{{ $name }}" value="{{ $item->value }}"
                       @checked($selected === $item->value) @required($required ?? false)>
                <span>{{ $item->label }}</span>
            </label>
        @endforeach
    </div>
@elseif($input === 'checkbox')
    <div class="option-chips">
        @foreach($items as $item)
            <label class="option-chip">
                <input type="checkbox" name="{{ $name }}[]" value="{{ $item->value }}"
                       @checked(is_array($selected) && in_array($item->value, $selected, true))>
                <span>{{ $item->label }}</span>
            </label>
        @endforeach
    </div>
@else
    <select name="{{ $name }}" id="{{ $id }}" class="form-select" @required($required ?? false)>
        @unless($required ?? false)<option value="">None</option>@endunless
        @foreach($items as $item)
            <option value="{{ $item->value }}" @selected($selected === $item->value)>{{ $item->label }}</option>
        @endforeach
    </select>
@endif

@isset($hint)<div class="form-text">{{ $hint }}</div>@endisset
