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
}
