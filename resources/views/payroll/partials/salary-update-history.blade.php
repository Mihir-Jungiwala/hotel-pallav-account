@forelse($history as $batchId => $rows)
    @php $first = $rows->first(); @endphp
    <div class="border rounded p-3 mb-3" style="border-color:var(--line) !important;">
        <div class="d-flex justify-content-between flex-wrap small text-muted mb-2">
            <span><strong style="color:var(--p800);">{{ $first->created_at->format('d-m-Y H:i') }}</strong> by {{ optional($first->changedBy)->name ?? 'System' }}</span>
            <span>Effective: {{ $first->effective_date->format('d-m-Y') }}</span>
        </div>
        <table class="table table-sm mb-0">
            <thead><tr><th>Field</th><th>Previous Value</th><th>New Value</th></tr></thead>
            <tbody>
            @foreach($rows as $row)
                <tr>
                    <td class="fw-semibold">{{ $row->field_name }}</td>
                    <td class="text-muted">{{ $row->previous_value ?: '—' }}</td>
                    <td style="color:var(--p700);font-weight:600;">{{ $row->new_value ?: '—' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@empty
    <p class="text-muted mb-0">No updates recorded for this employee yet.</p>
@endforelse
