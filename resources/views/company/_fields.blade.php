@php $c = $company ?? null; @endphp
<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Company Name *</label><input name="name" class="form-control" value="{{ old('name', $c->name ?? '') }}" required></div>
    <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email', $c->email ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label">Mobile Number</label><input name="mobile_number" class="form-control" value="{{ old('mobile_number', $c->mobile_number ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label">Phone Number</label><input name="phone_number" class="form-control" value="{{ old('phone_number', $c->phone_number ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label">Country</label><input name="country" class="form-control" value="{{ old('country', $c->country ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label">Nationality</label><input name="nationality" class="form-control" value="{{ old('nationality', $c->nationality ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label">Pincode</label><input name="pincode" class="form-control" value="{{ old('pincode', $c->pincode ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label">GST Number</label><input name="gst_number" class="form-control" value="{{ old('gst_number', $c->gst_number ?? '') }}"></div>
    <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2">{{ old('address', $c->address ?? '') }}</textarea></div>

    <div class="col-md-3"><label class="form-label">Discount %</label><input type="number" step="0.01" name="discount_percentage" class="form-control" value="{{ old('discount_percentage', $c->discount_percentage ?? '') }}"></div>
    <div class="col-md-3"><label class="form-label">GST %</label><input type="number" step="0.01" name="gst_percentage" class="form-control" value="{{ old('gst_percentage', $c->gst_percentage ?? '') }}"></div>
    <div class="col-md-3"><label class="form-label">TCS %</label><input type="number" step="0.01" name="tcs_percentage" class="form-control" value="{{ old('tcs_percentage', $c->tcs_percentage ?? '') }}"></div>
    <div class="col-md-3"><label class="form-label">TDS %</label><input type="number" step="0.01" name="tds_percentage" class="form-control" value="{{ old('tds_percentage', $c->tds_percentage ?? '') }}"></div>

    <div class="col-12"><label class="form-label">Instruction</label><textarea name="instruction" class="form-control" rows="2">{{ old('instruction', $c->instruction ?? '') }}</textarea></div>

    <div class="col-12"><hr><div class="fw-bold" style="color:var(--p700);">Contact Persons</div></div>
    @foreach($contacts as $contact)
        @php
            $label = match($contact) {
                'md_one' => 'Managing Director 1', 'md_second' => 'Managing Director 2',
                'hr_head' => 'HR Head', 'assistant_hr' => 'Assistant HR',
                'accountant_head' => 'Accountant Head', 'accountant_assistant_one' => 'Accountant Assistant 1',
                'accountant_assistant_two' => 'Accountant Assistant 2',
            };
        @endphp
        <div class="col-12"><div class="small text-muted fw-semibold mt-2">{{ $label }}</div></div>
        <div class="col-md-4"><input name="{{ $contact }}_name" class="form-control" placeholder="Name" value="{{ old("{$contact}_name", $c->{"{$contact}_name"} ?? '') }}"></div>
        <div class="col-md-4"><input type="email" name="{{ $contact }}_email" class="form-control" placeholder="Email" value="{{ old("{$contact}_email", $c->{"{$contact}_email"} ?? '') }}"></div>
        <div class="col-md-4"><input name="{{ $contact }}_mobile" class="form-control" placeholder="Mobile" value="{{ old("{$contact}_mobile", $c->{"{$contact}_mobile"} ?? '') }}"></div>
    @endforeach
</div>
