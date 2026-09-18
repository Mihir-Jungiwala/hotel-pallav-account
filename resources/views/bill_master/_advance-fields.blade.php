@php $a = $advance ?? null; @endphp

<div data-wizard>
    {{-- Step 1: who paid the advance --}}
    <div class="form-step" data-step="Guest">
        <div class="row g-3">
            @if(! $a)
                <div class="col-md-6">
                    <label class="form-label">Receipt number *</label>
                    <div class="input-group"><span class="input-group-text">{{ date('Y') }} /</span><input name="receipt_number" class="form-control" required></div>
                </div>
            @endif
            <div class="col-md-6">
                <label class="form-label">Guest name *</label>
                <input name="guest_name" class="form-control" value="{{ old('guest_name', $a->guest_name ?? '') }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Mobile number <span class="wz-optional">optional</span></label>
                <input name="mobile_number" class="form-control" value="{{ old('mobile_number', $a->mobile_number ?? '') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Company <span class="wz-optional">optional</span></label>
                <select name="company_id" class="form-select">
                    <option value="">None</option>
                    @foreach($companies as $c)
                        <option value="{{ $c->id }}" @selected(($a->company_id ?? null) === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Payment date *</label>
                <input type="date" name="payment_date" class="form-control"
                       value="{{ old('payment_date', optional($a->payment_date ?? null)->format('Y-m-d') ?: date('Y-m-d')) }}" required>
            </div>
        </div>
    </div>

    {{-- Step 2: how much, on each side of the business --}}
    <div class="form-step" data-step="Amounts">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Hotel advance <span class="wz-optional">optional</span></label>
                <input type="number" step="0.01" name="hotel_amount" class="form-control" value="{{ old('hotel_amount', $a->hotel_amount ?? '') }}">
            </div>
            <div class="col-md-6">
                @include('partials._option-field', ['key' => 'payment_mode', 'name' => 'hotel_mode', 'value' => $a->hotel_mode ?? null, 'label' => 'Hotel mode of payment'])
            </div>
            <div class="col-md-6">
                <label class="form-label">Food advance <span class="wz-optional">optional</span></label>
                <input type="number" step="0.01" name="food_amount" class="form-control" value="{{ old('food_amount', $a->food_amount ?? '') }}">
            </div>
            <div class="col-md-6">
                @include('partials._option-field', ['key' => 'payment_mode', 'name' => 'food_mode', 'value' => $a->food_mode ?? null, 'label' => 'Food mode of payment'])
            </div>
        </div>
    </div>

    {{-- Step 3: who to call about it --}}
    <div class="form-step" data-step="Reference">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Reference name <span class="wz-optional">optional</span></label>
                <input name="reference_name" class="form-control" value="{{ old('reference_name', $a->reference_name ?? '') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Reference mobile <span class="wz-optional">optional</span></label>
                <input name="reference_mobile_number" class="form-control" value="{{ old('reference_mobile_number', $a->reference_mobile_number ?? '') }}">
            </div>
            <div class="col-12">
                <label class="form-label">Instruction <span class="wz-optional">optional</span></label>
                <textarea name="instruction" class="form-control" rows="2">{{ old('instruction', $a->instruction ?? '') }}</textarea>
            </div>
        </div>
    </div>
</div>
