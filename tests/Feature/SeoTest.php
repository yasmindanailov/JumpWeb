<?php

namespace Tests\Feature;

use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\VenueRule;
use App\Domain\Platform\Models\Setting;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    public function test_sitemap_lists_public_urls(): void
    {
        $res = $this->get('/sitemap.xml');

        $res->assertOk();
        $res->assertHeader('Content-Type', 'application/xml');

        foreach (['servicios', 'precios', 'cumpleanos', 'contacto', 'normas', 'privacidad'] as $slug) {
            $res->assertSee(url('/'.$slug), false);
        }
    }

    public function test_pages_expose_canonical_and_og(): void
    {
        $this->get('/precios')
            ->assertOk()
            ->assertSee('rel="canonical"', false)
            ->assertSee('og:url', false)
            ->assertSee(url('/precios'), false);
    }

    public function test_pages_have_meta_description(): void
    {
        $this->get('/precios')
            ->assertOk()
            ->assertSee('name="description"', false);
    }

    public function test_sitemap_includes_priority_changefreq_and_lastmod(): void
    {
        $res = $this->get('/sitemap.xml')->assertOk();

        $res->assertSee('<priority>', false);
        $res->assertSee('<changefreq>', false);
        // El contenido sembrado tiene `updated_at` → al menos una página declara `lastmod`.
        $res->assertSee('<lastmod>', false);
    }

    public function test_home_exposes_business_and_faq_json_ld(): void
    {
        $res = $this->get('/')->assertOk();

        $res->assertSee('application/ld+json', false);
        $res->assertSee('"@type":"Organization"', false);
        $res->assertSee('"@type":"AmusementPark"', false);
        $res->assertSee('"@type":"FAQPage"', false);
    }

    public function test_local_business_json_ld_carries_address_from_settings(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('"@type":"PostalAddress"', $html);
        // address.line1 del fixture (LandingContentSeeder).
        $this->assertStringContainsString('Avenida de los Saltos, 22', $html);
    }

    public function test_all_json_ld_blocks_are_valid_json(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        $this->assertNotEmpty($matches[1], 'La home no emite ningún bloque JSON-LD');

        foreach ($matches[1] as $block) {
            json_decode($block, true);
            $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'JSON-LD inválido: '.$block);
        }
    }

    public function test_content_images_have_non_empty_alt(): void
    {
        // P2: las polaroids de la galería (home) y la foto editorial de /servicios ya no llevan
        // `alt=""` — son contenido real indexable, no decorativas.
        $this->get('/')->assertOk()->assertDontSee('alt=""', false);
        $this->get('/servicios')->assertOk()->assertDontSee('alt=""', false);
    }

    public function test_rules_page_meta_description_derives_from_rules(): void
    {
        app()->setLocale('es');
        VenueRule::query()->delete();
        VenueRule::create(['name' => ['es' => 'Calcetines obligatorios'], 'description' => ['es' => 'Antideslizantes.'], 'is_active' => true, 'position' => 1]);
        VenueRule::create(['name' => ['es' => 'Sin comida en la zona de salto'], 'description' => ['es' => 'Solo en la cafetería.'], 'is_active' => true, 'position' => 2]);

        $html = $this->get('/normas')->assertOk()->getContent();

        // La meta description resume las normas (unidas con ' · '), no repite el título.
        $this->assertMatchesRegularExpression(
            '/<meta name="description" content="Calcetines obligatorios · Sin comida en la zona de salto">/',
            $html
        );
    }

    public function test_legal_page_meta_description_derives_from_body_not_title(): void
    {
        app()->setLocale('es');
        Page::updateOrCreate(['slug' => 'privacidad'], [
            'title' => ['es' => 'Política de privacidad'],
            'body' => ['es' => [['h' => 'Quiénes somos', 'p' => 'Tratamos tus datos solo para gestionar tus reservas y responder a tus consultas.']]],
            'is_active' => true,
        ]);

        $html = $this->get('/privacidad')->assertOk()->getContent();

        // La meta description sale del primer párrafo del cuerpo, no del título.
        $this->assertStringContainsString('content="Tratamos tus datos solo para gestionar', $html);
        $this->assertStringNotContainsString('name="description" content="Política de privacidad"', $html);
    }

    public function test_og_image_setting_is_sanitized_through_safe_external_url(): void
    {
        // Auditoría Fase 1 · Sistema 6 · W8: `og_image` era la única URL externa editable que NO pasaba
        // por `safeExternalUrl` (asimetría con maps/instagram/tiktok/registration). Una URL http(s)
        // válida se emite; un esquema peligroso se descarta → la meta se omite (paridad de render).
        Setting::updateOrCreate(['key' => 'seo.og_image'], ['value' => 'https://cdn.example.com/og.jpg', 'group' => 'seo']);
        $this->get('/')
            ->assertOk()
            ->assertSee('property="og:image"', false)
            ->assertSee('content="https://cdn.example.com/og.jpg"', false);

        // Esquema peligroso → `safeExternalUrl` lo descarta (null) → cae a la imagen por defecto del
        // sitio (og-image.jpg). La meta og:image ahora SIEMPRE está, pero el valor malicioso NUNCA se
        // emite (el saneado W8 se mantiene; lo crítico es el `assertDontSee` del javascript:).
        Setting::updateOrCreate(['key' => 'seo.og_image'], ['value' => 'javascript:alert(1)', 'group' => 'seo']);
        $this->get('/')
            ->assertOk()
            ->assertSee('property="og:image"', false)       // siempre presente (con default local)
            ->assertSee('og-image.jpg', false)              // cae a la imagen por defecto del sitio
            ->assertDontSee('javascript:alert(1)', false);  // el valor peligroso NO se emite (saneado)
    }

    /**
     * **Las tres PUERTAS de auth no se indexan nunca** (`specs/auth-en-cajon.md` §1.3).
     *
     * ⚠️⚠️ **Esto es conducta viva que NADIE aseveraba, y se escribe ANTES de tocar la auth a
     * propósito.** Hoy el `noindex` de `/login`, `/registro` y `/recuperar-contrasena` es un efecto
     * lateral del prop `authModal` del layout —el mismo que decide si se pinta el modal—, así que
     * retirar el modal se llevaría por delante el `<meta robots>` **sin que nada fallara**: tres URLs
     * de auth entrando en el índice de Google en silencio.
     *
     * ▶ Es la familia de `DECISIONES #89`/`#93`: *un dato que viaja al cliente cuyo único test conduce
     * la superficie vieja no está fijado por nadie*. Con este caso, la conducta queda fijada por lo
     * que el cliente RECIBE y no por quién se lo pone, que es lo que permite cambiar el mecanismo sin
     * perderla.
     *
     * ⚠️ El **control negativo** va en el mismo caso y no es adorno: sin él, un layout que emitiera
     * `noindex` en TODA la web pasaría este test con matrícula (`CONVENCIONES §3.quater`: un valor
     * que no distingue no fija nada).
     */
    public function test_the_auth_doors_are_never_indexable(): void
    {
        // ⚠️ Las tres primeras lo llevaban ya; las dos últimas **NO** (medido el 2026-08-23:
        // `index, follow`), y una de ellas es una URL con un TOKEN de restablecimiento dentro. El
        // `noindex` de auth venía del prop `authModal`, que estas dos páginas ponen a `null`, así que
        // se quedaban fuera por el mismo efecto lateral que lo daba a las otras.
        $surfaces = [
            '/login', '/registro', '/recuperar-contrasena',
            '/restablecer-contrasena/token-de-prueba', '/email/verificar',
        ];

        foreach ($surfaces as $surface) {
            $this->get($surface)
                ->assertOk()
                ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        }

        // Control negativo: la home SÍ se indexa. Si esto cae, el caso de arriba dejó de medir nada.
        $this->get('/')
            ->assertOk()
            ->assertSee('<meta name="robots" content="index, follow">', false);
    }

    /** Y la otra mitad de «no indexable»: ninguna de las tres se anuncia en el sitemap. */
    public function test_the_auth_doors_are_not_advertised_in_the_sitemap(): void
    {
        $res = $this->get('/sitemap.xml')->assertOk();

        foreach (['login', 'registro', 'recuperar-contrasena'] as $slug) {
            $res->assertDontSee('<loc>'.url('/'.$slug).'</loc>', false);
        }
    }
}
