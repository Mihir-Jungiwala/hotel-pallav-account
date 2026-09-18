<?php

namespace App\Support;

use App\Models\OptionSet;
use Illuminate\Support\Collection;

/**
 * Reads the option lists kept in Master Data. Every form asks for its list by
 * key, so changing a list once changes it everywhere.
 */
class Masters
{
    /** @var array<string, Collection> */
    private static array $cache = [];

    public static function set(string $key): ?OptionSet
    {
        return OptionSet::with('activeItems')->where('key', $key)->first();
    }

    /** Active items for a list, in the order the SuperAdmin arranged them. */
    public static function items(string $key): Collection
    {
        return self::$cache[$key] ??= OptionSet::where('key', $key)->first()?->activeItems()->get() ?? collect();
    }

    /** Plain values, handy for validation rules. */
    public static function values(string $key): array
    {
        return self::items($key)->pluck('value')->all();
    }

    /** value => label, for building a dropdown. */
    public static function options(string $key): array
    {
        return self::items($key)->pluck('label', 'value')->all();
    }

    public static function default(string $key): ?string
    {
        $items = self::items($key);

        return ($items->firstWhere('is_default', true) ?? $items->first())?->value;
    }

    /** Falls back to the given list while a set is empty, so forms never break. */
    public static function valuesOr(string $key, array $fallback): array
    {
        $values = self::values($key);

        return $values ?: $fallback;
    }

    public static function flush(): void
    {
        self::$cache = [];
    }
}
