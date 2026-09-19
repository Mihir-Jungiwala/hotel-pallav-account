<?php

namespace App\Models\Concerns;

use App\Support\EntryStamp;

/**
 * An entry's `date` and `time` can only be chosen by an Admin or the
 * SuperAdmin. Anyone else gets the moment they saved on a new entry, and an
 * edit leaves the original untouched, whatever the form sent.
 */
trait LocksEntryStamp
{
    public static function bootLocksEntryStamp(): void
    {
        static::creating(function ($model) {
            if (EntryStamp::editable()) {
                return;
            }
            $now = now();
            $model->date = $now->toDateString();
            $model->time = $now->format('H:i:s');
        });

        static::updating(function ($model) {
            if (EntryStamp::editable()) {
                return;
            }
            foreach (['date', 'time'] as $column) {
                if ($model->isDirty($column)) {
                    $model->{$column} = $model->getOriginal($column);
                }
            }
        });
    }
}
