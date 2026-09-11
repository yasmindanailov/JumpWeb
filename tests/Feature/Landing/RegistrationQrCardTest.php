<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\QrCode;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Card «Regístrate antes de venir» (QR + CTA): en `/precios` SOLO con registro EXTERNO
 * (`registration.url`). QR y CTA apuntan a la MISMA URL; el QR es SVG inline server-side.
 *
 * ⚠️ **Desde `#531` es siempre una BANDA**: el modo inline colgaba de la rejilla de tarjetas de
 * entrada, que se fue al rehacer la página desde su artboard. Ver `<x-site.registration-qr>`.
 *
 * ⚠️⚠️ **DESDE `#479` ESTA CARD YA NO ESTÁ EN LA PORTADA, y por eso todos los casos miran
 * `/precios`.** El rediseño de la sección 02 desde el canvas la retira de la home
 * (`[DECIDIDO owner, 2026-09-09]`): allí el registro es la sección 05 «Antes de venir», que dice
 * *«el registro ES el QR»* y la trata entera. La card vive **solo** en la página de tarifas.
 * ▶ Los casos NO se han borrado: se han re-apuntado. La card sigue teniendo pantalla y sus reglas
 * —inline contra banda, la URL compartida, el rechazo de un esquema no http— siguen valiendo. Lo
 * que cambió es dónde se pinta.
 */
class RegistrationQrCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    private function setRegistrationUrl(?string $url): void
    {
        if ($url === null) {
            Setting::where('key', 'registration.url')->delete();

            return;
        }

        Setting::updateOrCreate(['key' => 'registration.url'], ['value' => $url, 'group' => 'business']);
    }

    /** Deja como mucho `$keep` entradas por zona (para forzar el modo inline). */
    private function trimEntriesPerZoneTo(int $keep): void
    {
        TicketType::where('type', TicketType::TYPE_ENTRY)->get()
            ->groupBy('zone_id')
            ->each(fn ($group) => $group->skip($keep)->each->delete());
    }

    public function test_card_shows_on_pricing_with_external_registration_url(): void
    {
        $this->setRegistrationUrl('https://registro.example.test/alta');

        $res = $this->get('/precios');

        $res->assertOk();
        $res->assertSee('reg-cta', false);      // el CTA de la card (presente en inline y en banda)
        $res->assertSee('qr-slot', false);      // el QR inline
        $res->assertSee('https://registro.example.test/alta', false); // CTA + destino del QR
        $res->assertSee(__('landing.registration.title'));
    }

    public function test_card_hidden_on_pricing_without_external_registration_url(): void
    {
        $this->setRegistrationUrl(null); // registro INTERNO (modal): sin URL externa

        $res = $this->get('/precios');

        $res->assertOk();
        $res->assertDontSee('reg-cta', false);
        $res->assertDontSee(__('landing.registration.title'));
    }

    public function test_card_shows_on_pricing_page(): void
    {
        $this->setRegistrationUrl('https://registro.example.test/alta');

        $this->get('/precios')
            ->assertOk()
            ->assertSee('reg-cta', false)
            ->assertSee('https://registro.example.test/alta', false);
    }

    /**
     * ❗❗ **LA CARD ES SIEMPRE UNA BANDA DESDE `#531`, y aquí vivían sus DOS modos.**
     *
     * El modo INLINE existía porque la página pintaba una rejilla de tarjetas de precio y la card
     * entraba como una columna más cuando la zona dejaba hueco (≤3 entradas). `/precios` se rehizo
     * desde su artboard —dos tablas, sin rejilla de tarjetas—, así que **ese hueco ya no existe**:
     * el modo inline se fue con su sujeto, y con él la clase, su CSS y el prop que lo encendía.
     * ▶ Lo que sí sigue siendo cierto se asevera aquí: la card se pinta **una sola vez**, como banda,
     * y NO depende de la zona activa —la página ya no tiene zona activa—.
     */
    public function test_card_is_a_single_band_and_no_longer_depends_on_a_zone(): void
    {
        $this->setRegistrationUrl('https://registro.example.test/alta');

        $html = (string) $this->get('/precios')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'class="regcard"'), 'la card de registro se pinta una sola vez');
        $this->assertStringNotContainsString('regcard--inline', $html);
        $this->assertStringNotContainsString('includes(priceZone)', $html);
    }

    public function test_cta_and_qr_target_the_same_url(): void
    {
        $this->setRegistrationUrl('https://registro.example.test/alta');

        $res = $this->get('/precios');

        // El CTA es un enlace real (funciona sin escanear) y el QR se genera de la MISMA URL.
        $res->assertSee('<a class="reg-cta" href="https://registro.example.test/alta"', false);
        $res->assertSee('<svg', false);
    }

    public function test_qr_helper_returns_inline_svg_without_leaking_the_url_as_text(): void
    {
        $svg = QrCode::svg('https://registro.example.test/alta');

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('<path', $svg);
        // El SVG son solo trazados: la URL NO aparece como texto → seguro con `{!! !!}`.
        $this->assertStringNotContainsString('registro.example.test', $svg);
    }

    public function test_non_http_registration_url_is_rejected_and_card_hidden(): void
    {
        // safeExternalUrl bloquea esquemas no http(s) → registration_url = null → card oculta.
        $this->setRegistrationUrl('javascript:alert(1)');

        $this->get('/precios')->assertOk()->assertDontSee('reg-cta', false);
    }
}
