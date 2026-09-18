<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Gives a record a permanent number in its own book. Numbers are handed out in
 * order and never reused, so deleting entry 5 leaves a gap rather than pulling
 * entry 6 down into its place.
 */
trait HasEntryNumber
{
    public static function bootHasEntryNumber(): void
    {
        static::creating(function ($model) {
            if ($model->entry_no === null) {
                $model->entry_no = static::nextEntryNumber();
            }
        });
    }

    public static function nextEntryNumber(): int
    {
        // withTrashed where it applies: a deleted entry still holds its number
        $query = DB::table((new static)->getTable());

        return (int) $query->max('entry_no') + 1;
    }

    /** The number as it is shown on screen and on documents. */
    public function entryNumber(): string
    {
        return str_pad((string) ($this->entry_no ?? 0), 4, '0', STR_PAD_LEFT);
    }
}
