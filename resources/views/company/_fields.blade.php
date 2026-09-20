{{-- The company form, in three steps. It is filled from the JSON on the page
     (company.js), so it holds no values of its own. Pass $contacts (key => label). --}}
<div data-wizard>
    <div class="form-step" data-step="Company">
        <div class="form-section">
            <div class="fs-head"><div class="fs-title"><i class="bi bi-building"></i> The company</div></div>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="co_name">Company name<span class="req">*</span></label>
                    <input name="name" id="co_name" class="form-control" maxlength="100" required placeholder="Registered or trading name">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="co_gst">GST number</label>
                    <input name="gst_number" id="co_gst" class="form-control" maxlength="50" placeholder="e.g. 24ABCDE1234F1Z5">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="co_nationality">Nationality</label>
                    <input name="nationality" id="co_nationality" class="form-control" maxlength="100">
                </div>
            </div>
        </div>

        <div class="form-section">
            <div class="fs-head"><div class="fs-title"><i class="bi bi-telephone"></i> How to reach them</div></div>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="co_email">Email</label>
                    <input type="email" name="email" id="co_email" class="form-control" maxlength="254" placeholder="accounts@company.com">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="co_mobile">Mobile</label>
                    <input name="mobile_number" id="co_mobile" class="form-control" maxlength="15" inputmode="tel">
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="co_phone">Phone</label>
                    <input name="phone_number" id="co_phone" class="form-control" maxlength="15" inputmode="tel">
                </div>
                <div class="col-12">
                    <label class="form-label" for="co_address">Address</label>
                    <textarea name="address" id="co_address" class="form-control" rows="2" maxlength="100" placeholder="Street, area, city"></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="co_country">Country</label>
                    <input name="country" id="co_country" class="form-control" maxlength="100">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="co_pincode">Pincode</label>
                    <input name="pincode" id="co_pincode" class="form-control" maxlength="20" inputmode="numeric">
                </div>
            </div>
        </div>
    </div>

    <div class="form-step" data-step="Rates">
        <div class="form-section">
            <div class="fs-head"><div class="fs-title"><i class="bi bi-percent"></i> Rates on their bills</div><div class="fs-hint">Leave a rate empty if it does not apply.</div></div>
            <div class="row g-3">
                @foreach(['discount_percentage' => 'Discount', 'gst_percentage' => 'GST', 'tcs_percentage' => 'TCS', 'tds_percentage' => 'TDS'] as $field => $label)
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="co_{{ $field }}">{{ $label }}</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" max="100" name="{{ $field }}" id="co_{{ $field }}" class="form-control" inputmode="decimal">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="form-section">
            <div class="fs-head"><div class="fs-title"><i class="bi bi-chat-left-text"></i> Instruction</div><div class="fs-hint">Anything the front desk should know when billing this company.</div></div>
            <label class="form-label visually-hidden" for="co_instruction">Instruction</label>
            <textarea name="instruction" id="co_instruction" class="form-control" rows="3" placeholder="Optional"></textarea>
        </div>
    </div>

    <div class="form-step" data-step="Contacts">
        <div class="form-section">
            <div class="fs-head"><div class="fs-title"><i class="bi bi-people"></i> People to contact</div><div class="fs-hint">Fill in only the ones you have.</div></div>
            <div class="co-contacts">
                @foreach($contacts as $key => $label)
                    <div class="co-contact">
                        <div class="co-contact-role">{{ $label }}</div>
                        <div class="row g-2">
                            <div class="col-md-4"><input name="{{ $key }}_name" class="form-control" maxlength="100" placeholder="Name" aria-label="{{ $label }} name"></div>
                            <div class="col-md-5"><input type="email" name="{{ $key }}_email" class="form-control" maxlength="254" placeholder="Email" aria-label="{{ $label }} email"></div>
                            <div class="col-md-3"><input name="{{ $key }}_mobile" class="form-control" maxlength="15" inputmode="tel" placeholder="Mobile" aria-label="{{ $label }} mobile"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
