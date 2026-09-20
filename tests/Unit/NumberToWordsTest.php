<?php

namespace Tests\Unit;

use App\Support\NumberToWords;
use PHPUnit\Framework\TestCase;

class NumberToWordsTest extends TestCase
{
    public function test_whole_rupees_in_indian_numbering(): void
    {
        $this->assertSame('Zero Rupees Only', NumberToWords::convert(0));
        $this->assertSame('One Rupee Only', NumberToWords::convert(1));
        $this->assertSame('Two Hundred Fifty Rupees Only', NumberToWords::convert(250));
        $this->assertSame('One Lakh Twenty Three Thousand Four Hundred Fifty Six Rupees Only', NumberToWords::convert(123456));
        $this->assertSame('One Hundred Crore Rupees Only', NumberToWords::convert(1000000000));
    }

    public function test_paise_are_spoken_not_rounded_away(): void
    {
        $this->assertSame('Twelve Thousand Five Hundred Rupees and Fifty Paise Only', NumberToWords::convert(12500.50));
        $this->assertSame('Fifty Paise Only', NumberToWords::convert(0.5));
        $this->assertSame('One Rupee and Five Paise Only', NumberToWords::convert(1.05));
        $this->assertSame('Ninety Nine Thousand Nine Hundred Ninety Nine Rupees and Ninety Nine Paise Only', NumberToWords::convert(99999.99));
    }
}
