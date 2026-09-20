@php
    $i = $item ?? null;
    $nameOnly = $nameOnly ?? false;
@endphp

@if($nameOnly)
    {{-- A list of people: the name is all there is to fill in --}}
    <label class="form-label" for="item_name_{{ $i->id ?? 'new' }}">Name<span class="req">*</span></label>
    <input name="label" id="item_name_{{ $i->id ?? 'new' }}" class="form-control" value="{{ old('label', $i->label ?? '') }}" maxlength="60" required autofocus autocomplete="off">
@else
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Option *</label>
        <input name="label" class="form-control" value="{{ old('label', $i->label ?? '') }}" maxlength="60" required autofocus>
        <div class="form-text">What people see in the dropdown.</div>
    </div>

    <div class="col-md-6">
        <label class="form-label">Saved value <span class="wz-optional">optional</span></label>
        <input name="value" class="form-control" value="{{ old('value', $i->value ?? '') }}" maxlength="60"
               placeholder="Same as the option">
        <div class="form-text">Leave blank unless older records store a different spelling.</div>
    </div>

    <div class="col-12">
        <label class="check-line">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $i->is_active ?? true))>
            <span>Offer this on forms</span>
        </label>
        <label class="check-line">
            <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $i->is_default ?? false))>
            <span>Pre-select it on new entries</span>
        </label>
    </div>
</div>
@endif
