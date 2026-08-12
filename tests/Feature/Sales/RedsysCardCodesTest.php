<?php

namespace Tests\Feature\Sales;

use App\Domain\Payments\Services\RedsysCardCodes;
use PHPUnit\Framework\TestCase;

/**
 * Sub-fase 7.2a refinada (#130) — Helper para traducir códigos Redsys
 * `Ds_Card_Brand` y `Ds_Card_Country` a etiquetas legibles en el panel admin.
 *
 * No requiere base de datos: el mapeo está en código (catálogo Redsys estable).
 */
class RedsysCardCodesTest extends TestCase
{
    // ─── brand ────────────────────────────────────────────────────────────

    public function test_brand_translates_known_codes(): void
    {
        $this->assertSame('Visa', RedsysCardCodes::brand('1'));
        $this->assertSame('Mastercard', RedsysCardCodes::brand('2'));
        $this->assertSame('Discover', RedsysCardCodes::brand('6'));
        $this->assertSame('Diners', RedsysCardCodes::brand('7'));
        $this->assertSame('American Express', RedsysCardCodes::brand('8'));
        $this->assertSame('JCB', RedsysCardCodes::brand('9'));
    }

    public function test_brand_returns_null_for_unknown_code(): void
    {
        $this->assertNull(RedsysCardCodes::brand('999'));
        $this->assertNull(RedsysCardCodes::brand('foo'));
    }

    public function test_brand_returns_null_for_empty_or_null_input(): void
    {
        $this->assertNull(RedsysCardCodes::brand(null));
        $this->assertNull(RedsysCardCodes::brand(''));
    }

    // ─── country ──────────────────────────────────────────────────────────

    public function test_country_translates_spain(): void
    {
        $this->assertSame('España', RedsysCardCodes::country('724'));
    }

    public function test_country_translates_eu_neighbours(): void
    {
        $this->assertSame('Francia', RedsysCardCodes::country('250'));
        $this->assertSame('Portugal', RedsysCardCodes::country('620'));
        $this->assertSame('Italia', RedsysCardCodes::country('380'));
        $this->assertSame('Alemania', RedsysCardCodes::country('276'));
    }

    public function test_country_normalizes_padding(): void
    {
        // Algunos terminales devuelven sin padding ('40' en vez de '040').
        $this->assertSame('Austria', RedsysCardCodes::country('40'));
        $this->assertSame('Austria', RedsysCardCodes::country('040'));
        $this->assertSame('Bélgica', RedsysCardCodes::country('56'));
        $this->assertSame('Bélgica', RedsysCardCodes::country('056'));
    }

    public function test_country_returns_null_for_unknown_code(): void
    {
        // Algún código ISO-3166 numérico no mapeado (Antártida, p. ej.).
        $this->assertNull(RedsysCardCodes::country('010'));
        $this->assertNull(RedsysCardCodes::country('foo'));
    }

    public function test_country_returns_null_for_empty_or_null_input(): void
    {
        $this->assertNull(RedsysCardCodes::country(null));
        $this->assertNull(RedsysCardCodes::country(''));
    }
}
