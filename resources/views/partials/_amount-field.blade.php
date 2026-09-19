{{-- A rupee amount, written out in words as it is typed, the same words the
     receipt will carry. Pass $value to prefill, $label to rename it. --}}
@php $amountId = 'amt_'.Str::random(5); @endphp

@once
    @push('scripts')
        <script src="{{ asset('assets/pms-money.js') }}?v=1"></script>
    @endpush
@endonce

<div class="amount-field" data-amount-field>
    <label class="form-label" for="{{ $amountId }}">{{ $label ?? 'Amount' }}<span class="req">*</span></label>
    <div class="input-group">
        <span class="input-group-text">₹</span>
        <input type="number" step="0.01" min="0" name="amount" id="{{ $amountId }}" class="form-control" inputmode="decimal"
               value="{{ old('amount', $value ?? null) }}" placeholder="0.00" required>
    </div>
    <div class="form-text amount-words" data-amount-words>Zero Rupees Only</div>
</div>
