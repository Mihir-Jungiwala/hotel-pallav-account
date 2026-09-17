@php $a = $advance ?? null; @endphp
<div class="row g-3">
    @if(! $a)
    <div class="col-md-6"><label class="form-label">Receipt Number (suffix)</label>
        <div class="input-group"><span class="input-group-text">{{ date('Y') }} /</span><input name="receipt_number" class="form-control" required></div>
    </div>
    @endif
    <div class="col-md-6"><label class="form-label">Guest Name</label><input name="guest_name" class="form-control" value="{{ old('guest_name', $a->guest_name ?? '') }}" required></div>
    <div class="col-md-6"><label class="form-label">Mobile Number</label><input name="mobile_number" class="form-control" value="{{ old('mobile_number', $a->mobile_number ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label">Company</label>
        <select name="company_id" class="form-select">
            <option value="">None</option>
            @foreach($companies as $c)
                <option value="{{ $c->id }}" @selected(($a->company_id ?? null) === $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6"><label class="form-label">Payment Date</label><input type="date" name="payment_date" class="form-control" value="{{ old('payment_date', optional($a->payment_date ?? null)->format('Y-m-d') ?: date('Y-m-d')) }}" required></div>
    <div class="col-md-6"><label class="form-label">Hotel Advance Amount</label><input type="number" step="0.01" name="hotel_amount" class="form-control" value="{{ old('hotel_amount', $a->hotel_amount ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label">Hotel Mode of Payment</label><input name="hotel_mode" class="form-control" value="{{ old('hotel_mode', $a->hotel_mode ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label">Food Advance Amount</label><input type="number" step="0.01" name="food_amount" class="form-control" value="{{ old('food_amount', $a->food_amount ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label">Food Mode of Payment</label><input name="food_mode" class="form-control" value="{{ old('food_mode', $a->food_mode ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label">Reference Name</label><input name="reference_name" class="form-control" value="{{ old('reference_name', $a->reference_name ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label">Reference Mobile</label><input name="reference_mobile_number" class="form-control" value="{{ old('reference_mobile_number', $a->reference_mobile_number ?? '') }}"></div>
    <div class="col-12"><label class="form-label">Instruction</label><textarea name="instruction" class="form-control">{{ old('instruction', $a->instruction ?? '') }}</textarea></div>
</div>
