<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\QrCode;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Card «Regístrate antes de venir» (QR + CTA): bajo la sección de entradas SOLO con registro EXTERNO
 * (`registration.url`). QR y CTA apuntan a la MISMA URL; el QR es SVG inline server-side. Entra como
 * COLUMNA del grid (≤3 entradas en la zona) o como BANDA debajo (≥4). Ver `<x-site.registration-qr>`.
 *
 * ⚠️⚠️ **DESDE `#479` ESTA CARD YA NO ESTÁ EN LA PORTADA, y por eso todos los casos miran
 * `/precios`.** El rediseño de la sección 02 desde el canvas la retira de la home
 * (`[DECIDIDO owner, 2026-09-09]`): allí el registro es la sección 05 «Antes de venir», que dice
 * *«el registro ES el QR»* y la trata entera. Hasta que esa sección exista, la card vive **solo** en
 * la página de tarifas, que sigue usando `<x-site.ticket-prices>`.
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

    public function test_card_is_inline_column_when_the_zone_has_room(): void
    {
        // ≤3 entradas por zona → la card entra como COLUMNA del grid (alineada con las entradas).
        $this->trimEntriesPerZoneTo(2);
        $this->setRegistrationUrl('https://registro.example.test/alta');

        $this->get('/precios')->assertOk()->assertSee('regcard--inline', false);
    }

    public function test_card_is_band_when_the_grid_is_full(): void
    {
        // El fixture siembra 4 entradas por zona → grid lleno → la card va como BANDA debajo (no inline),
        // visible por zona activa (`includes(priceZone)`).
        $this->setRegistrationUrl('https://registro.example.test/alta');

        $res = $this->get('/precios');

        $res->assertOk();
        $res->assertDontSee('regcard--inline', false);
        $res->assertSee('includes(priceZone)', false); // la banda se muestra según la zona activa
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
