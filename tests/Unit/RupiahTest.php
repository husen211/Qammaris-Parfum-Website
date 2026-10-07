<?php

namespace Tests\Unit;

use App\Support\Rupiah;
use InvalidArgumentException;
use OverflowException;
use PHPUnit\Framework\TestCase;

class RupiahTest extends TestCase
{
    public function test_decimal_conversion_and_formatting_do_not_round_or_truncate(): void
    {
        foreach (['0' => 'Rp 0', '1' => 'Rp 1', '100.9' => 'Rp 100,90',
            '175000.00' => 'Rp 175.000', '99999999.99' => 'Rp 99.999.999,99', '-0.29' => 'Rp -0,29'] as $amount => $formatted) {
            $this->assertSame($formatted, Rupiah::format($amount));
        }
        $this->assertSame(29, Rupiah::minorUnits(0.29));
        $this->assertSame('0.87', Rupiah::decimal(Rupiah::minorUnits('0.29') * 3));
        $this->assertSame('0.03', Rupiah::sum(['0.01', '0.02']));
        $this->assertSame('201.80', Rupiah::sum(['100.90', '100.90']));
        $this->assertSame('9899999999.01', Rupiah::decimal(Rupiah::minorUnits('99999999.99') * 99));
    }

    public function test_invalid_precision_is_rejected_instead_of_silently_rounded(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Rupiah::minorUnits('100.909');
    }

    public function test_new_prices_require_whole_rupiah_but_accept_database_decimal_zeroes(): void
    {
        foreach (['1', '175000', '100.0', '100.00'] as $price) {
            $this->assertSame(1, preg_match(Rupiah::WHOLE_PRICE_PATTERN, $price));
        }
        foreach (['100.90', '0.29', '100.909', '1e3', '100,50', '1.000.000', '-1'] as $price) {
            $this->assertSame(0, preg_match(Rupiah::WHOLE_PRICE_PATTERN, $price));
        }
    }

    public function test_input_cannot_overflow_the_integer_calculation(): void
    {
        $this->expectException(OverflowException::class);
        Rupiah::minorUnits('9999999999999999999999.99');
    }
}
