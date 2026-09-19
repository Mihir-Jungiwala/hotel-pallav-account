<?php

namespace App\Models;

use App\Models\Concerns\HasEntryNumber;
use App\Models\Concerns\LocksEntryStamp;
use App\Support\RichText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftHandover extends Model
{
    use HasEntryNumber, LocksEntryStamp;

    protected $guarded = ['id'];

    protected $casts = ['date' => 'date', 'notes' => 'array', 'instructions' => 'array'];

    public const DENOMINATIONS = [
        'd500' => 500, 'd200' => 200, 'd100' => 100, 'd50' => 50,
        'd20' => 20, 'd10' => 10, 'd5' => 5, 'coins' => 1,
    ];

    /**
     * Which shift a time of day falls in, matched by name against the shifts
     * set up in Master Data (so "Morning Shift" still counts as morning).
     * Hours are [from, to) on a 24-hour clock; night wraps past midnight.
     */
    public const SHIFT_HOURS = [
        'morning' => [6, 12],
        'afternoon' => [12, 17],
        'evening' => [17, 22],
        'night' => [22, 6],
    ];

    /** @param  list<string>  $shifts  the shift names on offer */
    public static function shiftAt(string $time, array $shifts): ?string
    {
        $hour = (int) substr($time, 0, 2);

        foreach (self::SHIFT_HOURS as $word => [$from, $to]) {
            $inside = $from < $to ? ($hour >= $from && $hour < $to) : ($hour >= $from || $hour < $to);
            if (! $inside) {
                continue;
            }
            foreach ($shifts as $shift) {
                if (str_contains(strtolower($shift), $word)) {
                    return $shift;
                }
            }
        }

        return null;
    }

    public static function denominationLabel(string $denom): string
    {
        return $denom === 'coins' ? 'Coins' : '₹'.self::DENOMINATIONS[$denom];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /** Notes for the next shift, each a safe formatted HTML point. @return list<string> */
    public function noteList(): array
    {
        return self::points($this->notes, fn ($n) => RichText::inline($n));
    }

    /** Special instructions, each a plain-text point. @return list<string> */
    public function instructionList(): array
    {
        return self::points($this->instructions, fn ($i) => trim((string) $i));
    }

    private static function points(mixed $items, callable $clean): array
    {
        return collect((array) $items)->map($clean)->filter(fn ($p) => $p !== null && $p !== '')->values()->all();
    }
}
