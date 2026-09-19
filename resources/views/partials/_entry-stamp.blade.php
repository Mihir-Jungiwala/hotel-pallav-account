{{-- When an entry was made, as one "Date and time" field like Payroll's.
     Admins and the SuperAdmin can set it (never in the future); for everyone
     else it is shown, not asked, and the server stamps the moment of saving
     (LocksEntryStamp). The form still sends `date` and `time` separately.
     Pass $date (Y-m-d), $time (H:i) and optionally $col. --}}
@php
    $stampDate = old('date', $date ?? now()->format('Y-m-d'));
    $stampTime = substr((string) old('time', $time ?? now()->format('H:i')), 0, 5);
    $stampCol = $col ?? 'col-md-6';
    $stampId = 'stamp_'.Str::random(5);
@endphp

@once
    @push('scripts')
        <script src="{{ asset('assets/pms-stamp.js') }}?v=2"></script>
    @endpush
@endonce

<div class="{{ $stampCol }}" data-entry-stamp-field>
    @if(\App\Support\EntryStamp::editable())
        <label class="form-label" for="{{ $stampId }}">Date and time<span class="req">*</span></label>
        <input type="datetime-local" id="{{ $stampId }}" class="form-control" data-stamp-when
               value="{{ $stampDate }}T{{ $stampTime }}" max="{{ now()->format('Y-m-d\TH:i') }}" required>
        <div class="form-text">Cannot be in the future.</div>
    @else
        <span class="form-label d-block">Date and time</span>
        <div class="field-readonly">
            <i class="bi bi-lock"></i>
            <span data-entry-stamp-text>{{ \Carbon\Carbon::parse($stampDate.' '.$stampTime)->format('d M Y, H:i') }}</span>
        </div>
        <div class="form-text">{{ isset($date) ? 'Only an Admin can change when this was entered.' : 'Stamped automatically when you save.' }}</div>
    @endif
    <input type="hidden" name="date" value="{{ $stampDate }}" data-stamp-date>
    <input type="hidden" name="time" value="{{ $stampTime }}" data-stamp-time>
</div>
