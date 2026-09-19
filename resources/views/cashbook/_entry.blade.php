{{-- One row of the list. Pass $e (built by the controller), $direction
     ("in" for Revenue, "out" for Expense) and $withKind (Expense has a Kind column). --}}
@php
    $r = $e['record'];
    $kind = $e['kind'] ?? 'deposit';
    $book = $e['book'];

    $title = match ($kind) {
        'deposit' => $r->depositor,
        'withdrawal' => $r->withdrawer,
        'misc' => $r->expense_name,
        'advance' => optional($r->staff)->name ?? 'Staff member',
    };

    $monthLabel = null;
    if ($kind === 'advance' && $r->year_month) {
        $monthLabel = 'For '.rescue(fn () => \Carbon\Carbon::createFromFormat('Y-m', $r->year_month)->format('F Y'), $r->year_month, false);
    }
    $chip = match ($kind) {
        'deposit' => $r->revenue_source,
        'misc' => $r->expense_head,
        'advance' => $monthLabel,
        default => null,
    };
    $kindLabel = ['withdrawal' => 'Cash withdrawal', 'misc' => 'Misc. expense', 'advance' => 'Staff advance'][$kind] ?? null;
    $note = $r->instruction ?? null;
    $bookName = $book ? \App\Support\CashLedger::BOOK_NAMES[$book] : 'Staff';

    $routeBase = $direction === 'in' ? 'revenue.'.$book : 'expense.'.$e['type'];
    $noun = $direction === 'in' ? 'deposit' : 'entry';
@endphp

<tr class="cb-row {{ $direction }}">
    <td class="cb-c-no" data-label="No."><span class="entry-no">#{{ $r->entryNumber() }}</span></td>
    <td class="cb-c-time" data-label="Time">{{ substr((string) $r->time, 0, 5) }}</td>
    <td data-label="Book"><span class="cb-book {{ $book ?? 'business' }}"><i class="bi {{ $book === 'hotel' ? 'bi-building' : ($book === 'food' ? 'bi-cup-hot' : 'bi-people') }}"></i> {{ $bookName }}</span></td>
    @if($withKind)
        <td data-label="Kind"><span class="cb-chip kind">{{ $kindLabel }}</span></td>
    @endif
    <td class="cb-c-name" data-label="{{ $direction === 'in' ? 'Depositor' : 'Name' }}"><strong>{{ $title }}</strong></td>
    <td class="cb-c-detail" data-label="Details">
        @if($chip)<span class="cb-chip">{{ $chip }}</span>@endif
        @if($note)<span class="cb-note">{{ $note }}</span>@endif
        @unless($chip || $note)<span class="text-muted">-</span>@endunless
    </td>
    <td class="cb-c-amount" data-label="Amount">
        <strong>{{ $direction === 'in' ? '+' : '-' }}₹{{ number_format((float) $r->amount, 2) }}</strong>
        <span>{{ $r->amount_in_words }}</span>
    </td>
    <td class="cb-c-by" data-label="By">{{ $r->user?->displayName() ?? $r->full_name }}</td>
    <td class="cb-c-actions">
        <a class="cb-icon-btn" href="{{ route($routeBase.'.view', $r) }}" target="_blank" title="Receipt (PDF)" aria-label="Open the PDF receipt"><i class="bi bi-file-earmark-pdf"></i></a>
        @if($e['canEdit'])
            <button type="button" class="cb-icon-btn" data-bs-toggle="modal" data-bs-target="#cashModal" data-cash-key="{{ $e['key'] }}" data-open-record title="Edit" aria-label="Edit this {{ $noun }}"><i class="bi bi-pencil-square"></i></button>
        @endif
        @if($e['delete'] === 'allowed')
            <form method="POST" action="{{ route($routeBase.'.destroy', $r) }}" class="d-inline" data-confirm-title="Delete #{{ $r->entryNumber() }}?" data-confirm="It comes off the cash book and cannot be restored.">
                @csrf @method('DELETE')
                <button class="cb-icon-btn danger" title="Delete" aria-label="Delete this {{ $noun }}"><i class="bi bi-trash"></i></button>
            </form>
        @endif
    </td>
</tr>
