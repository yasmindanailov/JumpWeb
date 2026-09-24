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

    // ─── T3a de la analítica (`specs/analitica.md` §4.3): tres secciones nuevas ──────────────────

    /** La política declara lo exento (la audiencia propia) y explica las dos categorías nuevas, en los tres idiomas. */
    public function test_policy_declares_the_exempt_measurement_and_the_two_new_purposes(): void
    {
        $this->seed(LandingContentSeeder::class);

        foreach (['es', 'en', 'fr'] as $locale) {
            $this->withSession(['locale' => $locale])->get('/cookies')->assertOk()
                ->assertSee(CookiePolicyContent::AUDIENCE_H[$locale])
                ->assertSee('visitor_id')
                ->assertSee(CookiePolicyContent::ANALYTICS_H[$locale])
                ->assertSee(CookiePolicyContent::MARKETING_H[$locale])
                ->assertSee('Data Privacy Framework');
        }

        // El orden: lo exento y las dos categorías van detrás de las cookies técnicas, antes del mapa.
        $headings = array_column(CookiePolicyContent::body()['es'], 'h');
        $this->assertSame(
            ['Cookies técnicas y de seguridad (necesarias)', CookiePolicyContent::AUDIENCE_H['es'], CookiePolicyContent::ANALYTICS_H['es'], CookiePolicyContent::MARKETING_H['es'], 'Mapa y reseñas (Google)'],
            array_slice($headings, 2, 5),
        );
    }

    /**
     * La migración INSERTA las tres secciones detrás de las cookies técnicas y renombra el párrafo de
     * transferencias en una BD sembrada con la v2, solo donde el texto sigue siendo el del producto; deja lo
     * que la clienta reescribió; es idempotente; y deja EXACTAMENTE lo que sembraría una instalación nueva.
     */
    public function test_analytics_migration_inserts_the_sections_only_where_the_text_is_the_products(): void
    {
        $nuevo = CookiePolicyContent::body()['es'];
        $nuevoEn = CookiePolicyContent::body()['en'];
        // El párrafo de transferencias de la v2 (anterior a la T3a·1), reconstruido desde el sembrado de HOY (que
        // desde la T3b·3 ya nombra la herramienta y los anuncios «más abajo»).
        $transfersOld = str_replace(
            ['Si activas el mapa y las reseñas, el contenido de redes sociales, el análisis de uso identificado o la publicidad', 'Google LLC (mapa, reseñas y Google Ads)', 'La herramienta de análisis y las plataformas de anuncios activas en esta web se nombran más abajo con su empresa responsable y su garantía de transferencia. '],
            ['Si activas el mapa y las reseñas o el contenido de redes sociales', 'Google LLC (mapa y reseñas)', ''],
            CookiePolicyContent::TRANSFERS_P['es'],
        );
        $this->assertNotSame(CookiePolicyContent::TRANSFERS_P['es'], $transfersOld, 'el párrafo viejo tiene que ser distinto del nuevo');

        // La v2: el cuerpo nuevo SIN las tres secciones y con el párrafo viejo de transferencias.
        $v2 = array_map(
            static fn (array $s): array => $s['h'] === 'Transferencias internacionales de datos' ? ['h' => $s['h'], 'p' => $transfersOld] : $s,
            array_values(array_filter($nuevo, static fn (array $s): bool => ! in_array($s['h'], [CookiePolicyContent::AUDIENCE_H['es'], CookiePolicyContent::ANALYTICS_H['es'], CookiePolicyContent::MARKETING_H['es']], true))),
        );
        $this->assertCount(count($nuevo) - 3, $v2);

        // Y la clienta reescribió el inglés entero (ni ancla ni transferencias del producto).
        $deLaClienta = [['h' => 'Cookies', 'p' => 'Rewritten by the client.']];
        // El francés sembrado con la v2 pero YA con la sección de analítica puesta a mano: no se duplica.
        $frConAnalitica = [['h' => 'Cookies techniques et de sécurité (nécessaires)', 'p' => 'x'], ['h' => CookiePolicyContent::ANALYTICS_H['fr'], 'p' => 'y']];

        $page = Page::create([
            'slug' => 'cookies',
            'title' => CookiePolicyContent::title(),
            'body' => ['es' => $v2, 'en' => $deLaClienta, 'fr' => $frConAnalitica],
            'is_active' => true,
        ]);

        // ⚠️ ENCADENADAS: desde la T3b·3 el sembrado ya no es lo que deja la migración de la T3a·1 sola (que
        // insertaba el «[PENDIENTE]» que la T3b·3 quita). Una instalación vieja pasa por las dos.
        $migration = require database_path('migrations/2026_09_24_120000_cookie_policy_adds_analytics_and_marketing.php');
        $textos = require database_path('migrations/2026_09_24_160000_cookie_policy_names_the_active_advertisers_at_render.php');
        $migration->up();
        $textos->up();
        $page->refresh();

        $this->assertSame($nuevo, $page->body['es'], 'el español migrado no queda como lo siembra una instalación nueva');
        $this->assertSame($deLaClienta, $page->body['en'], 'la migración reescribe un texto que editó la clienta');
        $this->assertSame($frConAnalitica, $page->body['fr'], 'con una de las tres secciones ya puesta no se inserta nada');
        $this->assertNotSame($nuevoEn, $page->body['en']);

        $tras = $page->body;
        $migration->up();
        $textos->up();
        $page->refresh();
        $this->assertSame($tras, $page->body, 'la migración no es idempotente');
    }

    /**
     * T3b·3: la migración quita el «[PENDIENTE: asesoría]» de los párrafos de publicidad y de transferencias SOLO
     * donde siguen como los sembró la T3a·1 (una instalación desplegada con ella), respeta lo editado y es
     * idempotente; el «[PENDIENTE]» del feed social, anterior y ajeno, se conserva.
     */
    public function test_the_advertisers_migration_replaces_the_two_paragraphs_only_where_they_are_the_products(): void
    {
        $nuevo = CookiePolicyContent::body()['es'];
        // Lo que dejó la T3a·1: reconstruido desde el sembrado de hoy.
        $marketingT3a1 = str_replace('Las plataformas activas en esta web, con la empresa responsable y su garantía de transferencia, se nombran más abajo.', '[PENDIENTE: asesoría — nombrar a las plataformas activas como destinatarias y su garantía de transferencia].', CookiePolicyContent::MARKETING_P['es']);
        $transfersT3a1 = str_replace('La herramienta de análisis y las plataformas de anuncios activas en esta web se nombran más abajo con su empresa responsable y su garantía de transferencia. Respecto al proveedor del feed social, la garantía', 'Respecto al proveedor del feed social, a la herramienta de análisis y a las demás plataformas de anuncios, la garantía', CookiePolicyContent::TRANSFERS_P['es']);
        $this->assertNotSame(CookiePolicyContent::MARKETING_P['es'], $marketingT3a1);
        $this->assertNotSame(CookiePolicyContent::TRANSFERS_P['es'], $transfersT3a1);
        $this->assertStringContainsString('[PENDIENTE: asesoría', $marketingT3a1, 'CONTROL: el texto viejo lleva el marcador');

        $viejo = array_map(static fn (array $s): array => match ($s['h']) {
            CookiePolicyContent::MARKETING_H['es'] => ['h' => $s['h'], 'p' => $marketingT3a1],
            'Transferencias internacionales de datos' => ['h' => $s['h'], 'p' => $transfersT3a1],
            default => $s,
        }, $nuevo);
        $deLaClienta = [['h' => 'Cookies', 'p' => 'Rewritten by the client, [PENDING] included.']];

        $page = Page::create([
            'slug' => 'cookies',
            'title' => CookiePolicyContent::title(),
            'body' => ['es' => $viejo, 'en' => $deLaClienta, 'fr' => CookiePolicyContent::body()['fr']],
            'is_active' => true,
        ]);

        $migration = require database_path('migrations/2026_09_24_160000_cookie_policy_names_the_active_advertisers_at_render.php');
        $migration->up();
        $page->refresh();

        $this->assertSame($nuevo, $page->body['es'], 'el español migrado no queda como lo siembra una instalación nueva');
        $this->assertStringNotContainsString('[PENDIENTE: asesoría', json_encode($page->body['es'], JSON_UNESCAPED_UNICODE), 'el marcador de la analítica sigue a la vista');
        $this->assertStringContainsString('[PENDIENTE: confirmar adhesión', json_encode($page->body['es'], JSON_UNESCAPED_UNICODE), 'el del feed social no es de esta tanda y se conserva');
        $this->assertSame($deLaClienta, $page->body['en'], 'la migración reescribe un texto que editó la clienta');
        $this->assertSame(CookiePolicyContent::body()['fr'], $page->body['fr'], 'un texto ya nuevo no se toca');

        $tras = $page->body;
        $migration->up();
        $page->refresh();
        $this->assertSame($tras, $page->body, 'la migración no es idempotente');
    }
}
