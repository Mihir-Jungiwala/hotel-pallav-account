@php $uid = $item?->id ?? 'new'; @endphp
<div class="row g-3">
    <div class="col-12">
        <label class="form-label" for="mi_label_{{ $uid }}">Name<span class="req">*</span></label>
        <input name="label" id="mi_label_{{ $uid }}" class="form-control" maxlength="120" required
               value="{{ old('label', $item->label ?? '') }}"
               placeholder="{{ $isEmail ? 'e.g. Accounts team' : 'e.g. Front Office' }}">
        @error('label')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>
    @if($isEmail)
        <div class="col-12">
            <label class="form-label" for="mi_value_{{ $uid }}">{{ $valueLabel }}<span class="req">*</span></label>
            <input type="email" name="value" id="mi_value_{{ $uid }}" class="form-control" maxlength="190" required
                   value="{{ old('value', $item->value ?? '') }}" placeholder="name@example.com">
            @error('value')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
    @endif
</div>
