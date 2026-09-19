<?php

namespace App\Support;

/**
 * Countries a mobile number can belong to, and what a valid one looks like.
 *
 * India is the default, and first, because that is where nearly every number
 * here comes from; the rest are the places staff or their families are most
 * likely to be reached in. Numbers are stored as the national digits plus a
 * separate country code (never as one "+91..." string), so a stored number
 * keeps meaning what it always meant, and the country can change without
 * rewriting the number.
 *
 * The form reads the same list (see the phone field), so what the browser
 * accepts and what the server accepts cannot drift apart.
 */
class PhoneCountries
{
    public const DEFAULT = '91';

    /**
     * iso, name, dial code, shortest and longest national number, an optional
     * pattern for how one must begin, and an example.
     *
     * @var array<int, array{iso: string, name: string, dial: string, min: int, max: int, lead: ?string, sample: string}>
     */
    private const LIST = [
        ['IN', 'India', '91', 10, 10, '[6-9]', '98765 43210'],
        ['AE', 'United Arab Emirates', '971', 9, 9, '5', '50 123 4567'],
        ['SA', 'Saudi Arabia', '966', 9, 9, '5', '50 123 4567'],
        ['QA', 'Qatar', '974', 8, 8, null, '3123 4567'],
        ['KW', 'Kuwait', '965', 8, 8, null, '5000 0000'],
        ['OM', 'Oman', '968', 8, 8, null, '9212 3456'],
        ['BH', 'Bahrain', '973', 8, 8, null, '3600 1234'],
        ['NP', 'Nepal', '977', 10, 10, '9', '98 1234 5678'],
        ['BD', 'Bangladesh', '880', 10, 10, '1', '1712 345678'],
        ['LK', 'Sri Lanka', '94', 9, 9, '7', '71 234 5678'],
        ['PK', 'Pakistan', '92', 10, 10, '3', '301 2345678'],
        ['BT', 'Bhutan', '975', 8, 8, null, '17 123 456'],
        ['MV', 'Maldives', '960', 7, 7, null, '771 2345'],
        ['AF', 'Afghanistan', '93', 9, 9, null, '70 123 4567'],
        ['US', 'United States / Canada', '1', 10, 10, null, '201 555 0123'],
        ['GB', 'United Kingdom', '44', 10, 10, '7', '7400 123456'],
        ['IE', 'Ireland', '353', 9, 9, null, '85 012 3456'],
        ['AU', 'Australia', '61', 9, 9, '4', '412 345 678'],
        ['NZ', 'New Zealand', '64', 8, 10, null, '21 123 4567'],
        ['SG', 'Singapore', '65', 8, 8, null, '8123 4567'],
        ['MY', 'Malaysia', '60', 9, 10, null, '12 345 6789'],
        ['ID', 'Indonesia', '62', 9, 12, null, '812 3456 789'],
        ['TH', 'Thailand', '66', 9, 9, null, '81 234 5678'],
        ['PH', 'Philippines', '63', 10, 10, null, '917 123 4567'],
        ['HK', 'Hong Kong', '852', 8, 8, null, '5123 4567'],
        ['CN', 'China', '86', 11, 11, null, '131 2345 6789'],
        ['JP', 'Japan', '81', 10, 10, null, '90 1234 5678'],
        ['KR', 'South Korea', '82', 9, 10, null, '10 1234 5678'],
        ['DE', 'Germany', '49', 10, 11, null, '151 23456789'],
        ['FR', 'France', '33', 9, 9, null, '6 12 34 56 78'],
        ['IT', 'Italy', '39', 9, 10, null, '312 345 6789'],
        ['ES', 'Spain', '34', 9, 9, null, '612 34 56 78'],
        ['NL', 'Netherlands', '31', 9, 9, null, '6 12345678'],
        ['CH', 'Switzerland', '41', 9, 9, null, '78 123 45 67'],
        ['SE', 'Sweden', '46', 9, 9, null, '70 123 45 67'],
        ['RU', 'Russia', '7', 10, 10, null, '912 345 6789'],
        ['ZA', 'South Africa', '27', 9, 9, null, '71 123 4567'],
        ['KE', 'Kenya', '254', 9, 9, null, '712 345678'],
        ['NG', 'Nigeria', '234', 10, 10, null, '802 123 4567'],
        ['MU', 'Mauritius', '230', 8, 8, null, '5251 2345'],
        ['BR', 'Brazil', '55', 10, 11, null, '11 91234 5678'],
    ];

    /** @return array<int, array{iso: string, name: string, dial: string, min: int, max: int, lead: ?string, sample: string}> */
    public static function all(): array
    {
        return array_map(fn ($c) => [
            'iso' => $c[0], 'name' => $c[1], 'dial' => $c[2],
            'min' => $c[3], 'max' => $c[4], 'lead' => $c[5], 'sample' => $c[6],
        ], self::LIST);
    }

