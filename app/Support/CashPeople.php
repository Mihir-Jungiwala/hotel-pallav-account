<?php

namespace App\Support;

use App\Models\OptionItem;
use App\Models\OptionSet;

/**
 * The people who hand cash in or take it out, kept in Master Data. A name
 * typed on a Revenue or Expense form that is not on the list yet is added to
 * it, so the next entry can just pick it.
 */
class CashPeople
{
    public const KEY = 'cash_person';

    /** The name to store: the picked one, or the typed one when "Other" was chosen. */
    public static function resolve(?string $picked, ?string $typed): string
    {
        return trim((string) ($picked === '__other__' ? $typed : $picked));
    }

    /** Puts the name on the Master Data list if it is not there (or is hidden), and returns it as listed. */
    public static function remember(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return $name;
        }

        $set = OptionSet::firstOrCreate(['key' => self::KEY], [
            'name' => 'Cash Handlers',
            'description' => 'People who hand cash in (Revenue) or take it out (Expense)',
            'icon' => 'bi-person-vcard', 'input' => 'select', 'is_system' => true,
            'sort_order' => (int) OptionSet::max('sort_order') + 1,
        ]);

        $existing = $set->items()->get()->first(fn ($item) => mb_strtolower($item->value) === mb_strtolower($name));

        if ($existing) {
            if (! $existing->is_active) {
                $existing->update(['is_active' => true]);
                Masters::flush();
            }

            return $existing->value;
        }

        OptionItem::create([
            'option_set_id' => $set->id, 'label' => $name, 'value' => $name,
            'is_active' => true, 'is_default' => false,
            'sort_order' => (int) $set->items()->max('sort_order') + 1,
        ]);
        Masters::flush();

        return $name;
    }
}
