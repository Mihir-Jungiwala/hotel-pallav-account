@php $r = $record ?? null; @endphp
<div class="row g-3">
    <div class="col-md-4"><label class="form-label">Date</label><input type="date" name="date" class="form-control" value="{{ old('date', optional($r->date ?? null)->format('Y-m-d') ?: date('Y-m-d')) }}" required></div>
    <div class="col-md-4"><label class="form-label">Time</label><input type="time" name="time" class="form-control" value="{{ old('time', $r->time ?? date('H:i')) }}" required></div>
    <div class="col-md-4"><label class="form-label">Shift</label>
        <select name="shift" class="form-select" required>
            @foreach(['Morning','Evening','Night'] as $sh)
                <option value="{{ $sh }}" @selected(old('shift', $r->shift ?? '') === $sh)>{{ $sh }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-12"><hr><div class="fw-bold" style="color:var(--p700);">Cash Denominations</div></div>
    @foreach(\App\Models\ShiftHandover::DENOMINATIONS as $denom => $value)
        <div class="col-md-3">
            <label class="form-label">{{ $denom === 'coins' ? 'Coins' : '₹'.$value.' Notes' }} &mdash; Count</label>
            <input type="number" min="0" class="form-control denom-count" data-value="{{ $value }}" name="{{ $denom }}_count" value="{{ old("{$denom}_count", $r->{"{$denom}_count"} ?? 0) }}">
        </div>
    @endforeach
    <div class="col-12 text-end fw-bold" style="color:var(--p800); font-size:18px;">Grand Total: ₹<span id="grandTotal">{{ number_format($r->total ?? 0, 2) }}</span></div>

    <div class="col-12"><hr><div class="fw-bold" style="color:var(--p700);">Handover Notes</div></div>
    @foreach(['message_one'=>'Note 1','message_two'=>'Note 2','message_three'=>'Note 3','message_four'=>'Note 4','message_five'=>'Note 5'] as $field => $label)
        <div class="col-12"><label class="form-label">{{ $label }}</label><textarea name="{{ $field }}" class="form-control" rows="2">{{ old($field, $r->$field ?? '') }}</textarea></div>
    @endforeach
    <div class="col-12"><label class="form-label">Special Instruction</label><textarea name="special_instruction" class="form-control" rows="2">{{ old('special_instruction', $r->special_instruction ?? '') }}</textarea></div>
</div>