    /** @return string[] */
    public static function dials(): array
    {
        return array_column(self::all(), 'dial');
    }

    public static function find(?string $dial): ?array
    {
        foreach (self::all() as $country) {
            if ($country['dial'] === (string) $dial) {
                return $country;
            }
        }

        return null;
    }

    /**
     * "+971 50 123 4567" -> ['971', '501234567'], taking the longest dial code
     * that fits so "+9198..." is India, not a country starting with 9.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function split(string $raw): ?array
    {
        $raw = trim($raw);

        if (! str_starts_with($raw, '+') && ! str_starts_with($raw, '00')) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw);
        if (str_starts_with($raw, '00')) {
            $digits = substr($digits, 2);
        }

        $dials = self::dials();
        usort($dials, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($dials as $dial) {
            if (str_starts_with($digits, $dial)) {
                return [$dial, substr($digits, strlen($dial))];
            }
        }

        return null;
    }

    /**
     * Whatever was typed, reduced to a country code and national digits.
     *
     * Accepts "+91 98765 43210", "0091 98765 43210", "098765-43210",
     * "919876543210" (with India selected) and plain "9876543210". A leading
     * "+" names the country itself, and overrides whatever was selected.
     *
     * @return array{0: string, 1: string} [dial, national digits]
     */
    public static function normalise(?string $raw, ?string $selected = null): array
    {
        $raw = trim((string) $raw);
        // Nothing chosen means India. A code that is not in the list is kept
        // as given, so validation can refuse it rather than quietly judging
        // the number as an Indian one.
        $dial = ($selected !== null && $selected !== '') ? (string) $selected : self::DEFAULT;

        if ($parts = self::split($raw)) {
            return $parts;
        }

        $digits = preg_replace('/\D+/', '', $raw);
        $country = self::find($dial);

        if ($country && $digits !== '') {
            // A trunk zero, as in 098765 43210
            if (strlen($digits) === $country['max'] + 1 && str_starts_with($digits, '0')) {
                $digits = substr($digits, 1);
            }
            // The country code typed without its "+"
            elseif (str_starts_with($digits, $dial)
                && strlen($digits) >= strlen($dial) + $country['min']
                && strlen($digits) <= strlen($dial) + $country['max']
                && ! (strlen($digits) >= $country['min'] && strlen($digits) <= $country['max'])) {
                $digits = substr($digits, strlen($dial));
            }
        }

        return [$dial, $digits];
    }

    /** A plain sentence saying what is wrong with a number, or null when it is fine. */
    public static function check(?string $dial, ?string $national): ?string
    {
        $country = self::find($dial);

        if ($country === null) {
            return 'Choose a country code from the list.';
        }

        $national = (string) $national;
        $length = strlen($national);
        $wanted = $country['min'] === $country['max'] ? (string) $country['min'] : $country['min'].' to '.$country['max'];

        if (! ctype_digit($national) || $length < $country['min'] || $length > $country['max']) {
            return $country['iso'] === 'IN'
                ? 'Enter a 10-digit mobile number starting with 6, 7, 8 or 9.'
                : 'Enter a valid '.$country['name'].' number of '.$wanted.' digits.';
        }

        if ($country['lead'] !== null && ! preg_match('/^'.$country['lead'].'/', $national)) {
            return $country['iso'] === 'IN'
                ? 'Enter a 10-digit mobile number starting with 6, 7, 8 or 9.'
                : 'That does not look like a '.$country['name'].' mobile number.';
        }

        return null;
    }

    /** What one country's number looks like, in a sentence. */
    public static function message(?string $dial): string
    {
        $country = self::find($dial) ?? self::find(self::DEFAULT);

        if ($country['iso'] === 'IN') {
            return 'Enter a 10-digit mobile number starting with 6, 7, 8 or 9.';
        }

        $wanted = $country['min'] === $country['max'] ? (string) $country['min'] : $country['min'].' to '.$country['max'];

        return 'Enter a valid '.$country['name'].' number of '.$wanted.' digits.';
    }

    /** The same rule as an HTML pattern, so the browser can check it as they type. */
    public static function pattern(?string $dial): string
    {
        $country = self::find($dial) ?? self::find(self::DEFAULT);
        $lead = $country['lead'] ?? '[0-9]';

        return $lead.'[0-9]{'.($country['min'] - 1).','.($country['max'] - 1).'}';
    }

    /** "+91 98765 43210" for showing a stored number. */
    public static function format(?string $dial, ?string $national): string
    {
        $national = (string) $national;
        if ($national === '') {
            return '';
        }

        $dial = self::find($dial) ? (string) $dial : self::DEFAULT;

        // Split ten digits five and five, the way Indian numbers are read
        if ($dial === '91' && strlen($national) === 10) {
            return '+91 '.substr($national, 0, 5).' '.substr($national, 5);
        }

        return '+'.$dial.' '.$national;
    }
}
