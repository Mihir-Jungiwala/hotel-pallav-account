{{-- The countries the phone field offers, once per page. The field reads this
     instead of carrying its own copy, so the browser and the server cannot
     disagree about what a valid number is. --}}
<script type="application/json" id="pmsPhoneCountries">{!! json_encode(\App\Support\PhoneCountries::all(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
