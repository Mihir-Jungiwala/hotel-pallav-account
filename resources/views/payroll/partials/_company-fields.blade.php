@php $c = $target ?? null; @endphp

<div class="form-wizard" data-wizard>

    {{-- Step 1 — identity and where they are --}}
    <div class="form-step" data-step="Company">
        <div class="row g-3">
            <div class="col-md-7">
                <label class="form-label">Company Name *</label>
                <input name="name" class="form-control" value="{{ old('name', $c->name ?? '') }}" required>
            </div>
            <div class="col-md-5">
                <label class="form-label">Company Code *</label>
                <input name="code" class="form-control text-uppercase" value="{{ old('code', $c->code ?? '') }}" required>
                <div class="form-text">A short unique code, e.g. HP01.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label">Owner Name <span class="wz-optional">optional</span></label>
                <input name="owner_name" class="form-control" value="{{ old('owner_name', $c->owner_name ?? '') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Company Logo <span class="wz-optional">optional</span></label>
                <input type="file" name="logo" class="form-control" accept="image/*">
                @include('payroll.partials._current-file', ['path' => $c->logo_path ?? null, 'label' => 'Current logo'])
            </div>

            <div class="col-md-6">
                <label class="form-label">Mobile Number *</label>
                <input name="mobile_number" class="form-control" value="{{ old('mobile_number', $c->mobile_number ?? '') }}" required>
                <div class="form-text">Shown on salary slips and letters.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Email Address <span class="wz-optional">optional</span></label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $c->email ?? '') }}">
            </div>

            <div class="col-12">
                <label class="form-label">Address *</label>
                <textarea name="address" class="form-control" rows="2" required>{{ old('address', $c->address ?? '') }}</textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">City *</label>
                <input name="city" class="form-control" value="{{ old('city', $c->city ?? '') }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">State *</label>
                <input name="state" class="form-control" value="{{ old('state', $c->state ?? '') }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Pincode <span class="wz-optional">optional</span></label>
                <input name="pincode" class="form-control" value="{{ old('pincode', $c->pincode ?? '') }}">
            </div>
        </div>
    </div>

    {{-- Step 2 — statutory numbers, all optional until they're registered --}}
    <div class="form-step" data-step="Legal">
        <div class="form-text mb-3">Statutory registrations. Leave blank any the company doesn't hold yet.</div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">PAN Number <span class="wz-optional">optional</span></label>
                <input name="pan_number" class="form-control text-uppercase" value="{{ old('pan_number', $c->pan_number ?? '') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">TAN Number <span class="wz-optional">optional</span></label>
                <input name="tan_number" class="form-control text-uppercase" value="{{ old('tan_number', $c->tan_number ?? '') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">PF Registration Number <span class="wz-optional">optional</span></label>
                <input name="pf_registration_number" class="form-control" value="{{ old('pf_registration_number', $c->pf_registration_number ?? '') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">ESIC Registration Number <span class="wz-optional">optional</span></label>
                <input name="esic_registration_number" class="form-control" value="{{ old('esic_registration_number', $c->esic_registration_number ?? '') }}">
            </div>
            <div class="col-12">
                <label class="form-label">Professional Tax Registration Number <span class="wz-optional">optional</span></label>
                <input name="professional_tax_registration_number" class="form-control" value="{{ old('professional_tax_registration_number', $c->professional_tax_registration_number ?? '') }}">
            </div>
        </div>
    </div>

    {{-- Step 3 — who signs the documents --}}
    <div class="form-step" data-step="Signatory">
        <div class="form-text mb-3">This person signs salary slips and joining letters generated for this company.</div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Authorized Person Name *</label>
                <input name="authorized_person_name" class="form-control" value="{{ old('authorized_person_name', $c->authorized_person_name ?? '') }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Designation *</label>
                <input name="authorized_designation" class="form-control" value="{{ old('authorized_designation', $c->authorized_designation ?? '') }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Mobile Number <span class="wz-optional">optional</span></label>
                <input name="authorized_mobile" class="form-control" value="{{ old('authorized_mobile', $c->authorized_mobile ?? '') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Email Address <span class="wz-optional">optional</span></label>
                <input type="email" name="authorized_email" class="form-control" value="{{ old('authorized_email', $c->authorized_email ?? '') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">Signature Image <span class="wz-optional">optional</span></label>
                <input type="file" name="signature_image" class="form-control" accept="image/*">
                @include('payroll.partials._current-file', ['path' => $c->signature_image_path ?? null, 'label' => 'Current signature'])
            </div>
        </div>
    </div>

    {{-- Step 4 — banking, needed only for bank payouts --}}
    <div class="form-step" data-step="Banking">
        <div class="form-text mb-3">The account salaries are paid from. Optional if you only pay in cash.</div>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Bank Name <span class="wz-optional">optional</span></label>
                <input name="bank_name" class="form-control" value="{{ old('bank_name', $c->bank_name ?? '') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Branch Name <span class="wz-optional">optional</span></label>
                <input name="branch_name" class="form-control" value="{{ old('branch_name', $c->branch_name ?? '') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Account Number <span class="wz-optional">optional</span></label>
                <input name="account_number" class="form-control" value="{{ old('account_number', $c->account_number ?? '') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">IFSC Code <span class="wz-optional">optional</span></label>
                <input name="ifsc_code" class="form-control text-uppercase" value="{{ old('ifsc_code', $c->ifsc_code ?? '') }}">
            </div>
        </div>
    </div>
</div>
