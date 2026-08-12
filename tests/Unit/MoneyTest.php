<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

/**
 * Test PURO (sin BD, sin bootstrap de Laravel): {@see Money} solo envuelve number_format.
 * Estrena la convención `tests/Unit` para servicios puros (auditoría de organización:
 * tests/Unit estaba vacío). Su trabajo es PINEAR que el formateo centralizado es
 * BYTE-IDÉNTICO al `number_format($c/100,2,',','.')` que vivía duplicado en ~10 vistas,
 * de modo que redirigir las closures locales a Money no cambie ni un carácter del render.
 */
class MoneyTest extends TestCase
{
    public function test_format_matches_the_legacy_inline_pattern_exactly(): void
    {
        foreach ([0, 1, 99, 100, 1200, 2990, 123456, 9999999, 30070] as $cents) {
            // Contrato heredado: number_format(cents/100, 2, ',', '.') + ' €'.
            $expected = number_format($cents / 100, 2, ',', '.').' €';
            $this->assertSame($expected, Money::format($cents), "format($cents)");
        }
    }

    public function test_format_renders_spanish_separators_and_euro_symbol(): void
    {
        $this->assertSame('0,00 €', Money::format(0));
        $this->assertSame('12,00 €', Money::format(1200));
        $this->assertSame('29,90 €', Money::format(2990));
        $this->assertSame('1.234,56 €', Money::format(123456));
    }

    public function test_amount_omits_the_currency_suffix(): void
    {
        $this->assertSame('12,00', Money::amount(1200));
        $this->assertSame('1.234,56', Money::amount(123456));
        $this->assertSame('0,00', Money::amount(0));
    }

    public function test_non_eur_currency_shows_iso_code_after_the_number(): void
    {
        // Replica el criterio dinámico de order-totals / payments-list:
        // EUR → '€', cualquier otra → su código.
        $this->assertSame('12,00 USD', Money::format(1200, 'USD'));
        $this->assertSame('€', Money::symbol('EUR'));
        $this->assertSame('GBP', Money::symbol('GBP'));
    }
}
