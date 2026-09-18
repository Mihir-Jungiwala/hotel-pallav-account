@php $r = $record ?? null; @endphp

<div data-wizard>
    {{-- Step 1: who is handing over, and when --}}
    <div class="form-step" data-step="Shift">
        <div class="row g-3">
            @include('partials._unit-field', ['selected' => optional($r)->businessUnit->slug ?? 'hotel'])
            <div class="col-md-4">
                <label class="form-label">Date *</label>
                <input type="date" name="date" class="form-control"
                       value="{{ old('date', optional($r->date ?? null)->format('Y-m-d') ?: date('Y-m-d')) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Time *</label>
                <input type="time" name="time" class="form-control" value="{{ old('time', $r->time ?? date('H:i')) }}" required>
            </div>
            <div class="col-md-4">
                @include('partials._option-field', ['key' => 'shift', 'name' => 'shift', 'value' => $r->shift ?? null, 'label' => 'Shift', 'required' => true])
            </div>
        </div>
    </div>

    {{-- Step 2: count the till --}}
    <div class="form-step" data-step="Cash count">
        <div class="row g-3">
            @foreach(\App\Models\ShiftHandover::DENOMINATIONS as $denom => $value)
                <div class="col-md-3 col-6">
                    <label class="form-label">{{ $denom === 'coins' ? 'Coins' : 'Rs '.$value.' notes' }}</label>
                    <input type="number" min="0" class="form-control denom-count" data-value="{{ $value }}"
                           name="{{ $denom }}_count" value="{{ old("{$denom}_count", $r->{"{$denom}_count"} ?? 0) }}">
                </div>
            @endforeach
            <div class="col-12">
                <div class="count-total">
                    <span>Counted total</span>
                    <strong>Rs <span id="grandTotal">{{ number_format($r->total ?? 0, 2) }}</span></strong>
                </div>
            </div>
        </div>
    </div>

    {{-- Step 3: anything the next shift needs to know --}}
    <div class="form-step" data-step="Notes">
        <div class="row g-3">
            @foreach(['message_one' => 'Note 1', 'message_two' => 'Note 2', 'message_three' => 'Note 3', 'message_four' => 'Note 4', 'message_five' => 'Note 5'] as $field => $label)
                <div class="col-md-6">
                    <label class="form-label">{{ $label }} <span class="wz-optional">optional</span></label>
                    <textarea name="{{ $field }}" class="form-control" rows="2">{{ old($field, $r->$field ?? '') }}</textarea>
                </div>
            @endforeach
            <div class="col-12">
                <label class="form-label">Special instruction <span class="wz-optional">optional</span></label>
                <textarea name="special_instruction" class="form-control" rows="2">{{ old('special_instruction', $r->special_instruction ?? '') }}</textarea>
            </div>
        </div>
    </div>
</div>
