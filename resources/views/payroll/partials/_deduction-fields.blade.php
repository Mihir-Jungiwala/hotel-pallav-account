@php $d = $target ?? null; $uid = $d->id ?? 'new'; @endphp

<div class="form-section">
    <div class="fs-head">
        <div class="fs-title"><i class="bi bi-dash-circle"></i> Deduction</div>
        <div class="fs-hint">The amount here is the standard one. When you assign this deduction to an employee you can override it for that person.</div>
    </div>
    <div class="row g-3">
        <div class="col-md-7">
            <label class="form-label" for="ded_name_{{ $uid }}">Deduction name<span class="req">*</span></label>
            <input name="name" id="ded_name_{{ $uid }}" class="form-control"
                   value="{{ old('name', $d->name ?? '') }}"
                   placeholder="Food, Uniform, Accommodation&hellip;" required>
        </div>
        <div class="col-md-5">
            <label class="form-label" for="ded_amt_{{ $uid }}">Standard amount<span class="req">*</span></label>
            <input type="number" step="0.01" min="0" name="amount" id="ded_amt_{{ $uid }}" class="form-control"
                   value="{{ old('amount', $d->amount ?? '') }}" required>
        </div>
    </div>
</div>
