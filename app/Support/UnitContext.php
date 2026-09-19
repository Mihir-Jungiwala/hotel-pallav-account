<?php

namespace App\Support;

use App\Models\BusinessUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

/**
 * Hotel Pallav and Pallav Food are one business under two names, run side by
 * side every day. Outside Payroll nothing is split or filtered by them: every
 * screen shows the whole business, with Hotel and Food as its two cash books.
 *
 * This used to be a switcher kept in the session. It is now fixed on "the
 * whole business", so the screens, reports and dashboard that still ask
 * which part is in view always get everything.
 */
class UnitContext
{
    public const KEY = 'business_unit';

    public const BOTH = 'both';

    private static ?Collection $units = null;

    /** The two names the business trades under, in display order. */
    public static function units(): Collection
    {
        return self::$units ??= BusinessUnit::where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    public static function find(?string $slug): ?BusinessUnit
    {
        return $slug === null ? null : self::units()->firstWhere('slug', $slug);
    }

    /** Always 'both': the whole business is always in view. */
    public static function currentKey(): string
    {
        return self::BOTH;
    }

    /** Never one name on its own. */
    public static function current(): ?BusinessUnit
    {
        return null;
    }

    public static function isBoth(): bool
    {
        return true;
    }

    public static function label(): string
    {
        return 'Hotel Pallav';
    }

    /** Kept so old links do not break; there is nothing left to switch. */
    public static function remember(?string $slug): void
    {
        Session::forget(self::KEY);
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
