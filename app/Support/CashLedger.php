<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * What the Revenue and Expense pages share: the newest-first delete rule and
 * a single paged list built from several books.
 */
class CashLedger
{
    public const PER_PAGE = [10, 25, 50, 100];

    /** How each cash book is named on screen and on receipts. */
    public const BOOK_NAMES = ['hotel' => 'Hotel Pallav', 'food' => 'Pallav Food'];

    /** The Master Data list of depositors kept for each book (Revenue picks from these only). */
    public const DEPOSITOR_SETS = ['hotel' => 'revenue_depositor_hotel', 'food' => 'revenue_depositor_food'];

    /** The book's newest entry: highest number, latest row on a tie. */
    public static function newestId(string $model): ?int
    {
        return $model::query()->orderByDesc('entry_no')->orderByDesc('id')->value('id');
    }

    /**
     * Why this user cannot delete this entry, as [kind, message], or null when
     * they can. Entries come off a book newest first, and only an Admin or the
     * SuperAdmin may remove one. Pure, so it is safe to call while drawing a page.
     *
     * @return array{0: string, 1: string}|null  kind is "role", "owner" or "order"
     */
    public static function blocked(User $user, Model $record, ?int $newestId): ?array
    {
        if (! $user->isAdmin()) {
            return ['role', 'Only an Admin or the SuperAdmin can delete an entry.'];
        }

        if (! $user->canManage($record->user)) {
            return ['owner', 'You are not allowed to delete this entry.'];
        }

        if ($record->getKey() !== $newestId) {
            return ['order', 'Only the newest entry in a book can be deleted. Delete the newer ones first.'];
        }

        return null;
    }

    /** The reason a delete is refused, or null when it goes ahead. Force mode may step past the order rule. */
    public static function deleteRefusal(User $user, Model $record): ?string
    {
        $blocked = self::blocked($user, $record, self::newestId($record::class));

        if (! $blocked) {
            return null;
        }

        if ($blocked[0] === 'order' && ! ForceMode::locked(true, 'Delete an entry that is not the newest in its book')) {
            return null;
        }

        return $blocked[1];
    }

    /** What was typed in the search box, tidied. */
    public static function query(): string
    {
        return trim(mb_substr((string) request('q', ''), 0, 120));
    }

    /**
     * Keeps the entries that match the search box. Like Payroll's: every word
     * must match somewhere on the row, in any order; "quotes" match an exact
     * phrase; -word leaves rows out; >5000, <500 and 500-2000 filter by amount.
     * $extra gives the words a row shows that are not columns (book, kind, staff name).
     */
    public static function search(Collection $rows, string $q, callable $extra): Collection
    {
        if ($q === '') {
            return $rows;
        }

        preg_match_all('/-?"[^"]+"|\S+/u', $q, $found);
        $tests = [];

        foreach ($found[0] as $token) {
            $not = str_starts_with($token, '-') && strlen($token) > 1;
            $body = trim($not ? substr($token, 1) : $token, '"');
            $body = self::normalise($body);

            if ($body === '') {
                continue;
            }
            $tests[] = [$not, $body, preg_match('/^(>|<)\s*(\d+(\.\d+)?)$|^(\d+(\.\d+)?)-(\d+(\.\d+)?)$/', str_replace(',', '', $body))];
        }

        return $rows->filter(function ($r) use ($tests, $extra) {
            $text = self::normalise(implode(' ', array_filter([
                '#'.$r->entryNumber(), $r->entryNumber(), $r->date?->format('d-m-Y d M Y l Y-m-d'), substr((string) $r->time, 0, 5),
                $r->depositor, $r->withdrawer, $r->expense_name, $r->expense_head, $r->revenue_source, $r->reason, $r->instruction,
                $r->year_month, $r->full_name, $r->user?->displayName(), $r->amount_in_words,
                number_format((float) $r->amount, 2, '.', ''), number_format((float) $r->amount, 2), ...$extra($r),
            ], fn ($v) => $v !== null && $v !== '')));

            foreach ($tests as [$not, $body, $isAmount]) {
                $hit = $isAmount ? self::amountMatches($body, (float) $r->amount) : str_contains($text, $body);
                if ($hit === $not) {
                    return false;
                }
            }

            return true;
        })->values();
    }

    private static function amountMatches(string $body, float $amount): bool
    {
        $body = str_replace(',', '', $body);

        if (preg_match('/^>\s*(.+)$/', $body, $m)) {
            return $amount > (float) $m[1];
        }
        if (preg_match('/^<\s*(.+)$/', $body, $m)) {
            return $amount < (float) $m[1];
        }
        [$low, $high] = array_map('floatval', explode('-', $body, 2));

        return $amount >= min($low, $high) && $amount <= max($low, $high);
    }

    /** Lower case, commas inside numbers dropped (24,000 = 24000), punctuation to spaces. */
    private static function normalise(string $value): string
    {
        $value = mb_strtolower($value);
        $value = preg_replace('/(?<=\d),(?=\d)/', '', $value);

        return trim(preg_replace('/[^\p{L}\p{M}\p{N}.<>\-]+/u', ' ', $value));
    }

    /** Newest first, cut into pages, keeping the filter in the page links. */
    public static function page(Collection $rows, int $per): LengthAwarePaginator
    {
        $rows = $rows->sortByDesc(fn ($r) => sprintf('%s %s %010d', $r->date?->toDateString(), $r->time, $r->id))->values();
        $page = max(1, (int) request('page', 1));

        return (new LengthAwarePaginator($rows->forPage($page, $per)->values(), $rows->count(), $per, $page, [
            'path' => request()->url(), 'query' => request()->query(),
        ]));
    }

    public static function per(): int
    {
        return in_array((int) request('per'), self::PER_PAGE, true) ? (int) request('per') : 10;
    }

    /** Which book the page is showing: all, hotel or food. */
    public static function book(): string
    {
        return in_array(request('book'), ['hotel', 'food'], true) ? request('book') : 'all';
    }
}
