<?php

namespace Tests\Feature\Site;

use App\Domain\Content\Services\StructuredData;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El pie no puede ofrecer un contacto que la instalación no tiene (`DECISIONES #307`).
 *
 * Encontrado al cargar los datos reales del segundo cliente: sin redes configuradas el pie servía
 * `<a href="#">Instagram</a>` y lo mismo TikTok, en TODA instalación. El menú
 * (`components/site/menu.blade.php`) y el `sameAs` de schema.org ({@see StructuredData})
 * ya comprobaban el centinela `'#'` que el composer usa para «sin configurar»; el pie era el único
 * de los TRES consumidores que no. Con datos de demostración —que traían URLs de un parque
 * inventado— el defecto era invisible.
 *
 * Y el mismo caso con el TELÉFONO: el pie leía `phone` y lo saneaba con un `preg_replace` propio,
 * saltándose el `has_phone`/`phone_tel` que el composer centralizó justamente para no emitir un
 * `tel:` roto a un ajuste sin rellenar.
 *
 * ⚠️ Las aserciones se acotan al ELEMENTO (`<nav class="foot__links">`) y no a la página: la home
 * está llena de anclas `href="#…"` legítimas, así que buscar por subcadena en todo el HTML daría un
 * verde que no vigila nada — la trampa que `#295` pagó con una guarda re-apuntada.
 */
class FooterContactLinksTest extends TestCase
{
    use RefreshDatabase;

    private function set(string $key, string $value, string $group = 'contact'): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
    }

    /** El `<nav>` de enlaces del pie, aislado del resto del documento. */
    private function footerNav(): string
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<nav class="foot__links"[^>]*>/',
            $html,
            'El pie ya no emite `<nav class="foot__links">`: esta guarda se quedó sin sujeto.',
        );

        preg_match('#<nav class="foot__links".*?</nav>#s', $html, $m);

        return $m[0];
    }

    public function test_sin_redes_configuradas_el_pie_no_ofrece_ninguna(): void
    {
        $this->set('contact.instagram', '', 'social');
        $this->set('contact.tiktok', '', 'social');

        $nav = $this->footerNav();

        $this->assertStringNotContainsString('Instagram', $nav);
        $this->assertStringNotContainsString('TikTok', $nav);
        $this->assertStringNotContainsString('href="#"', $nav);
    }

    public function test_una_red_con_esquema_peligroso_tampoco_se_ofrece(): void
    {
        // `safeExternalUrl` la convierte en el centinela `'#'` en el composer; el pie tiene que
        // leerlo como «sin configurar», no pintar un enlace muerto.
        $this->set('contact.instagram', 'javascript:alert(1)', 'social');
        $this->set('contact.tiktok', '', 'social');

        $nav = $this->footerNav();

        $this->assertStringNotContainsString('javascript:', $nav);
        $this->assertStringNotContainsString('Instagram', $nav);
        $this->assertStringNotContainsString('href="#"', $nav);
    }

    public function test_con_redes_configuradas_el_pie_las_ofrece(): void
    {
        $this->set('contact.instagram', 'https://instagram.com/playjumppark', 'social');
        $this->set('contact.tiktok', 'https://tiktok.com/@playjumppark', 'social');

        $nav = $this->footerNav();

        $this->assertStringContainsString('https://instagram.com/playjumppark', $nav);
        $this->assertStringContainsString('https://tiktok.com/@playjumppark', $nav);
        // Salen a un tercero: se abren fuera y sin filtrar el referente.
        $this->assertStringContainsString('rel="noopener noreferrer"', $nav);
    }

    public function test_un_telefono_sin_rellenar_no_emite_un_tel_roto(): void
    {
        // Es el valor que siembra `ProductionSeeder` mientras la instalación no lo cambia.
        $this->set('contact.phone', '[PENDIENTE]');

        $nav = $this->footerNav();

        $this->assertStringNotContainsString('tel:', $nav);
        $this->assertStringNotContainsString('[PENDIENTE]', $nav);
    }

    public function test_un_telefono_real_se_emite_marcable(): void
    {
        $this->set('contact.phone', '+34 641 99 57 14');

        $nav = $this->footerNav();

        // El `href` va sin espacios; el texto visible los conserva.
        $this->assertStringContainsString('tel:+34641995714', $nav);
        $this->assertStringContainsString('+34 641 99 57 14', $nav);
    }
}
