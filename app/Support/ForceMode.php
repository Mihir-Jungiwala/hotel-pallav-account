<?php

namespace App\Support;

use App\Models\UserAuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

/**
 * SuperAdmin override. While it is armed, field validation is relaxed and the
 * business locks (processed salary, used advances, dependent records) step
 * aside, so a bad day can be corrected by hand. Everything it lets through is
 * written to the audit log, and it is off unless the SuperAdmin turns it on.
 */
class ForceMode
{
    public const KEY = 'force_mode';

    /** Rules that stay even under force mode: types the database needs, and upload safety. */
    private const KEEP = [
        'nullable', 'numeric', 'integer', 'decimal', 'boolean', 'date', 'array',
        'string', 'file', 'image', 'mimes', 'mimetypes', 'confirmed', 'sometimes',
    ];

    public static function availableTo(): bool
    {
        return Auth::check() && Auth::user()->isSuperAdmin();
    }

    public static function enabled(): bool
    {
        return self::availableTo() && Session::get(self::KEY) === true;
    }

    public static function set(bool $on): void
    {
        if (! self::availableTo()) {
            return;
        }

        $on ? Session::put(self::KEY, true) : Session::forget(self::KEY);

        UserAuditLog::record($on ? 'force.enabled' : 'force.disabled', Auth::user());
    }

    /**
     * Drop the rules that would refuse a value, keeping the ones that protect
     * the column type and file uploads.
     */
    public static function relax(array $rules): array
    {
        $relaxed = [];

        foreach ($rules as $field => $fieldRules) {
            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            if (! is_array($fieldRules)) {
                $relaxed[$field] = $fieldRules;

                continue;
            }

            $kept = array_values(array_filter($fieldRules, function ($rule) use ($fieldRules) {
                if (! is_string($rule)) {
                    return false;   // Rule objects (unique, exists, enums) are the ones we are relaxing
                }

                $name = Str::before($rule, ':');

                // A size cap only matters for uploads, where it stops a huge file
                if ($name === 'max') {
                    return self::isUpload($fieldRules);
                }

                return in_array($name, self::KEEP, true);
            }));

            $kept[] = 'nullable';
            $relaxed[$field] = array_values(array_unique($kept));
        }

        return $relaxed;
    }

    /**
     * With the rules relaxed, a field the user left out would still hit a
     * NOT NULL column, so stand in a harmless blank of the right shape:
     * today's date, the current time, zero, or an empty string. Foreign keys
     * and uploads are left alone — a made-up id would point at nothing.
     */
    public static function fill(array $data, array $rules): array
    {
        foreach ($rules as $field => $fieldRules) {
            if (str_contains($field, '.') || str_contains($field, '*')) {
                continue;   // nested and wildcard rules keep whatever was sent
            }

            if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                continue;
            }

            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            $names = array_map(
                fn ($rule) => is_string($rule) ? Str::before($rule, ':') : '',
                is_array($fieldRules) ? $fieldRules : []
            );

            if (str_ends_with($field, '_id') || array_intersect($names, ['file', 'image', 'mimes', 'mimetypes'])) {
                continue;
            }

            if (in_array('date', $names, true)) {
                $data[$field] = now()->toDateString();
            } elseif (array_intersect($names, ['numeric', 'integer', 'decimal'])) {
                $data[$field] = 0;
            } else {
                // An empty box arrives as null, which a NOT NULL column rejects
                $data[$field] = $field === 'time' ? now()->format('H:i') : '';
            }
        }

        return $data;
    }

    private static function isUpload(array $fieldRules): bool
    {
        foreach ($fieldRules as $rule) {
            if (is_string($rule) && in_array(Str::before($rule, ':'), ['file', 'image', 'mimes', 'mimetypes'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Wraps a business lock. Normally it returns the condition unchanged; with
     * force mode armed a blocked action is allowed through and audited.
     */
    public static function locked(bool $condition, ?string $what = null): bool
    {
        if (! $condition) {
            return false;
        }

        if (! self::enabled()) {
            return true;
        }

        self::audit($what);

        return false;
    }

    public static function audit(?string $what = null): void
    {
        UserAuditLog::record('force.override', Auth::user(), array_filter([
            'lock' => $what,
            'route' => request()?->route()?->getName(),
            'path' => request()?->path(),
            'method' => request()?->method(),
        ]));
    }
}
