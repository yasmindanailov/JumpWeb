<?php

namespace Tests\Feature\Site;

use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\SiteLocales;
use Database\Seeders\LandingContentSeeder;
use Dom\Element;
use Dom\HTMLDocument;
use Dom\XPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * **EL PIE DEL MARCO** (`DECISIONES #522`, carril de diseño Fase 3 · T3a·2, `[DECIDIDO owner,
 * 2026-09-11]`).
 *
 * El pie de `Marco Portada PJP` (1e) y `Layout Paginas PJP` (1a · 1b): cinco filas sobre TINTA
 * —la tira, los destinos del inventario, el contacto con el idioma, lo legal y el colofón—.
 *
 * ⚠️⚠️ **Lo esperado va ESCRITO en los casos, nunca leído de la fuente que se vigila.** La guarda
 * gemela del menú (`#521`) nació calculando lo esperado desde `SiteDestinations::PAGES` y dejaba
 * sobrevivir a dos mutaciones: comparaba la constante consigo misma.
 * ⚠️ Y todo se lee **acotado al `<footer>`**: el menú ofrece los mismos destinos, así que buscar en
 * la página entera pasaría en verde con el pie vacío.
 */
class FooterFrameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
        Cache::flush();
    }

    /**
     * **Los destinos del pie son el inventario + la cuenta, en ese orden; y solo en la portada, sus
     * secciones detrás** (`#521`/`#522`). Una interior no tiene secciones a las que bajar.
     */
    public function test_the_footer_offers_the_inventory_the_account_and_only_on_the_home_its_sections(): void
    {
        $paginas = ['/precios', '/cumpleanos', '/atracciones', '/normas', '/servicios', '/contacto'];
        $cuenta = (string) parse_url(route('account'), PHP_URL_PATH);

        $this->assertSame(
            [...$paginas, $cuenta],
            $this->footerLinks('/normas'),
            'el pie de una interior no ofrece exactamente el inventario y la cuenta, en ese orden',
        );

        $this->assertSame(
            [...$paginas, $cuenta, '/#zones', '/#rides', '/#before', '/#info', '/#faq'],
            $this->footerLinks('/'),
            'el pie de la portada no ofrece el inventario, la cuenta y sus secciones, en ese orden',
        );
    }

    /**
     * **El idioma está A LA VISTA, con enlaces de verdad y fuera de `<noscript>`** (`#522`, revierte
     * `#253`). Son la única forma de cambiar de idioma sin JavaScript.
     * ⚠️ El nombre accesible es el nombre NATIVO y tiene que EMPEZAR por el código visible («EN» →
     * «English»): lo exige «label in name» (WCAG 2.5.3) para quien maneja la web por voz.
     */
    public function test_the_languages_are_visible_links_with_a_native_name_and_the_current_one_marked(): void
    {
        $xpath = $this->xpath($this->get('/precios')->assertOk()->getContent());
        $enlaces = $xpath->query('//h:footer//*[contains(concat(" ", normalize-space(@class), " "), " foot__langs ")]//h:a');

        $this->assertSame(count(SiteLocales::SUPPORTED), $enlaces->length, 'no hay un enlace por idioma en el pie');

        $actual = [];
        /** @var Element $a */
        foreach ($enlaces as $a) {
            $codigo = trim($a->textContent);
            $nombre = $a->getAttribute('aria-label');

            $this->assertNull($a->closest('noscript'), "«{$codigo}» vive dentro de un `<noscript>`: con JS no se vería");
            $this->assertStringContainsString('/lang/', $a->getAttribute('href'));
            $this->assertSame(strtolower($codigo), $a->getAttribute('hreflang'));
            $this->assertStringStartsWith(
                mb_strtolower($codigo), mb_strtolower($nombre),
                "el nombre accesible «{$nombre}» no empieza por el rótulo visible «{$codigo}» (WCAG 2.5.3)",
            );
            if ($a->getAttribute('aria-current') === 'true') {
                $actual[] = $codigo;
            }
        }

        $this->assertSame([strtoupper(app()->getLocale())], $actual, 'el idioma en curso no va marcado (o va marcado otro)');
    }

    /**
     * **El pie es una banda de TINTA a sangre, y la columna la pone su interior** (`#522`).
     * ⚠️ Con `.wrap` en el propio `<footer>`, el fondo que pinta `[data-surface]` se quedaría en la
     * columna y el papel asomaría a los lados.
     */
    public function test_the_footer_is_a_full_bleed_ink_band(): void
    {
        $xpath = $this->xpath($this->get('/contacto')->assertOk()->getContent());
        $pie = $xpath->query('//h:footer')->item(0);

        $this->assertNotNull($pie);
        $this->assertSame('ink', $pie->getAttribute('data-surface'), 'el pie no declara la superficie de tinta');
        $this->assertStringNotContainsString('wrap', $pie->getAttribute('class'), 'el pie a sangre lleva `.wrap`: el fondo se quedaría en la columna');
        $this->assertSame(
            1, $xpath->query('./*[contains(concat(" ", normalize-space(@class), " "), " wrap ")]', $pie)->length,
            'la columna del pie no la pone su interior',
        );
    }

    /**
     * **El colofón es «Nombre · Ciudad» y «© año», y no lleva el lema ni la coletilla** (`#522`,
     * `[DECIDIDO owner]`). Los dos textos siguen vivos en otros sitios (el `<title>` de la portada y
     * el pie del formulario de invitados), así que este caso fija que NO vuelvan al pie de la web.
     */
    public function test_the_colophon_is_name_city_and_year_without_the_tagline_or_the_rights(): void
    {
        Setting::updateOrCreate(['key' => 'business.name'], ['value' => 'Parque Sonda', 'group' => 'business']);
        Setting::updateOrCreate(['key' => 'business.city'], ['value' => 'Villasonda', 'group' => 'business']);
        Setting::updateOrCreate(['key' => 'landing.tagline.es'], ['value' => 'LEMA DE SONDA', 'group' => 'landing']);
        Setting::updateOrCreate(['key' => 'landing.footer_rights.es'], ['value' => 'COLETILLA DE SONDA', 'group' => 'landing']);

        $xpath = $this->xpath($this->get('/normas')->assertOk()->getContent());
        $colofon = $xpath->query('//h:footer//*[contains(concat(" ", normalize-space(@class), " "), " foot__colophon ")]/h:span');

        $this->assertSame(2, $colofon->length, 'el colofón no son dos piezas');
        $this->assertSame('Parque Sonda · Villasonda', trim($colofon->item(0)->textContent));
        $this->assertSame('© '.date('Y'), trim($colofon->item(1)->textContent));

        $pie = (string) $xpath->query('//h:footer')->item(0)?->textContent;
        $this->assertStringNotContainsString('LEMA DE SONDA', $pie, 'el lema volvió al pie');
        $this->assertStringNotContainsString('COLETILLA DE SONDA', $pie, 'la coletilla volvió al pie');
    }

    /** Sin ciudad escrita, el colofón no deja un «·» colgando. */
    public function test_without_a_city_the_colophon_does_not_leave_a_dangling_separator(): void
    {
        Setting::updateOrCreate(['key' => 'business.name'], ['value' => 'Parque Sonda', 'group' => 'business']);
        Setting::updateOrCreate(['key' => 'business.city'], ['value' => '', 'group' => 'business']);

        $xpath = $this->xpath($this->get('/normas')->assertOk()->getContent());
        $nombre = $xpath->query('//h:footer//*[contains(concat(" ", normalize-space(@class), " "), " foot__colophon ")]/h:span')->item(0);

        $this->assertSame('Parque Sonda', trim((string) $nombre?->textContent));
    }

    /**
     * **La vela se apaga sola cuando no hay nada que deslizar** (`#522`).
     *
     * ⚠️⚠️ Con los destinos del inventario, en escritorio la fila CABE, y la vela —un degradado encima
     * del último destino— lo tapaba sin nada detrás. Sin desbordamiento la línea de tiempo está
     * inactiva y la animación no se aplica, así que el CSS no puede saberlo solo: el HECHO lo publica
     * `rail-sails.js` (`data-rail-scroll`) y sin él la vela no se pinta. Con él y sin soporte de líneas
     * de tiempo la vela se queda puesta, que es el estado seguro mientras la fila desborda.
     */
    public function test_the_edge_fade_follows_its_own_row_and_is_off_when_the_row_fits(): void
    {
        $css = (string) file_get_contents(base_path('public/css/landing.css'));

        // (1) Sin la marca que publica `rail-sails.js` —la fila CABE, o no hay JS—, la vela no se pinta:
        // taparía el último destino sin nada detrás, que es lo normal en escritorio con el inventario.
        $this->assertMatchesRegularExpression(
            '/\.foot__links-wrap:not\(\[data-rail-scroll\]\)::after,\s*\.foot__legal-wrap:not\(\[data-rail-scroll\]\)::after\s*\{[^}]*opacity:\s*0/',
            $css, 'sin desbordamiento la vela del pie se sigue pintando',
        );

        // (2) La línea de tiempo lleva NOMBRE, la declara la FILA y el ENVOLTORIO la sube.
        foreach (['links' => '--pie-destinos', 'legal' => '--pie-legal'] as $fila => $nombre) {
            $n = preg_quote($nombre, '/');
            $this->assertMatchesRegularExpression("/\\.foot__{$fila}-wrap\\s*\\{\\s*timeline-scope:\\s*{$n}\\s*;/", $css, "el envoltorio de `{$fila}` no sube la línea de tiempo de su fila");
            $this->assertMatchesRegularExpression("/\\.foot__{$fila}\\s*\\{\\s*scroll-timeline-name:\\s*{$n}\\s*;\\s*scroll-timeline-axis:\\s*inline/", $css, "la fila `{$fila}` no declara su eje con nombre");
            $this->assertMatchesRegularExpression("/\\.foot__{$fila}-wrap\\[data-rail-scroll\\]::after\\s*\\{\\s*animation-timeline:\\s*{$n}\\s*;/", $css, "la vela de `{$fila}` no cuelga de la línea de su fila");
        }

        // (3) ❗ EL DEFECTO REAL: ninguna vela del pie vuelve a la línea ANÓNIMA. `scroll(nearest …)` en el
        // `::after` del envoltorio busca un ANTECESOR —el documento— y no la fila, que es su hermana:
        // la vela no se apagó nunca al llegar al final, y como estaba siempre visible nadie lo vio.
        $this->assertDoesNotMatchRegularExpression(
            '/\.foot__(links|legal)-wrap[^{]*::after[^{]*\{[^}]*scroll\(nearest/', $css,
            'una vela del pie vuelve a colgar de `scroll(nearest …)`: busca el documento, no su fila',
        );

        // (4) Y el hecho de si desborda lo publica `rail-sails.js` para las DOS filas del pie.
        $js = (string) file_get_contents(resource_path('js/ui/rail-sails.js'));
        $this->assertStringContainsString("['.foot__links-wrap', '.foot__links']", $js);
        $this->assertStringContainsString("['.foot__legal-wrap', '.foot__legal']", $js);
    }

    /**
     * **En escritorio el contacto y lo legal comparten FILA** (`#522`, `[DECIDIDO owner, 2026-09-11]`).
     *
     * ⚠️⚠️ Se aparta del canvas —que las dibuja en dos filas— por un motivo MEDIDO: con dos filas el
     * pie crecía ~25 px en escritorio y el punto estático del cierre de la portada dejaba de caber a
     * 1440×900. Sin esta regla todo sigue pintándose y **nada falla**: solo la tarjeta del cierre vuelve
     * a pisar el pie. La geometría la mide la sonda de navegador; esto fija que la regla exista.
     */
    public function test_on_desktop_the_contact_and_the_legal_strip_share_a_row(): void
    {
        $css = (string) file_get_contents(base_path('public/css/landing.css'));

        preg_match('/@media \(min-width: 1024px\) \{\s*\.foot__inner \{([^}]*)\}(.*?)\n\}/s', $css, $bloque);
        $this->assertNotEmpty($bloque, 'no se encuentra el bloque de escritorio que junta contacto y legal en una fila');
        $this->assertMatchesRegularExpression('/display:\s*flex/', $bloque[1]);
        $this->assertMatchesRegularExpression('/flex-wrap:\s*wrap/', $bloque[1]);
        $this->assertMatchesRegularExpression('/\.foot__contact\s*\{[^}]*flex:\s*0 0 auto/', $bloque[2], 'el contacto no toma solo su ancho');
        $this->assertMatchesRegularExpression('/\.foot__legal-wrap\s*\{[^}]*flex:\s*1 1 0/', $bloque[2], 'lo legal no ocupa el resto de la fila');
        $this->assertMatchesRegularExpression('/\.foot__colophon\s*\{\s*flex:\s*0 0 100%/', $bloque[2], 'el colofón no baja a su propia fila');
    }

    // ─────────────────────────────────────────────────────────────────────────────────

    /** Los destinos (`ruta#ancla`) de la tira de destinos del pie, en orden. */
    private function footerLinks(string $path): array
    {
        $xpath = $this->xpath($this->get($path)->assertOk()->getContent());
        $enlaces = $xpath->query('//h:footer//*[contains(concat(" ", normalize-space(@class), " "), " foot__links ")]//h:a[@href]');

        $this->assertGreaterThan(0, $enlaces->length, "CONTROL: el pie de `{$path}` no tiene tira de destinos");

        $out = [];
        /** @var Element $a */
        foreach ($enlaces as $a) {
            $partes = parse_url($a->getAttribute('href'));
            $out[] = ($partes['path'] ?? '/').(isset($partes['fragment']) ? '#'.$partes['fragment'] : '');
        }

        return $out;
    }

    private function xpath(string $html): XPath
    {
        $xpath = new XPath(HTMLDocument::createFromString($html, LIBXML_NOERROR));
        $xpath->registerNamespace('h', 'http://www.w3.org/1999/xhtml');

        return $xpath;
    }
}
