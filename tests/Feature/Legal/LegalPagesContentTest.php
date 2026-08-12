<?php

namespace Tests\Feature\Legal;

use App\Domain\Platform\Models\Setting;
use App\Models\Page;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #220 — Privacidad / Condiciones / Aviso legal redactadas (fuente única `LegalContent`): muestran
 * el contenido REAL (no el `[PENDIENTE]` antiguo), interpolan los datos fiscales, son trilingües,
 * ocultan el aviso de borrador y la migración de reparación es idempotente y respeta ediciones.
 */
class LegalPagesContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    public function test_privacy_has_real_content(): void
    {
        $response = $this->get('/privacidad')->assertOk();

        $response->assertDontSee('[PENDIENTE: razón social]');          // marcador del texto viejo
        $response->assertDontSee(__('site.legal_draft_notice'));        // sin aviso de borrador
        $response->assertSee('Redsys');
        $response->assertSee('AEPD');
        $response->assertSee('Bunny Fonts');                            // tercero que carga siempre (IP)
        $response->assertSee('art. 9.2.a');                             // base de los datos de salud
        $response->assertSee('Data Privacy Framework');
    }

    public function test_terms_has_real_content_aligned_with_refund_policy(): void
    {
        $response = $this->get('/condiciones')->assertOk();

        $response->assertDontSee('[PENDIENTE: política de cancelación y reembolso]');
        $response->assertDontSee(__('site.legal_draft_notice'));
        $response->assertSee('Redsys');
        $response->assertSee('reembolso parcial automático');           // política real (#150)
    }

    public function test_legal_notice_has_real_content(): void
    {
        $response = $this->get('/aviso-legal')->assertOk();

        $response->assertDontSee('jumpingjump.com');                    // dominio a pelo retirado
        $response->assertDontSee(__('site.legal_draft_notice'));
        $response->assertSee('34/2002');                                // LSSI-CE
    }

    public function test_fiscal_tokens_are_interpolated(): void
    {
        Setting::updateOrCreate(['key' => 'business.legal_name'], ['value' => 'Saltos del Mar SL', 'group' => 'business']);
        Setting::updateOrCreate(['key' => 'contact.email'], ['value' => 'datos@ejemplo.es', 'group' => 'contact']);

        $this->get('/privacidad')->assertOk()
            ->assertSee('Saltos del Mar SL')
            ->assertSee('datos@ejemplo.es')
            ->assertDontSee(':legal_name')
            ->assertDontSee(':legal_email');
    }

    public function test_extended_tokens_are_interpolated_from_settings(): void
    {
        // IVA, teléfono y dominio se rellenan solos desde Ajustes (#220).
        Setting::updateOrCreate(['key' => 'business.domain'], ['value' => 'saltopark.example', 'group' => 'business']);
        Setting::updateOrCreate(['key' => 'contact.phone'], ['value' => '900 123 456', 'group' => 'contact']);
        Setting::updateOrCreate(['key' => 'payment.tax_rate'], ['value' => '10', 'group' => 'payment']);

        $this->get('/condiciones')->assertOk()
            ->assertSee('10 %')                 // :tax_rate (IVA configurado)
            ->assertSee('900 123 456')          // :legal_phone
            ->assertDontSee(':tax_rate')
            ->assertDontSee(':legal_phone');

        $this->get('/aviso-legal')->assertOk()
            ->assertSee('saltopark.example')        // :site_domain (ajuste)
            ->assertSee('900 123 456')
            ->assertDontSee(':site_domain');
    }

    public function test_site_domain_falls_back_to_request_host_when_unset(): void
    {
        // Sin `business.domain` configurado, el dominio cae al host de la petición (en producción,
        // el dominio real automáticamente). En tests el host es «localhost».
        $this->get('/aviso-legal')->assertOk()
            ->assertSee('localhost')
            ->assertDontSee(':site_domain');
    }

    public function test_pages_are_localized(): void
    {
        $this->withSession(['locale' => 'en'])->get('/privacidad')->assertOk()->assertSee('Privacy policy');
        $this->withSession(['locale' => 'fr'])->get('/condiciones')->assertOk()->assertSee('Conditions');
        $this->withSession(['locale' => 'en'])->get('/aviso-legal')->assertOk()->assertSee('Legal notice');
    }

    public function test_repair_migration_idempotent_and_respects_edits(): void
    {
        // Forzar contenido legacy (la setUp ya sembró el nuevo): la página de privacidad vuelve a su
        // marcador antiguo, simulando una BD de dev/prod previa.
        $priv = Page::where('slug', 'privacidad')->firstOrFail();
        $priv->update(['body' => ['es' => [['h' => 'Responsable del tratamiento', 'p' => 'El responsable es [PENDIENTE: razón social], con NIF [PENDIENTE].']]]]);

        $migration = require database_path('migrations/2026_06_08_000003_refresh_legal_pages_content.php');

        // 1.ª pasada: reemplaza por el contenido real.
        $migration->up();
        $priv->refresh();
        $this->assertStringNotContainsString('PENDIENTE: razón social', (string) json_encode($priv->body));
        $this->assertStringContainsString(':legal_email', (string) json_encode($priv->body));

        // 2.ª pasada: idempotente.
        $after = $priv->body;
        $migration->up();
        $this->assertSame($after, $priv->refresh()->body);

        // Edición de la clienta → no se pisa.
        $priv->update(['body' => ['es' => [['h' => 'Mío', 'p' => 'Texto propio de la clienta.']]]]);
        $migration->up();
        $this->assertSame('Texto propio de la clienta.', $priv->refresh()->body['es'][0]['p']);
    }
}
