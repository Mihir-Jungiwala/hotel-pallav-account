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
     * Converts an amount into Indian-numbering-system words, paise included,
     * e.g. 123456 -> "One Lakh Twenty Three Thousand Four Hundred Fifty Six Rupees Only"
     * and 12500.50 -> "Twelve Thousand Five Hundred Rupees and Fifty Paise Only".
     */
    public static function convert(int|float $number): string
    {
        $paiseTotal = (int) round($number * 100);

        if ($paiseTotal === 0) {
            return 'Zero Rupees Only';
        }

        if ($paiseTotal < 0 || $paiseTotal > 100000000000) {
            return (string) $number;
        }

        $rupees = intdiv($paiseTotal, 100);
        $paise = $paiseTotal % 100;

        $words = $rupees > 0 ? self::rupeeWords($rupees).($rupees === 1 ? ' Rupee' : ' Rupees') : '';

        if ($paise > 0) {
            $words .= ($words !== '' ? ' and ' : '').self::twoDigits($paise).' Paise';
        }

        return $words.' Only';
    }

    /** A whole number of rupees in words, without the currency. */
    private static function rupeeWords(int $number): string
    {
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
            $parts[] = self::rupeeWords($crore).' Crore';
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

        return trim(implode(' ', $parts));
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
