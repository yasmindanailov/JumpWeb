<?php

namespace Tests\Feature\Cookies;

use App\Domain\Content\Models\Page;
use App\Domain\Content\Services\CookiePolicyContent;
use App\Domain\Platform\Models\Setting;
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

    // ⚠️ Aquí vivía `test_reviewed_cookie_page_hides_the_draft_notice`: el aviso de borrador y su lista
    // `REVIEWED_LEGAL_SLUGS` se retiraron en `#655` (regla muerta: las cinco legales eran definitivas desde
    // el 2026-09-01 y el aviso no se pintaba nunca). Un caso que afirma la ausencia de algo que no existe
    // pasa en verde sin mirar nada.

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

    /**
     * `#592` — **La política dice para qué es el permiso**: la categoría del mapa enseña también las
     * reseñas de Google y la foto de quien las escribe.
     */
    public function test_policy_names_google_reviews_in_the_map_category(): void
    {
        $this->seed(LandingContentSeeder::class);

        $this->withSession(['locale' => 'es'])->get('/cookies')->assertOk()->assertSee('Mapa y reseñas (Google)');
        $this->withSession(['locale' => 'en'])->get('/cookies')->assertOk()->assertSee('Map and reviews (Google)');
        $this->withSession(['locale' => 'fr'])->get('/cookies')->assertOk()->assertSee('Carte et avis (Google)');
    }

    /**
     * `#592` — **La migración nombra las reseñas en una BD ya sembrada, solo donde el texto sigue
     * siendo el del producto**, deja lo que la clienta reescribió, es idempotente y deja EXACTAMENTE
     * lo que sembraría una instalación nueva.
     */
    public function test_reviews_migration_rewrites_only_untouched_paragraphs(): void
    {
        $nuevo = collect(CookiePolicyContent::body()['es']);
        $mapaNuevo = $nuevo->firstWhere('h', 'Mapa y reseñas (Google)');
        $transferenciasNuevo = $nuevo->firstWhere('h', 'Transferencias internacionales de datos');
        $this->assertNotNull($mapaNuevo, 'la política ya no tiene la sección del mapa y las reseñas: el caso miraría el vacío');

        $mapaViejo = ['h' => 'Mapa de ubicación (Google Maps)', 'p' => 'En la página de inicio y en la de contacto mostramos un mapa de Google Maps, pero solo se carga si das tu consentimiento a la categoría «mapa». Al cargarlo, Google LLC puede instalar cookies propias en tu navegador con finalidades de funcionamiento, seguridad y, en su caso, medición. Mientras no lo autorices, verás un aviso en lugar del mapa y no se instala ninguna cookie de Google.'];
        $transferenciasViejo = ['h' => $transferenciasNuevo['h'], 'p' => str_replace(
            ['Si activas el mapa y las reseñas o el contenido', 'Google LLC (mapa y reseñas)'],
            ['Si activas el mapa o el contenido', 'Google LLC (mapa)'],
            $transferenciasNuevo['p'],
        )];
        $deLaClienta = ['h' => 'Carte (Google Maps)', 'p' => 'Texte réécrit par la cliente.'];

        $page = Page::create([
            'slug' => 'cookies',
            'title' => CookiePolicyContent::title(),
            'body' => ['es' => [$mapaViejo, $transferenciasViejo], 'fr' => [$deLaClienta]],
            'is_active' => true,
        ]);

        $migration = require database_path('migrations/2026_09_13_140000_cookie_policy_names_google_reviews.php');
        $migration->up();
        $page->refresh();

        $this->assertSame($mapaNuevo, $page->body['es'][0], 'el párrafo del mapa no queda como lo siembra una instalación nueva');
        $this->assertSame($transferenciasNuevo, $page->body['es'][1], 'el párrafo de transferencias no nombra las reseñas');
        $this->assertSame($deLaClienta, $page->body['fr'][0], 'la migración reescribe un texto que editó la clienta');

        $tras = $page->body;
        $migration->up();
        $page->refresh();
        $this->assertSame($tras, $page->body, 'la migración no es idempotente');
    }
}
