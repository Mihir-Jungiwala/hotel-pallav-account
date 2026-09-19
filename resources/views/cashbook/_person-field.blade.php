{{-- Who handed cash in or is taking it out. The names are the "Cash Handlers"
     list in Master Data; "Other" lets someone type a new one, which is added
     to that list when the entry is saved. cashbook.js keeps the box in step.
     Pass $name (the field, e.g. depositor) and $label. --}}
@php
    use App\Support\CashPeople;
    use App\Support\Masters;

    $people = Masters::items(CashPeople::KEY);
    $personId = 'person_'.$name.'_'.Str::random(4);
@endphp

<div class="cb-person" data-person>
    <label class="form-label" for="{{ $personId }}">{{ $label }}<span class="req">*</span></label>
    <select name="{{ $name }}" id="{{ $personId }}" class="form-select" data-person-select required>
        <option value="">Choose a name&hellip;</option>
        @foreach($people as $person)
            <option value="{{ $person->value }}">{{ $person->label }}</option>
        @endforeach
        <option value="__other__">Other (add a new name)</option>
    </select>
    <input type="text" name="{{ $name }}_new" class="form-control mt-2" maxlength="100" placeholder="Type the full name"
           aria-label="New name" data-person-new hidden disabled>
    <div class="form-text">Chosen from Master Data. A new name is added there for next time.</div>
</div>
