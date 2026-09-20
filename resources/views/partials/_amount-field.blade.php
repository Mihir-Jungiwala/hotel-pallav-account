{{-- A rupee amount, written out in words as it is typed, the same words the
     receipt will carry. Pass $value to prefill, $label to rename it.
     pms-money.js keeps the words in step; the look is in cashbook.css. --}}
@php $amountId = 'amt_'.Str::random(5); @endphp

@once
    @push('scripts')
        <script src="{{ asset('assets/pms-money.js') }}?v=2"></script>
    @endpush
@endonce

<div class="amount-field amt-tile" data-amount-field>
    <label class="form-label" for="{{ $amountId }}">{{ $label ?? 'Amount' }}<span class="req">*</span></label>
    <div class="amt-box">
        <span class="amt-symbol" aria-hidden="true">₹</span>
        <input type="number" step="0.01" min="0" name="amount" id="{{ $amountId }}" class="amt-input" inputmode="decimal"
               value="{{ old('amount', $value ?? null) }}" placeholder="0.00" required autocomplete="off">
    </div>
    <div class="amt-words">
        <i class="bi bi-chat-quote" aria-hidden="true"></i>
        <span data-amount-words>Zero Rupees Only</span>
    </div>
</div>
