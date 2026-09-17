<div class="table-responsive">
    <table class="table table-hover mb-0" style="font-size:13px;">
        <thead><tr>
            <th>Employee ID</th><th>Name</th><th>Designation</th><th>Salary</th>
            <th>Payable Days</th><th>Attendance Salary</th><th>OT (hrs)</th><th>OT Amount</th>
            <th>Bonus</th><th>Incentive</th><th>Deductions</th><th>Advance</th><th>Pending Adv.</th>
            <th>Net Salary</th><th>Mode</th><th>Status</th>
        </tr></thead>
        <tbody>
        @forelse($rows as $row)
            <tr>
                <td>{{ $row->employee_code }}</td>
                <td class="fw-semibold">{{ $row->employee_name }}</td>
                <td>{{ $row->designation }}</td>
                <td>₹{{ number_format($row->monthly_salary, 2) }}</td>
                <td>{{ number_format($row->total_payable_days, 2) }}</td>
                <td>₹{{ number_format($row->attendance_salary, 2) }}</td>
                <td>{{ number_format($row->overtime_hours, 2) }}</td>
                <td>₹{{ number_format($row->overtime_amount, 2) }}</td>
                <td>₹{{ number_format($row->bonus_amount, 2) }}</td>
                <td>₹{{ number_format($row->incentive_amount, 2) }}</td>
                <td>₹{{ number_format($row->deduction_amount, 2) }}</td>
                <td>₹{{ number_format($row->advance_deduction, 2) }}</td>
                <td>₹{{ number_format($row->pending_advance_amount, 2) }}</td>
                <td class="fw-bold" style="color:var(--p700);">₹{{ number_format($row->net_salary, 2) }}</td>
                <td>{{ $row->payment_mode }}</td>
                <td><span class="pay-pill pay-{{ $row->paymentTone() }}"><i class="bi {{ $row->paymentIcon() }}"></i>{{ $row->payment_status }}</span></td>
            </tr>
        @empty
            <tr><td colspan="16" class="text-center text-muted py-4">{{ $emptyMessage ?? 'No processed salary data for this selection.' }}</td></tr>
        @endforelse
        </tbody>
        @if($rows->isNotEmpty())
        <tfoot>
            <tr class="fw-bold" style="background:var(--p50);">
                <td colspan="13" class="text-end">Total</td>
                <td style="color:var(--p800);">₹{{ number_format($rows->sum('net_salary'), 2) }}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
        @endif
    </table>
</div>
