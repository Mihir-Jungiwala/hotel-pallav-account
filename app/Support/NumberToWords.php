<?php

namespace App\Support;

class NumberToWords
{
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
    ];

    /**
     * Converts an integer amount into Indian-numbering-system words,
     * e.g. 123456 -> "One Lakh Twenty Three Thousand Four Hundred Fifty Six Rupees Only".
     */
    public static function convert(int|float $number): string
    {
        $number = (int) round($number);

        if ($number === 0) {
            return 'Zero Rupees Only';
        }

        if ($number < 0 || $number > 1000000000) {
            return (string) $number;
        }

        $crore = intdiv($number, 10000000);
        $number %= 10000000;
        $lakh = intdiv($number, 100000);
        $number %= 100000;
        $thousand = intdiv($number, 1000);
        $number %= 1000;
        $hundred = intdiv($number, 100);
        $remainder = $number % 100;

        $parts = [];

        if ($crore > 0) {
            $parts[] = self::twoDigits($crore).' Crore';
        }
        if ($lakh > 0) {
            $parts[] = self::twoDigits($lakh).' Lakh';
        }
        if ($thousand > 0) {
            $parts[] = self::twoDigits($thousand).' Thousand';
        }
        if ($hundred > 0) {
            $parts[] = self::ONES[$hundred].' Hundred';
        }
        if ($remainder > 0) {
            $parts[] = self::twoDigits($remainder);
        }

        return trim(implode(' ', $parts)).' Rupees Only';
    }

    private static function twoDigits(int $n): string
    {
        if ($n < 20) {
            return self::ONES[$n];
        }

        $tens = intdiv($n, 10);
        $ones = $n % 10;

        return trim(self::TENS[$tens].' '.self::ONES[$ones]);
    }
}
