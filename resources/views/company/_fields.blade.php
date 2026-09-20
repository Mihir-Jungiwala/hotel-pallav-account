{{-- The company form, in four steps: who they are, where they are, how they are
     billed, and who to contact. It is filled from the JSON on the page
     (company.js), so it holds no values of its own. Pass $roles (contact roles). --}}
<div data-wizard>
    {{-- 1. Company --}}
    <div class="form-step" data-step="Company">
        <div class="form-section">
            <div class="fs-head"><div class="fs-title"><i class="bi bi-building"></i> About the company</div></div>
            <div class="row g-3">
                <div class="col-md-7">
                    <label class="form-label" for="co_name">Company name<span class="req">*</span></label>
                    <input name="name" id="co_name" class="form-control" maxlength="100" required placeholder="Registered or trading name">
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="co_gst">GST number</label>
                    <input name="gst_number" id="co_gst" class="form-control text-uppercase" maxlength="50" placeholder="24ABCDE1234F1Z5">
                </div>
            </div>
        </div>

        <div class="form-section">
            <div class="fs-head"><div class="fs-title"><i class="bi bi-telephone"></i> How to reach them</div><div class="fs-hint">The company's own number and email, not a person's.</div></div>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="co_email">Email</label>
                    <input type="email" name="email" id="co_email" class="form-control" maxlength="254" placeholder="accounts@company.com">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="co_mobile">Mobile</label>
                    <input name="mobile_number" id="co_mobile" class="form-control" maxlength="15" inputmode="tel">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="co_phone">Landline</label>
                    <input name="phone_number" id="co_phone" class="form-control" maxlength="15" inputmode="tel">
                </div>
            </div>
        </div>
    </div>

    {{-- 2. Address --}}
    <div class="form-step" data-step="Address">
        <div class="form-section">
            <div class="fs-head"><div class="fs-title"><i class="bi bi-geo-alt"></i> Where they are</div></div>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="co_address">Address</label>
                    <textarea name="address" id="co_address" class="form-control" rows="2" maxlength="100" placeholder="Street, area, city"></textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="co_pincode">Pincode</label>
                    <input name="pincode" id="co_pincode" class="form-control" maxlength="20" inputmode="numeric">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="co_country">Country</label>
                    <input name="country" id="co_country" class="form-control" maxlength="100">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="co_nationality">Nationality</label>
                    <input name="nationality" id="co_nationality" class="form-control" maxlength="100">
                </div>
            </div>
        </div>
    </div>

    {{-- 3. Billing --}}
    <div class="form-step" data-step="Billing">
        <div class="form-section">
            <div class="fs-head"><div class="fs-title"><i class="bi bi-percent"></i> Rates on their bills</div><div class="fs-hint">Leave a rate empty if it does not apply.</div></div>
            <div class="co-rates">
                @foreach(['discount_percentage' => ['Discount', 'bi-tag'], 'gst_percentage' => ['GST', 'bi-receipt'], 'tcs_percentage' => ['TCS', 'bi-cash-coin'], 'tds_percentage' => ['TDS', 'bi-scissors']] as $field => [$label, $icon])
                    <label class="co-rate" for="co_{{ $field }}">
                        <span class="co-rate-label"><i class="bi {{ $icon }}"></i> {{ $label }}</span>
                        <span class="co-rate-input">
                            <input type="number" step="0.01" min="0" max="100" name="{{ $field }}" id="co_{{ $field }}" class="form-control" inputmode="decimal" placeholder="0">
                            <span class="co-rate-unit">%</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="form-section">
            <div class="fs-head"><div class="fs-title"><i class="bi bi-chat-left-text"></i> Instruction</div><div class="fs-hint">Anything the front desk should know when billing this company.</div></div>
            <label class="form-label visually-hidden" for="co_instruction">Instruction</label>
            <textarea name="instruction" id="co_instruction" class="form-control" rows="3" placeholder="Optional"></textarea>
        </div>
    </div>

    {{-- 4. People: one card each, as many as needed, and a role may repeat --}}
    <div class="form-step" data-step="People">
        <div class="form-section">
            <div class="fs-head">
                <div class="fs-title"><i class="bi bi-people"></i> People to contact<span class="req">*</span> <span class="co-count" data-people-count>0</span></div>
                <div class="fs-hint">The first person is required. Add as many more as you need, and the same role may be used again.</div>
            </div>

            <div class="co-people" data-people-list></div>

            <button type="button" class="co-add-more" data-add-person>
                <i class="bi bi-plus-circle"></i> Add another person
            </button>

            <template data-person-template>
                <div class="co-person">
                    <div class="co-person-head">
                        <span class="co-person-no" data-person-no></span>
                        <span class="co-person-tag" data-person-tag></span>
                        <button type="button" class="co-person-remove" data-remove-person aria-label="Remove this person"><i class="bi bi-trash"></i> Remove</button>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Full name<span class="req">*</span></label>
                            <input class="form-control" data-field="name" maxlength="100" placeholder="e.g. Meera Joshi" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Their role</label>
                            <select class="form-select" data-field="role" data-native>
                                @foreach($roles as $role)<option value="{{ $role }}">{{ $role }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" data-field="email" maxlength="254" placeholder="name@company.com">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Mobile</label>
                            <input class="form-control" data-field="mobile" maxlength="15" inputmode="tel" placeholder="98765 43210">
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
