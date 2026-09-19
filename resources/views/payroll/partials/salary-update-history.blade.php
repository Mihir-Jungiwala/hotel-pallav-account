{{-- Loaded into the history dialog. Newest revision at the top, each one
     showing what the value was and what it became. --}}
@forelse($history as $batchId => $rows)
    @php $first = $rows->first(); @endphp
    <div class="history-entry">
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
            <div>
                <div class="fw-bold" style="color:var(--p700);font-size:13px;">
                    Effective {{ $first->effective_date->format('d M Y') }}
                </div>
                <div class="text-muted" style="font-size:11.5px;">
                    Saved {{ $first->created_at->format('d M Y, H:i') }} by {{ optional($first->changedBy)->name ?? 'System' }}
                </div>
            </div>
            <span class="badge-p px-2 py-1 rounded align-self-start">
                {{ $rows->count() }} {{ Str::plural('field', $rows->count()) }} changed
            </span>
        </div>

        <table class="table table-sm mb-0">
            <thead><tr><th>Field</th><th>Was</th><th>Became</th></tr></thead>
            <tbody>
            @foreach($rows as $row)
                <tr>
                    <td class="fw-semibold">{{ $row->field_name }}</td>
                    <td><span class="diff-old">{{ $row->previous_value ?: '-' }}</span></td>
                    <td><span class="diff-new">{{ $row->new_value ?: '-' }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@empty
    <div class="empty-state">
        <div class="es-icon"><i class="bi bi-clock-history"></i></div>
        <div class="es-title">Nothing revised yet</div>
        <div class="es-text">{{ $employee->name }} is still on the terms recorded when they joined.</div>
    </div>
@endforelse
