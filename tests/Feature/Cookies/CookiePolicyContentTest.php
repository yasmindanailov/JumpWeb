<?php

namespace Tests\Feature\Cookies;

use App\Domain\Platform\Models\Setting;
use App\Models\Page;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #219 — La política de cookies (2.ª capa) muestra el inventario REAL (no el marcador `[PENDIENTE]`),
 * con los proveedores y las transferencias internacionales; es trilingüe; y la migración de
 * reparación es idempotente y respeta las ediciones de la clienta.
 */
class CookiePolicyContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_policy_has_real_content_not_placeholder(): void
    {
        $this->seed(LandingContentSeeder::class);

        $response = $this->get('/cookies')->assertOk();

        $response->assertDontSee('[PENDIENTE: listado detallado');
        $response->assertDontSee('[PENDING: detailed list');
        $response->assertDontSee('[À COMPLÉTER : liste détaillée');

        // Proveedores reales + transferencias.
        $response->assertSee('Google');
        $response->assertSee('Cloudflare');
        $response->assertSee('Redsys');
        $response->assertSee('Data Privacy Framework');
    }

    public function test_reviewed_cookie_page_hides_the_draft_notice(): void
    {
        $this->seed(LandingContentSeeder::class);

        // Cookies tiene contenido definitivo → NO muestra el aviso de borrador.
        $this->get('/cookies')->assertOk()->assertDontSee(__('site.legal_draft_notice'));

        // Una legal aún en borrador (fuera de REVIEWED_LEGAL_SLUGS) sí lo muestra.
        $this->assertNotContains('waiver', Page::REVIEWED_LEGAL_SLUGS);
        $this->get('/waiver')->assertOk()->assertSee(__('site.legal_draft_notice'));
    }

    public function test_policy_interpolates_fiscal_data(): void
    {
        $this->seed(LandingContentSeeder::class);
        Setting::updateOrCreate(['key' => 'business.legal_name'], ['value' => 'Saltos del Mar SL', 'group' => 'business']);

        $this->get('/cookies')->assertOk()->assertSee('Saltos del Mar SL');
    }

    public function test_policy_is_localized(): void
    {
        $this->seed(LandingContentSeeder::class);

        $this->withSession(['locale' => 'es'])->get('/cookies')->assertOk()->assertSee('¿Qué son las cookies?');
        $this->withSession(['locale' => 'en'])->get('/cookies')->assertOk()->assertSee('What are cookies?');
        $this->withSession(['locale' => 'fr'])->get('/cookies')->assertOk()->assertSee('Que sont les cookies');
    }

    public function test_repair_migration_replaces_placeholder_idempotently_and_respects_edits(): void
    {
        // BD «legacy»: la página de cookies sigue con el listado `[PENDIENTE]`.
        $page = Page::create([
            'slug' => 'cookies',
            'title' => ['es' => 'Política de cookies', 'en' => 'Cookie policy', 'fr' => 'Politique de cookies'],
            'body' => ['es' => [['h' => 'Cookies que utilizamos', 'p' => 'Técnicas y analíticas. [PENDIENTE: listado detallado de cookies y proveedores].']]],
            'is_active' => true,
        ]);

        $migration = require database_path('migrations/2026_06_08_000002_refresh_cookie_policy_content.php');

        // 1.ª pasada: reemplaza el marcador por el contenido real.
        $migration->up();
        $page->refresh();
        $this->assertStringNotContainsString('[PENDIENTE: listado detallado', (string) json_encode($page->body));
        $this->assertStringContainsString('Google', (string) json_encode($page->body));

        // 2.ª pasada: idempotente (ya no hay marcador → no toca nada).
        $bodyAfterFirst = $page->body;
        $migration->up();
        $page->refresh();
        $this->assertSame($bodyAfterFirst, $page->body);

        // Edición de la clienta (sin marcador) → la migración NO la pisa.
        $page->body = ['es' => [['h' => 'Mi sección', 'p' => 'Texto propio de la clienta.']]];
        $page->save();
        $migration->up();
        $page->refresh();
        $this->assertSame('Texto propio de la clienta.', $page->body['es'][0]['p']);
    }
}
