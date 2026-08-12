<?php

namespace Tests\Feature\Legal;

use App\Models\Setting;
use App\Support\LegalIdentity;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #206 — Las páginas legales (política de privacidad / aviso legal) interpolan los datos
 * fiscales del titular desde la configuración (`/admin/settings`): rellenarlos se refleja
 * en el texto legal, sustituyendo los marcadores `[PENDIENTE]` sembrados.
 */
class LegalIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function setFiscal(): void
    {
        foreach ([
            'business.legal_name' => 'Saltos de Murcia S.L.',
            'business.nif' => 'B12345678',
            'business.address' => 'Avenida de los Saltos 22, Murcia',
            'contact.email' => 'legal@saltopark.example',
        ] as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'business']);
        }
    }

    public function test_interpolates_legacy_spanish_literals(): void
    {
        $this->setFiscal();

        $out = LegalIdentity::interpolate(
            'El responsable es [PENDIENTE: razón social], con NIF [PENDIENTE] y domicilio en [PENDIENTE]. Puedes contactarnos en [PENDIENTE: email].'
        );

        $this->assertStringContainsString('Saltos de Murcia S.L.', $out);
        $this->assertStringContainsString('NIF B12345678', $out);
        $this->assertStringContainsString('domicilio en Avenida de los Saltos 22, Murcia', $out);
        $this->assertStringContainsString('legal@saltopark.example', $out);
        $this->assertStringNotContainsString('[PENDIENTE', $out);
    }

    public function test_interpolates_legacy_english_and_french_literals(): void
    {
        $this->setFiscal();

        $en = LegalIdentity::interpolate('The controller is [PENDING: legal name], tax ID [PENDING], registered at [PENDING]. Reach us at [PENDING: email].');
        $this->assertStringContainsString('tax ID B12345678', $en);
        $this->assertStringContainsString('registered at Avenida de los Saltos 22, Murcia', $en);
        $this->assertStringNotContainsString('[PENDING', $en);

        $fr = LegalIdentity::interpolate('Éditeur : [À COMPLÉTER : raison sociale], NIF [À COMPLÉTER], adresse [À COMPLÉTER], contact [À COMPLÉTER : email].');
        $this->assertStringContainsString('NIF B12345678', $fr);
        $this->assertStringContainsString('adresse Avenida de los Saltos 22, Murcia', $fr);
        $this->assertStringNotContainsString('À COMPLÉTER', $fr);
    }

    public function test_interpolates_clean_tokens(): void
    {
        $this->setFiscal();

        $this->assertSame(
            'Titular: Saltos de Murcia S.L., NIF B12345678, contacto legal@saltopark.example.',
            LegalIdentity::interpolate('Titular: :legal_name, NIF :legal_nif, contacto :legal_email.')
        );
    }

    public function test_interpolates_business_name_token_with_product_fallback(): void
    {
        // Fase 1 (DECISIONES #12): los textos legales nombran :business_name, nunca una marca quemada.
        $this->assertSame(
            'Eventos de '.config('app.name').'.',
            LegalIdentity::interpolate('Eventos de :business_name.')
        );

        Setting::updateOrCreate(['key' => 'business.name'], ['value' => 'SaltoPark', 'group' => 'business']);
        Setting::flushMemo();

        $this->assertSame('Eventos de SaltoPark.', LegalIdentity::interpolate('Eventos de :business_name.'));
    }

    public function test_falls_back_to_pendiente_when_fiscal_data_empty(): void
    {
        // NIF aún con el placeholder sembrado por defecto → muestra un [pendiente] neutro.
        Setting::updateOrCreate(['key' => 'business.nif'], ['value' => '[PENDIENTE]', 'group' => 'business']);

        $this->assertSame('NIF [pendiente].', LegalIdentity::interpolate('NIF :legal_nif.'));
    }

    public function test_privacy_page_shows_fiscal_data_from_settings(): void
    {
        // Escenario exacto reportado: la página legal mostraba el texto [PENDIENTE].
        $this->seed(LandingContentSeeder::class);
        $this->setFiscal();

        $response = $this->get('/privacidad');

        $response->assertOk();
        $response->assertSee('Saltos de Murcia S.L.');
        $response->assertSee('B12345678');
        $response->assertDontSee('[PENDIENTE: razón social]');
        $response->assertDontSee('[PENDIENTE]');
    }

    public function test_legal_notice_page_shows_fiscal_data_from_settings(): void
    {
        $this->seed(LandingContentSeeder::class);
        $this->setFiscal();

        $response = $this->get('/aviso-legal');

        $response->assertOk();
        $response->assertSee('Saltos de Murcia S.L.');
        // El marcador FISCAL se interpola; los `[PENDIENTE]` de negocio (p. ej. fuero) pueden quedar
        // legítimamente hasta que la clienta los decida (#220).
        $response->assertDontSee('[PENDIENTE: razón social]');
    }
}
