<?php

namespace Tests\Feature\Landing;

use App\Domain\Platform\Models\Setting;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * **`/contacto`: EL MAPA SE PUEDE CARGAR Y EL PARKING NO ESTÁ EN EL CÓDIGO** (auditoría de diseño
 * M4 y M5, `[DECIDIDO owner, 2026-09-03]`, `DECISIONES #434`).
 *
 * La tarjeta «Ubicación» iba POSADA sobre el marco del mapa y tapaba al 100 % el botón «Cargar el
 * mapa» y su enlace a la política de cookies (medido con `elementFromPoint()` a 1440 y a 390): en
 * escritorio el mapa no se podía cargar. Ahora va DEBAJO del mapa, como pegatina, y aquí se vigila
 * la CAUSA —que no vuelva a anidarse dentro de `.map-card`— porque una prueba estática no puede
 * medir un solape.
 *
 * Y «Parking gratis 2h» era un dato de negocio escrito en el código (`landing.info.parking`):
 * `#297`/`#307` lo retiraron de la portada y `VisitSectionTest` lo vigilaba SOLO allí; en contacto
 * sobrevivía. `[DECIDIDO owner]`: fuera (la FAQ del panel ya lo dice).
 */
class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        foreach ([
            'address.line1' => 'Ctra. de Prueba, 1',
            'address.line2' => '30000 Ciudad',
            'address.maps_url' => 'https://maps.app.goo.gl/prueba',
            'address.maps_embed_url' => 'https://www.google.com/maps/embed?pb=PRUEBA123',
        ] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'contact']);
        }
    }

    public function test_the_address_card_is_not_inside_the_map_card(): void
    {
        $xpath = $this->xpath();

        $tarjetas = $xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' contact-where ')]");
        $this->assertSame(1, $tarjetas->length, 'la tarjeta de la dirección (`.contact-where`) no está en `/contacto`');

        $anidadas = $xpath->query(
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' map-card ')]"
            ."//*[contains(concat(' ', normalize-space(@class), ' '), ' contact-where ')]",
        );
        $this->assertSame(
            0,
            $anidadas->length,
            'la tarjeta de la dirección vuelve a estar DENTRO de la tarjeta del mapa: posada sobre el marco '.
            'tapa el botón «Cargar el mapa» (auditoría M4). Va debajo, como pegatina.',
        );

        // Y dentro del mapa no queda nada posado por encima del marco de consentimiento.
        $posados = $xpath->query(
            "//*[contains(concat(' ', normalize-space(@class), ' '), ' map-card ')]//*[contains(@style, 'z-index')]",
        );
        $this->assertSame(0, $posados->length, 'algo vuelve a posarse con `z-index` dentro de la tarjeta del mapa');
    }

    public function test_the_map_consent_button_and_the_address_are_both_served(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('consent-frame__ph-btn', $html, 'el botón «Cargar el mapa» no se sirve');
        $this->assertStringContainsString('Ctra. de Prueba, 1', $html, 'la dirección no se pinta');
        $this->assertStringContainsString('https://maps.app.goo.gl/prueba', $html, '«Cómo llegar» no enlaza al mapa');
    }

    public function test_parking_is_gone_from_the_page_and_from_the_copy(): void
    {
        $this->assertStringNotContainsStringIgnoringCase('parking', $this->html(), '«Parking gratis 2h» vuelve a estar en el código (auditoría M5)');

        foreach (['es', 'en', 'fr'] as $locale) {
            $copy = (string) file_get_contents(base_path("lang/{$locale}/landing.php"));
            $this->assertDoesNotMatchRegularExpression(
                "/'parking'\s*=>/",
                $copy,
                "`lang/{$locale}/landing.php` vuelve a llevar la clave `parking`: es un dato de negocio y va en el panel o fuera.",
            );
        }
    }

    private function html(): string
    {
        return $this->get('/contacto')->assertOk()->getContent();
    }

    private function xpath(): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$this->html());
        libxml_clear_errors();

        return new DOMXPath($dom);
    }
}
