<?php

namespace App\Support;

use App\Models\BusinessUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

/**
 * Which business the screens are showing: Hotel Pallav, Pallav Food, or both
 * at once. Kept in the session so every screen agrees, and only ever set to a
 * unit that really exists.
 */
class UnitContext
{
    public const KEY = 'business_unit';

    public const BOTH = 'both';

    private static ?Collection $units = null;

    /** Every selectable unit, ordered the way the switcher shows them. */
    public static function units(): Collection
    {
        return self::$units ??= BusinessUnit::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    public static function find(?string $slug): ?BusinessUnit
    {
        return $slug === null ? null : self::units()->firstWhere('slug', $slug);
    }

    /** 'hotel', 'food' or 'both'. */
    public static function currentKey(): string
    {
        $stored = Session::get(self::KEY, self::BOTH);

        return self::find($stored) ? $stored : self::BOTH;
    }

    /** The selected unit, or null while both are shown together. */
    public static function current(): ?BusinessUnit
    {
        return self::find(Session::get(self::KEY));
    }

    public static function isBoth(): bool
    {
        return self::current() === null;
    }

    public static function label(): string
    {
        return self::current()?->name ?? 'Hotel Pallav + Pallav Food';
    }

    public static function remember(?string $slug): void
    {
        if ($slug === self::BOTH || $slug === null) {
            Session::forget(self::KEY);

            return;
        }

        if (self::find($slug)) {
            Session::put(self::KEY, $slug);
        }
    }

    /** True when a unit's own section belongs on screen. */
    public static function shows(string $slug): bool
    {
        return self::isBoth() || self::currentKey() === $slug;
    }

    public static function id(): ?int
    {
        return self::current()?->id;
    }

    /** Narrow a query to the selected unit; a no-op while both are shown. */
    public static function scope(Builder $query, string $column = 'business_unit_id'): Builder
    {
        $unit = self::current();

        return $unit ? $query->where($column, $unit->id) : $query;
    }

    /** The unit a newly created record belongs to, falling back to the request. */
    public static function forNewRecord(?string $requested = null): ?int
    {
        return self::current()?->id ?? self::find($requested)?->id ?? self::units()->first()?->id;
    }

    public static function flush(): void
    {
        self::$units = null;
    }
}
