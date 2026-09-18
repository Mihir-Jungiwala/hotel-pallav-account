@php
    $e = $target ?? null;
    $takenCodes = ($employees ?? collect())->pluck('employee_code')->values();
@endphp

<div class="form-wizard" data-wizard data-bank-scope>

    {{-- Step 1 - who the employee is --}}
    <div class="form-step" data-step="Identity">
        <div class="alert alert-light border mb-3" style="background:var(--p50);">
            <div class="row small">
                <div class="col-md-4"><span class="text-muted">Date &amp; Time</span><br><strong>{{ now()->format('d M Y, H:i') }}</strong></div>
                <div class="col-md-4"><span class="text-muted">Current User</span><br><strong>{{ auth()->user()->name }}</strong></div>
                <div class="col-md-4"><span class="text-muted">Company</span><br><strong>{{ $company->name }}</strong></div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Employee ID *</label>
                <input name="employee_code" class="form-control"
                       value="{{ old('employee_code', $e->employee_code ?? '') }}" required
                       data-unique-among='@json($takenCodes)'
                       data-unique-self="{{ $e->employee_code ?? '' }}"
                       data-unique-message="This Employee ID is already used in this company">
            </div>
            <div class="col-md-8">
                <label class="form-label">Employee Name *</label>
                <input name="name" class="form-control" value="{{ old('name', $e->name ?? '') }}" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Designation *</label>
                <input name="designation" class="form-control" value="{{ old('designation', $e->designation ?? '') }}" required>
                <div class="form-text">Printed on the salary slip and joining letter.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Department <span class="wz-optional">optional</span></label>
                <input name="department" class="form-control" value="{{ old('department', $e->department ?? '') }}">
            </div>

            <div class="col-md-6">
                <label class="form-label">Contact Number <span class="wz-optional">optional</span></label>
                <input name="contact_number" class="form-control" value="{{ old('contact_number', $e->contact_number ?? '') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Employee Photo <span class="wz-optional">optional</span></label>
                <input type="file" name="photo" class="form-control" accept="image/*">
                @include('payroll.partials._current-file', ['path' => $e->photo_path ?? null, 'label' => 'Current photo'])
            </div>

            <div class="col-12">
                <label class="form-label">Address <span class="wz-optional">optional</span></label>
                <textarea name="address" class="form-control" rows="2">{{ old('address', $e->address ?? '') }}</textarea>
            </div>
        </div>
    </div>

    {{-- Step 2 - what drives the payroll maths --}}
    {{-- Personal details the old staff profile carried --}}
    <div class="form-step" data-step="Personal">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Email <span class="wz-optional">optional</span></label>
                <input type="email" name="email" class="form-control" value="{{ old('email', $e->email ?? '') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Date of birth <span class="wz-optional">optional</span></label>
                <input type="date" name="date_of_birth" class="form-control"
                       value="{{ old('date_of_birth', optional($e->date_of_birth ?? null)->format('Y-m-d')) }}">
            </div>
            <div class="col-md-6">
                @include('partials._option-field', ['key' => 'gender', 'name' => 'gender', 'value' => $e->gender ?? null, 'label' => 'Gender'])
            </div>
            <div class="col-md-6">
                <label class="form-label">Nationality <span class="wz-optional">optional</span></label>
                <input name="nationality" class="form-control" value="{{ old('nationality', $e->nationality ?? '') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Country and pincode <span class="wz-optional">optional</span></label>
                <input name="country_and_pincode" class="form-control" value="{{ old('country_and_pincode', $e->country_and_pincode ?? '') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Qualification <span class="wz-optional">optional</span></label>
                <input name="qualification" class="form-control" value="{{ old('qualification', $e->qualification ?? '') }}">
            </div>
            <div class="col-md-6">
                <label class="form-label">Institution <span class="wz-optional">optional</span></label>
                <input name="qualification_institution" class="form-control" value="{{ old('qualification_institution', $e->qualification_institution ?? '') }}">
            </div>
            <div class="col-12">
                <label class="form-label">Skills <span class="wz-optional">optional</span></label>
                <textarea name="skills" class="form-control" rows="2">{{ old('skills', $e->skills ?? '') }}</textarea>
                <div class="form-text">Prints on the employee record, handy when planning cover.</div>
            </div>
        </div>
    </div>

    <div class="form-step" data-step="Employment">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Joining Date *</label>
                <input type="date" name="joining_date" class="form-control"
                       value="{{ old('joining_date', optional($e->joining_date ?? null)->format('Y-m-d')) }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Monthly Salary *</label>
                <input type="number" step="0.01" min="0" name="salary" class="form-control"
                       value="{{ old('salary', $e->salary ?? '') }}" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Daily Working Hours *</label>
                <input type="number" step="0.5" min="0.5" max="24" name="daily_working_hours" class="form-control"
                       value="{{ old('daily_working_hours', $e->daily_working_hours ?? 8) }}" required>
                <div class="form-text">Used to price overtime.</div>
            </div>

            <div class="col-12">
                <label class="form-label">Responsibilities <span class="wz-optional">optional</span></label>
                <textarea name="responsibilities" class="form-control" rows="3">{{ old('responsibilities', $e->responsibilities ?? '') }}</textarea>
                <div class="form-text">Fills the {RESPONSIBILITIES} placeholder in the joining letter.</div>
            </div>
        </div>
    </div>

    {{-- Step 3 - how they get paid --}}
    <div class="form-step" data-step="Payment">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Payment Mode *</label>
                <select name="payment_mode" class="form-select payment-mode" required>
                    @foreach(\App\Support\Masters::valuesOr('salary_payment_mode', ['Cash', 'Bank']) as $mode)
                        <option value="{{ $mode }}" @selected(old('payment_mode', $e->payment_mode ?? 'Cash') === $mode)>{{ $mode }}</option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 bank-details"><hr class="mt-2"><div class="fw-bold" style="color:var(--p700);">Bank Details</div></div>
            <div class="col-md-6 bank-details">
                <label class="form-label">Bank Name *</label>
                <input name="bank_name" class="form-control" value="{{ old('bank_name', $e->bank_name ?? '') }}" data-require-when-bank>
            </div>
            <div class="col-md-6 bank-details">
                <label class="form-label">Account Holder Name *</label>
                <input name="account_holder_name" class="form-control" value="{{ old('account_holder_name', $e->account_holder_name ?? '') }}" data-require-when-bank>
            </div>
            <div class="col-md-4 bank-details">
                <label class="form-label">Account Number *</label>
                <input name="account_number" class="form-control" value="{{ old('account_number', $e->account_number ?? '') }}" data-require-when-bank>
            </div>
            <div class="col-md-4 bank-details">
                <label class="form-label">IFSC Code *</label>
                <input name="ifsc_code" class="form-control text-uppercase" value="{{ old('ifsc_code', $e->ifsc_code ?? '') }}" data-require-when-bank>
            </div>
            <div class="col-md-4 bank-details">
                <label class="form-label">Branch <span class="wz-optional">optional</span></label>
                <input name="branch_name" class="form-control" value="{{ old('branch_name', $e->branch_name ?? '') }}">
            </div>
        </div>
    </div>

    {{-- Step 4 - supporting records, none of it blocking --}}
    <div class="form-step" data-step="Documents">
        <div class="row g-3">
            <div class="col-12"><div class="fw-bold" style="color:var(--p700);">ID Proof <span class="wz-optional">optional</span></div></div>
            <div class="col-md-4">
                <label class="form-label">ID Proof Type</label>
                <select name="id_proof_type_id" class="form-select">
                    <option value="">Select&hellip;</option>
                    @foreach($idProofTypes as $type)
                        <option value="{{ $type->id }}" @selected((int) old('id_proof_type_id', $e->id_proof_type_id ?? 0) === $type->id)>{{ $type->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">ID Proof Number</label>
                <input name="id_proof_number" class="form-control" value="{{ old('id_proof_number', $e->id_proof_number ?? '') }}">
            </div>
            <div class="col-md-4">
                <label class="form-label">ID Proof Image (front)</label>
                <input type="file" name="id_proof_image" class="form-control" accept="image/*">
                @include('payroll.partials._current-file', ['path' => $e->id_proof_image_path ?? null, 'label' => 'Current ID proof'])
            </div>

            <div class="col-md-4">
                <label class="form-label">ID Proof Image (back) <span class="wz-optional">optional</span></label>
                <input type="file" name="id_proof_back_image" class="form-control" accept="image/*">
                @include('payroll.partials._current-file', ['path' => $e->id_proof_back_image_path ?? null, 'label' => 'Current back image'])
            </div>

            <div class="col-md-4">
                <label class="form-label">Resume <span class="wz-optional">optional</span></label>
                <input type="file" name="resume" class="form-control" accept="application/pdf,image/*">
                @include('payroll.partials._current-file', ['path' => $e->resume_path ?? null, 'label' => 'Current resume'])
            </div>

            <div class="col-12"><hr>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="fw-bold" style="color:var(--p700);">Deductions <span class="wz-optional">optional</span></div>
                    <button type="button" class="btn btn-sm btn-outline-p add-deduction-row"><i class="bi bi-plus-lg"></i> Add</button>
                </div>
                <div class="form-text">Recovered automatically during salary processing.</div>
            </div>

            <div class="col-12">
                <div class="deduction-rows">
                    @foreach(($e->deductions ?? collect()) as $index => $assignment)
                        <div class="row g-2 mb-2 deduction-row">
                            <input type="hidden" name="deductions[{{ $index }}][id]" value="{{ $assignment->id }}">
                            <div class="col-md-5">
                                <select name="deductions[{{ $index }}][deduction_id]" class="form-select">
                                    <option value="">Select deduction&hellip;</option>
                                    @foreach($deductionOptions as $option)
                                        <option value="{{ $option->id }}" data-amount="{{ $option->amount }}" @selected($assignment->deduction_id === $option->id)>{{ $option->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="deductions[{{ $index }}][deduction_type]" class="form-select">
                                    @foreach(\App\Support\Masters::valuesOr('advance_deduction_type', ['One Time', 'Monthly']) as $type)
                                        <option value="{{ $type }}" @selected($assignment->deduction_type === $type)>{{ $type }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3"><input type="number" step="0.01" name="deductions[{{ $index }}][amount]" class="form-control" value="{{ $assignment->amount }}" placeholder="Amount"></div>
                            <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-deduction-row"><i class="bi bi-x"></i></button></div>
                        </div>
                    @endforeach
                </div>

                <template class="deduction-template">
                    <div class="row g-2 mb-2 deduction-row">
                        <div class="col-md-5">
                            <select name="deductions[__INDEX__][deduction_id]" class="form-select">
                                <option value="">Select deduction&hellip;</option>
                                @foreach($deductionOptions as $option)
                                    <option value="{{ $option->id }}" data-amount="{{ $option->amount }}">{{ $option->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select name="deductions[__INDEX__][deduction_type]" class="form-select">
                                <option value="One Time">One Time</option>
                                <option value="Monthly" selected>Monthly</option>
                            </select>
                        </div>
                        <div class="col-md-3"><input type="number" step="0.01" name="deductions[__INDEX__][amount]" class="form-control" placeholder="Amount"></div>
                        <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-deduction-row"><i class="bi bi-x"></i></button></div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
