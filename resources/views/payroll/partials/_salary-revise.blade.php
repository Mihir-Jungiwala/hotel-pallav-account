{{-- The revision form for one employee ($row), shown in a dialog on their page. --}}
<div class="modal fade pay-form-modal" id="updateSalary{{ $row->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
        <form method="POST" action="{{ route('payroll.salary-update.update', $row) }}"
              class="salary-update-form">@csrf @method('PUT')
            <div class="modal-header">
                <div>
                    <div class="pms-eyebrow">Salary revision &middot; {{ $row->employee_code }}</div>
                    <h5 class="modal-title">{{ $row->name }}</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                {{-- The change itself, shown as current versus new, so nobody
                     has to hold the old figure in their head while typing. --}}
                <div class="ba-compare mb-4" data-salary-compare>
                    <div>
                        <div class="ba-label">Current salary</div>
                        <div class="ba-value" data-current-salary="{{ $row->salary }}">₹{{ number_format($row->salary, 2) }}</div>
                    </div>
                    <i class="bi bi-arrow-right ba-to" aria-hidden="true"></i>
                    <div class="ba-new">
                        <div class="ba-label">New salary</div>
                        <div class="ba-value" data-new-salary>₹{{ number_format($row->salary, 2) }}</div>
                        <div class="ba-delta same" data-salary-delta>No change yet</div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="fs-head">
                        <div class="fs-title"><i class="bi bi-cash-coin"></i> Pay</div>
                        <div class="fs-hint">The effective date is what the revision is filed under in the history.</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="su_date_{{ $row->id }}">Effective date<span class="req">*</span></label>
                            <input type="date" name="effective_date" id="su_date_{{ $row->id }}" class="form-control"
                                   value="{{ old('effective_date', date('Y-m-d')) }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="su_sal_{{ $row->id }}">New salary<span class="req">*</span></label>
                            <input type="number" step="0.01" min="0" name="salary" id="su_sal_{{ $row->id }}"
                                   class="form-control" value="{{ old('salary', $row->salary) }}" required data-salary-input>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="su_hrs_{{ $row->id }}">Working hours<span class="req">*</span></label>
                            <input type="number" step="0.5" min="0.5" max="24" name="daily_working_hours"
                                   id="su_hrs_{{ $row->id }}" class="form-control"
                                   value="{{ old('daily_working_hours', $row->daily_working_hours) }}" required>
                            <div class="form-text">Per day. Overtime is paid against this.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="su_mode_{{ $row->id }}">Payment mode<span class="req">*</span></label>
                            <select name="payment_mode" id="su_mode_{{ $row->id }}" class="form-select payment-mode" required>
                                @foreach(\App\Support\PayrollMasters::choices('salary_payment_mode') as $mode)
                                    <option value="{{ $mode }}" @selected(old('payment_mode', $row->payment_mode) === $mode)>{{ $mode }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="fs-head">
                        <div class="fs-title"><i class="bi bi-briefcase"></i> Role</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="su_desig_{{ $row->id }}">Designation</label>
                            <input name="designation" id="su_desig_{{ $row->id }}" class="form-control"
                                   value="{{ old('designation', $row->designation) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="su_dept_{{ $row->id }}">Department</label>
                            <input name="department" id="su_dept_{{ $row->id }}" class="form-control"
                                   value="{{ old('department', $row->department) }}">
                        </div>
                    </div>
                </div>

                <div class="form-section" data-show-when="payment_mode=Bank">
                    <div class="fs-head">
                        <div class="fs-title"><i class="bi bi-bank"></i> Bank details</div>
                        <div class="fs-hint">Needed only while salary is paid to a bank.</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="su_bank_{{ $row->id }}">Bank name</label>
                            <input name="bank_name" id="su_bank_{{ $row->id }}" class="form-control" value="{{ old('bank_name', $row->bank_name) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="su_holder_{{ $row->id }}">Account holder</label>
                            <input name="account_holder_name" id="su_holder_{{ $row->id }}" class="form-control" value="{{ old('account_holder_name', $row->account_holder_name) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="su_acc_{{ $row->id }}">Account number</label>
                            <input name="account_number" id="su_acc_{{ $row->id }}" class="form-control" value="{{ old('account_number', $row->account_number) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="su_ifsc_{{ $row->id }}">IFSC code</label>
                            <input name="ifsc_code" id="su_ifsc_{{ $row->id }}" class="form-control text-uppercase" value="{{ old('ifsc_code', $row->ifsc_code) }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="su_branch_{{ $row->id }}">Branch</label>
                            <input name="branch_name" id="su_branch_{{ $row->id }}" class="form-control" value="{{ old('branch_name', $row->branch_name) }}">
                        </div>
                    </div>
                </div>

                <div class="form-text mt-3">
                    <i class="bi bi-info-circle"></i>
                    Only the fields you actually change are written to the update history. Earlier records are never overwritten.
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-p">Save Revision</button>
            </div>
        </form>
    </div></div>
</div>

@push('scripts')
<script>
(function(){
    // Current vs new, updated as they type, so the size of the change is
    // visible without working it out
    document.querySelectorAll('[data-salary-compare]').forEach(function(panel){
        const form = panel.closest('form');
        const input = form?.querySelector('[data-salary-input]');
        const out = panel.querySelector('[data-new-salary]');
        const delta = panel.querySelector('[data-salary-delta]');
        const current = parseFloat(panel.querySelector('[data-current-salary]').dataset.currentSalary) || 0;
        if (!input || !out || !delta) return;

        const money = (n) => '₹' + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        const paint = function(){
            const next = parseFloat(input.value);
            if (Number.isNaN(next)) { out.textContent = '—'; delta.className = 'ba-delta same'; delta.textContent = 'Enter a salary'; return; }

            out.textContent = money(next);
            const diff = next - current;

            if (Math.abs(diff) < 0.005) {
                delta.className = 'ba-delta same';
                delta.textContent = 'No change';
            } else {
                const pct = current > 0 ? Math.abs(diff / current * 100).toFixed(1) + '%' : '';
                delta.className = 'ba-delta ' + (diff > 0 ? 'up' : 'down');
                delta.textContent = (diff > 0 ? '▲ ' : '▼ ') + money(Math.abs(diff)) + (pct ? ' (' + pct + ')' : '');
            }
        };

        input.addEventListener('input', paint);
        paint();
    });

})();
</script>
@endpush
