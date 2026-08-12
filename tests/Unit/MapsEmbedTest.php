<?php

namespace Tests\Unit;

use App\Domain\Content\Services\MapsEmbed;
use PHPUnit\Framework\TestCase;

/**
 * #206 — `MapsEmbed::clean` extrae la URL de inserción limpia de Google Maps de lo que
 * pegue el operador (iframe completo, URL con atributos detrás, o URL sola). Servicio puro
 * → `tests/Unit` sin BD (CONVENCIONES §3.ter).
 */
class MapsEmbedTest extends TestCase
{
    private const URL = 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d25150!2sCasa!5e0!3m2!1ses!2ses';

    public function test_keeps_a_clean_embed_url(): void
    {
        $this->assertSame(self::URL, MapsEmbed::clean(self::URL));
    }

    public function test_extracts_src_from_full_iframe(): void
    {
        $iframe = '<iframe src="'.self::URL.'" width="600" height="450" style="border:0;" allowfullscreen loading="lazy"></iframe>';

        $this->assertSame(self::URL, MapsEmbed::clean($iframe));
    }

    public function test_strips_trailing_attributes_pasted_after_the_url(): void
    {
        // Escenario exacto reportado: pegan el src con los atributos del iframe detrás.
        $pasted = self::URL.'" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">';

        $this->assertSame(self::URL, MapsEmbed::clean($pasted));
    }

    public function test_rejects_non_google_maps_urls(): void
    {
        $this->assertNull(MapsEmbed::clean('https://evil.example.com/maps/embed'));
        $this->assertNull(MapsEmbed::clean('<iframe src="https://evil.example.com/x"></iframe>'));
        $this->assertNull(MapsEmbed::clean('javascript:alert(1)'));
        // Host-spoofing: tras "www.google.com" viene ".evil", no "/maps" → rechazado.
        $this->assertNull(MapsEmbed::clean('https://www.google.com.evil.com/maps/embed?pb=1'));
        $this->assertNull(MapsEmbed::clean('https://www.google.com@evil.com/maps/embed?pb=1'));
    }

    public function test_rejects_incomplete_embed_url_without_parameters(): void
    {
        // Sin `?…` carga un mapa roto → se rechaza.
        $this->assertNull(MapsEmbed::clean('https://www.google.com/maps/embed'));
        $this->assertNull(MapsEmbed::clean('https://www.google.com/maps/embed_fake'));
    }

    public function test_accepts_embed_v1_place_form(): void
    {
        $url = 'https://www.google.com/maps/embed/v1/place?key=ABC123&q=Murcia';
        $this->assertSame($url, MapsEmbed::clean($url));
    }

    public function test_rejects_text_before_a_bare_url(): void
    {
        // Texto pegado ANTES de una URL suelta (sin <iframe src=…>) no se reconoce.
        $this->assertNull(MapsEmbed::clean('mira esto https://www.google.com/maps/embed?pb=1'));
    }

    public function test_returns_null_for_empty(): void
    {
        $this->assertNull(MapsEmbed::clean(''));
        $this->assertNull(MapsEmbed::clean('   '));
        $this->assertNull(MapsEmbed::clean(null));
    }
}
