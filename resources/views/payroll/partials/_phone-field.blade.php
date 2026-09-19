{{-- A mobile number: a country-code chip that opens a searchable list, and the
     number beside it. India is the default.

     The number and the country are two separate fields, so a stored number
     keeps meaning what it always meant. The chip is a button rather than a
     native <select>, so its list carries the theme and can be searched.

     Needs the country list on the page - include payroll.partials._phone-data
     once. Parameters:
       $name         field name for the number       (contact_number)
       $countryName  field name for the country code (contact_country)
       $id           id for the number input, so a <label for> can point at it
       $value        the stored national number
       $country      the stored dial code, without "+"   (default 91)
       $required     whether it must be filled in --}}
@php
    $dial = (string) old($countryName, $country ?? \App\Support\PhoneCountries::DEFAULT);
    $c = \App\Support\PhoneCountries::find($dial) ?? \App\Support\PhoneCountries::find(\App\Support\PhoneCountries::DEFAULT);
@endphp

<div class="phone-field" data-phone>
    <button type="button" class="phone-country" aria-haspopup="listbox" aria-expanded="false"
            aria-label="Country code, {{ $c['name'] }} plus {{ $c['dial'] }}. Change">
        <span class="pc-iso">{{ $c['iso'] }}</span>
        <span class="pc-dial">+{{ $c['dial'] }}</span>
        <i class="bi bi-chevron-down" aria-hidden="true"></i>
    </button>

    <input type="hidden" name="{{ $countryName }}" value="{{ $c['dial'] }}" data-phone-country>

    <input type="tel" name="{{ $name }}" id="{{ $id }}" class="form-control"
           inputmode="tel" autocomplete="off" placeholder="{{ $c['sample'] }}"
           pattern="{{ \App\Support\PhoneCountries::pattern($c['dial']) }}"
           data-pattern-message="{{ \App\Support\PhoneCountries::message($c['dial']) }}"
           data-phone-number
           value="{{ old($name, $value ?? '') }}" @required($required ?? false)>
</div>
