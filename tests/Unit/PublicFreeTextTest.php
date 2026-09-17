<?php

namespace Tests\Unit;

use App\Domain\Platform\Services\PublicFreeText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * **El texto libre que se publica bajo el dominio del parque** (`PublicFreeText`, T4·2 de
 * `docs/specs/celebracion-e-invitacion.md` §4.5·12; `SEC-07`, §7.2·R9; `DECISIONES #574`).
 *
 * La revisión adversarial lo dijo con un ejemplo que basta: **una invitación podía decir «paga el
 * regalo en este enlace»**, con la credibilidad del parque detrás y servida desde su dominio.
 *
 * Value object PURO → `tests/Unit` (`CONVENCIONES §3.ter`).
 */
class PublicFreeTextTest extends TestCase
{
    /** @return list<array{0: string}> */
    public static function enlaces(): array
    {
        return [
            ['Paga el regalo en https://cobro.example/lucia'],
            ['mira http://algo.test'],
            ['escríbeme a mama@example.com'],
            ['entra en www.regalos-lucia.com'],
            ['el sitio es regaloslucia.com y ya'],
            // Sin `://`, que es lo que `safeExternalUrl()` exige y por lo que no sirve para esto.
            ['javascript:alert(1)'],
            ['data:text/html;base64,AAAA'],
        ];
    }

    #[DataProvider('enlaces')]
    public function test_a_link_or_an_email_is_refused(string $value): void
    {
        $this->assertTrue(PublicFreeText::hasLink($value), "«{$value}» tenía que rechazarse");
        $this->assertNull(PublicFreeText::clean($value, 80));
    }

    /**
     * ⚠️ Y lo que NO puede rechazar: los nombres reales de la gente. Un filtro que tumbe «Ana S.A.» o
     * «Mª José» convierte una defensa en un defecto — y lo pagaría un anfitrión que no entiende por
     * qué su fiesta no se puede compartir.
     *
     * @return list<array{0: string}>
     */
    public static function nombresReales(): array
    {
        return [
            ['Lucía'],
            ['Te invita Marta'],
            ['Mª José Pérez-Gil'],
            ['Ana S.A'],
            ['Cumple de Hugo (8 años)'],
            ['Fiesta 2026'],
            ['Juan & Ana'],
        ];
    }

    #[DataProvider('nombresReales')]
    public function test_a_real_name_is_admitted_untouched(string $value): void
    {
        $this->assertFalse(PublicFreeText::hasLink($value), "«{$value}» NO tenía que rechazarse");
        $this->assertSame($value, PublicFreeText::clean($value, 80));
    }

    public function test_spacing_is_collapsed_and_the_text_is_cut_to_its_column(): void
    {
        $this->assertSame('Te invita Marta', PublicFreeText::clean("  Te   invita\n Marta \t", 80));
        $this->assertSame(10, mb_strlen((string) PublicFreeText::clean(str_repeat('a', 300), 10)));
    }

    /** Vacío no es un texto: quien llama tiene que poder distinguirlo y pedirlo. */
    public function test_an_empty_text_is_not_a_text(): void
    {
        $this->assertNull(PublicFreeText::clean('   ', 80));
        $this->assertNull(PublicFreeText::clean(null, 80));
    }
}
