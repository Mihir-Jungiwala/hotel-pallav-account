@php $b = $bill ?? null; @endphp
<div class="row g-3">
    @if(! $b)
    <div class="col-md-6"><label class="form-label">Bill Number (suffix)</label>
        <div class="input-group"><span class="input-group-text">{{ date('Y') }} /</span><input name="bill_number" class="form-control" required></div>
    </div>
    <div class="col-md-6"><label class="form-label">Link Advance (optional)</label>
        <select name="advance_id" class="form-select">
            <option value="">None</option>
            @foreach($advances as $adv)
                <option value="{{ $adv->id }}">{{ $adv->receipt_number }} &mdash; {{ $adv->guest_name }}</option>
            @endforeach
        </select>
    </div>
    @endif
    <div class="col-md-6"><label class="form-label">Guest Name</label><input name="guest_name" class="form-control" value="{{ old('guest_name', $b->guest_name ?? '') }}" required></div>
    <div class="col-md-6"><label class="form-label">Mobile Number</label><input name="mobile_number" class="form-control" value="{{ old('mobile_number', $b->mobile_number ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label">Company</label>
        <select name="company_id" class="form-select">
            <option value="">None</option>
            @foreach($companies as $c)
                <option value="{{ $c->id }}" @selected(($b->company_id ?? null) === $c->id)>{{ $c->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6"><label class="form-label">Bill Date</label><input type="date" name="bill_date" class="form-control" value="{{ old('bill_date', optional($b->bill_date ?? null)->format('Y-m-d') ?: date('Y-m-d')) }}" required></div>

    <div class="col-12"><hr><div class="fw-bold" style="color:var(--p700);">Hotel</div></div>
    <div class="col-md-4"><label class="form-label">Plan</label><input name="hotel_plan" class="form-control" value="{{ old('hotel_plan', $b->hotel_plan ?? '') }}"></div>
    <div class="col-md-4"><label class="form-label">Room Amount</label><input type="number" step="0.01" name="hotel_amount" class="form-control" value="{{ old('hotel_amount', $b->hotel_amount ?? 0) }}"></div>
    <div class="col-md-4"><label class="form-label">Plan Amount</label><input type="number" step="0.01" name="hotel_plan_amount" class="form-control" value="{{ old('hotel_plan_amount', $b->hotel_plan_amount ?? 0) }}"></div>
    <div class="col-md-4"><label class="form-label">Laundry Amount</label><input type="number" step="0.01" name="hotel_laundry_amount" class="form-control" value="{{ old('hotel_laundry_amount', $b->hotel_laundry_amount ?? 0) }}"></div>
    <div class="col-md-4"><label class="form-label">GST</label><input type="number" step="0.01" name="hotel_gst" class="form-control" value="{{ old('hotel_gst', $b->hotel_gst ?? 0) }}"></div>
    <div class="col-md-4"><label class="form-label">Mode of Payment</label><input name="hotel_mode_of_payment" class="form-control" value="{{ old('hotel_mode_of_payment', $b->hotel_mode_of_payment ?? '') }}" placeholder="Cash / Debit"></div>

    <div class="col-12"><hr><div class="fw-bold" style="color:var(--p700);">Food</div></div>
    <div class="col-md-4"><label class="form-label">Food Amount</label><input type="number" step="0.01" name="food_amount" class="form-control" value="{{ old('food_amount', $b->food_amount ?? 0) }}"></div>
    <div class="col-md-4"><label class="form-label">Plan Amount</label><input type="number" step="0.01" name="food_plan_amount" class="form-control" value="{{ old('food_plan_amount', $b->food_plan_amount ?? 0) }}"></div>
    <div class="col-md-4"><label class="form-label">Laundry Amount</label><input type="number" step="0.01" name="food_laundry_amount" class="form-control" value="{{ old('food_laundry_amount', $b->food_laundry_amount ?? 0) }}"></div>
    <div class="col-md-4"><label class="form-label">GST</label><input type="number" step="0.01" name="food_gst" class="form-control" value="{{ old('food_gst', $b->food_gst ?? 0) }}"></div>
    <div class="col-md-4"><label class="form-label">Mode of Payment</label><input name="food_mode_of_payment" class="form-control" value="{{ old('food_mode_of_payment', $b->food_mode_of_payment ?? '') }}" placeholder="Cash / Debit"></div>

    <div class="col-12"><hr><div class="fw-bold" style="color:var(--p700);">Reference &amp; Attachments</div></div>
    <div class="col-md-6"><label class="form-label">Reference Name</label><input name="reference_name" class="form-control" value="{{ old('reference_name', $b->reference_name ?? '') }}"></div>
    <div class="col-md-6"><label class="form-label">Reference Mobile</label><input name="reference_mobile_number" class="form-control" value="{{ old('reference_mobile_number', $b->reference_mobile_number ?? '') }}"></div>
    <div class="col-12"><label class="form-label">Instruction</label><textarea name="instruction" class="form-control">{{ old('instruction', $b->instruction ?? '') }}</textarea></div>
    <div class="col-12"><label class="form-label">Invoice PDF</label><input type="file" name="invoice_pdf" class="form-control"></div>
</div>
