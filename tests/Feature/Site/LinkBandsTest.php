<?php

namespace Tests\Feature\Site;

use App\Domain\Content\Services\LinkBands;
use App\Domain\Content\Services\SiteDestinations;
use App\Domain\Platform\Models\Setting;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * **LAS BANDAS DE ENLACE: UNA SOLA FAMILIA PARA LO QUE HABÍA CUATRO VECES**
 * (carril de diseño Fase 3 · artboard `Bandas PJP` turno 1 · `doc/bandas.md`).
 *
 * ❗❗❗ **Esta guarda existe porque el defecto no era que faltara una pieza: era que sobraban.**
 * Medido antes de construirla, CUATRO de las seis páginas interiores acababan con un enlace a otro
 * destino escrito a mano, cada uno con su familia de clases y su talla de texto:
 *
 *     /atracciones  .page--rides .page__foot-link  → /#zones      --fs-body (clamp 16→17)
 *     /precios      .rate-page__birthdays-link     → /cumpleanos  --fs-body
 *     /bar          .bar-party__cta                → /cumpleanos  --fs-17
 *     /contacto     .where__cta                    → /#info       (dentro de su tarjeta)
 *
 * Tres tallas para la misma línea, y `/precios` y `/bar` ofreciendo **el mismo destino con el mismo
 * rótulo**. Es la forma de defecto de `#538` (8 alturas de botón), `#196` (53 sombras, 42 formas) y
 * los badges de la tanda A: nadie rompe un sistema de golpe, y **no lo veía ninguna guarda** — de
 * los cuatro bloques solo dos se mencionaban en un test, y eran tests *de su página*.
 *
 * ▶ Por eso la mitad de los casos de abajo vigila la EROSIÓN: que el reparto salga del inventario,
 * que nadie vuelva a escribir un cierre propio y que los topes del canvas —una gorda, dos finas—
 * no se relajen «solo un poco».
 */
class LinkBandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
        Cache::flush();
        Setting::flushMemo();
        $this->publishBar();
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El instrumento, antes que lo que mide
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Guarda de la guarda.** Media docena de casos de abajo comprueban AUSENCIAS —que una página
     * no lleve gorda, que un destino apagado no se ofrezca—, y comprobar que algo no está es la
     * forma más fácil de escribir un test que no mira nada.
     */
    public function test_the_scan_reads_a_page_with_both_pieces(): void
    {
        $html = $this->page('atracciones');

        $this->assertStringContainsString('class="bands"', $html, 'la pieza no se pinta o cambió de clase raíz');
        $this->assertStringContainsString('class="band-wide"', $html, 'no hay banda gorda que medir');
        $this->assertStringContainsString('class="band-thin"', $html, 'no hay franjas finas que medir');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  La familia es UNA
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Ninguna página interior vuelve a escribir su propio cierre de navegación.**
     *
     * ⚠️ Es un TRINQUETE, y la lista solo puede encoger: cada entrada es una salida a medida que se
     * retiró, y el modo de fallo es que alguien añada la quinta porque «esta página necesita un
     * enlace al final». Lo necesita, y la pieza que lo da es `<x-site.link-bands />`.
     */
    public function test_no_page_writes_its_own_exit_any_more(): void
    {
        $retiradas = ['page__foot-link', 'rate-page__birthdays', 'bar-party__cta'];

        foreach (array_keys(LinkBands::THIN) as $ruta) {
            $html = $this->page($ruta);

            foreach ($retiradas as $clase) {
                $this->assertStringNotContainsString(
                    $clase, $html,
                    "`/{$ruta}` emite `{$clase}`: es un cierre de página a medida, y de ésos había ".
                    'cuatro con cuatro pieles. Los destinos los ofrece `<x-site.link-bands />`.',
                );
            }
        }
    }

    /**
     * **La gorda declara su superficie EN LA TARJETA, no en un contenedor** (`#484`).
     *
     * `[data-surface]` no solo re-escopa los tokens: además PINTA el fondo. En el contenedor dejaría
     * un rectángulo oscuro detrás de la tarjeta, sin radio y sin que nada fallara.
     */
    public function test_the_wide_band_declares_its_surface_on_the_card(): void
    {
        $html = $this->page('atracciones');

        $this->assertStringContainsString(
            '<div class="band-wide" data-surface="ink">', $html,
            'la gorda ha perdido su superficie o la ha subido a su contenedor',
        );
        $this->assertStringNotContainsString(
            '<div class="bands" data-surface', $html,
            'la superficie ha subido al contenedor: pintaría un rectángulo sin radio detrás de la tarjeta',
        );
    }

    /**
     * **Ninguna de las dos piezas escribe la ruta del destino** (`#586`, `[DECIDIDO owner]`).
     *
     * Hasta entonces las rotulaba la ruta escrita («/normas»), y el owner la retiró por jerga. La fina
     * sigue diciendo qué hay allí («Cuánto cuesta saltar»).
     */
    public function test_neither_piece_writes_the_destination_route(): void
    {
        $html = $this->page('atracciones');

        $this->assertStringNotContainsString('band-wide__route', $html, 'la gorda vuelve a rotular con la ruta');
        $this->assertStringNotContainsString('band-thin__route', $html, 'la fina vuelve a rotular con la ruta');
        $this->assertStringNotContainsString('>/normas<', $html);
        $this->assertStringContainsString(e(__('site.bands.go.precios.what')), $html, 'la fina ya no dice qué hay en su destino');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Los topes del canvas
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Como mucho UNA gorda y DOS finas por página.**
     *
     * Los dos números son del canvas y tienen motivo escrito: de la gorda, *«dos ya son un menú, y
     * el menú existe»*; de las finas, *«tres es el menú otra vez»*.
     */
    public function test_a_page_never_offers_more_than_one_wide_and_two_thin(): void
    {
        foreach (array_keys(LinkBands::THIN) as $ruta) {
            $html = $this->page($ruta);

            $this->assertLessThanOrEqual(
                1, substr_count($html, 'class="band-wide"'),
                "`/{$ruta}` pinta más de una banda gorda: dos ya son un menú",
            );
            $this->assertLessThanOrEqual(
                2, substr_count($html, 'class="band-thin"'),
                "`/{$ruta}` pinta más de dos franjas finas: tres es el menú otra vez",
            );
        }
    }

    /** **Ninguna banda lleva a la página en la que ya estás.** */
    public function test_no_band_points_at_its_own_page(): void
    {
        foreach (LinkBands::WIDE as $origen => $destino) {
            $this->assertNotSame($destino, $origen, "la gorda de `/{$origen}` lleva a `/{$origen}`");
        }

        foreach (LinkBands::THIN as $origen => $destinos) {
            $this->assertNotContains($origen, $destinos, "una fina de `/{$origen}` lleva a `/{$origen}`");
        }
    }

    /**
     * **Las dos páginas SIN gorda, con su motivo.**
     *
     * `/contacto` por el canvas —*«su acción es el formulario y una banda grande al lado compite con
     * ella»*— y `/bar` por `#536`, que es `[DECIDIDO owner]`: cero relleno de acción en esa página.
     * ⚠️⚠️ Y el segundo no se puede cumplir «con cuidado»: desde `#541` `--action` y `--secondary`
     * valen el MISMO cian sobre tinta, así que un botón relleno dentro de la gorda no tiene forma de
     * decir «esto no es comprar».
     */
    public function test_contact_and_bar_carry_no_wide_band(): void
    {
        foreach (['contacto', 'bar'] as $ruta) {
            $this->assertArrayNotHasKey($ruta, LinkBands::WIDE, "`/{$ruta}` ha ganado una gorda");
            $this->assertNull(LinkBands::wide($ruta));
            $this->assertStringNotContainsString('class="band-wide"', $this->page($ruta));
        }

        // Y el otro lado: las dos siguen ofreciendo sus finas, que no llevan relleno.
        foreach (['contacto', 'bar'] as $ruta) {
            $this->assertStringContainsString('class="band-thin"', $this->page($ruta));
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El reparto sale del INVENTARIO
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Un destino en mantenimiento RETIRA la pieza, no la sustituye.**
     *
     * Es la regla que la pieza hereda gratis por leer `SiteDestinations::pages()` en vez de componer
     * sus propias URL: *«un enlace que no está en el inventario es relleno»* (`#521`). Sin ella, la
     * gorda de `/atracciones` llevaría a una pantalla de «vuelve luego».
     *
     * ⚠️ **Retira, no sustituye**: la pregunta y su destino son lo mismo —«¿puede subir tu hijo?» la
     * contesta `/normas` y nadie más—, así que poner otro destino contestaría otra cosa.
     */
    public function test_a_destination_in_maintenance_removes_the_band_instead_of_swapping_it(): void
    {
        $this->assertNotNull(LinkBands::wide('atracciones'), 'el caso nace sin sujeto');

        $this->apagar('normas');

        $this->assertNull(
            LinkBands::wide('atracciones'),
            'la gorda sigue ofreciendo `/normas` con la página en mantenimiento: lleva a «vuelve luego»',
        );

        // Y una FINA cuyo destino se apaga se cae ella sola, sin que la otra se pierda.
        $finas = LinkBands::thin('precios');
        $this->assertCount(1, $finas, 'la fina a `/normas` no se ha retirado, o se ha llevado a su hermana');
        $this->assertStringContainsString(route('atracciones'), $finas[0]['url']);
    }

    /**
     * **El bar sin nombre no se ofrece, y eso alcanza a la gorda de `/cumpleanos`.**
     *
     * `#536`: sin nombre en el panel la ruta de `/bar` responde **404**, y un destino que lleva a un
     * 404 es peor que no tenerlo. La pieza no lo sabe ni tiene que saberlo: lo sabe el inventario.
     */
    public function test_the_unpublished_bar_takes_the_birthday_wide_band_with_it(): void
    {
        $this->assertNotNull(LinkBands::wide('cumpleanos'), 'el caso nace sin sujeto');

        Setting::where('key', 'like', 'bar.name.%')->delete();
        Cache::flush();
        Setting::flushMemo();

        $this->assertNull(
            LinkBands::wide('cumpleanos'),
            'la gorda de `/cumpleanos` sigue ofreciendo el bar con la página sin publicar: es un 404',
        );
    }

    /**
     * **Sin nada que ofrecer no se pinta NADA**, ni el contenedor ni el rótulo «Sigue por aquí».
     *
     * El canvas: *«si una página no deja ninguna pregunta abierta, no lleva gorda — no se rellena el
     * hueco»*. Un contenedor vacío costaría el aire de una sección debajo de nada, que es el defecto
     * que `#488` fichó para tres secciones de la portada.
     */
    public function test_with_nothing_to_offer_the_piece_paints_nothing(): void
    {
        foreach (['precios', 'normas'] as $destino) {
            $this->apagar($destino);
        }
        Setting::where('key', 'like', 'bar.name.%')->delete();
        Cache::flush();
        Setting::flushMemo();

        // `/servicios` se queda sin gorda (su destino sigue vivo, así que se apaga también) y sin finas.
        $this->apagar('contacto');

        $html = $this->page('atracciones');

        $this->assertNull(LinkBands::wide('atracciones'));
        $this->assertSame([], LinkBands::thin('atracciones'), 'quedan finas: el caso no monta el vacío');
        $this->assertStringNotContainsString('class="bands"', $html, 'se pinta el contenedor vacío');
        $this->assertStringNotContainsString(__('site.bands.lead'), $html, 'se pinta el rótulo sin lista');
    }

    /**
     * **La pieza no cuesta ni una consulta.**
     *
     * Su docblock afirma que `SiteDestinations::pages()` solo lee `settings`, que están memoizados
     * para toda la petición — y por eso no memoiza nada por su cuenta, que entre peticiones de un
     * mismo proceso de test congelaría el mantenimiento de una prueba en la siguiente.
     * ⚠️ Se mide con la página YA cargada una vez: la primera visita paga el catálogo entero, que no
     * es de esta pieza.
     */
    public function test_the_piece_costs_no_queries(): void
    {
        $this->page('atracciones');

        $antes = 0;
        DB::listen(function () use (&$antes) {
            $antes++;
        });

        LinkBands::wide('atracciones');
        LinkBands::thin('atracciones');

        $this->assertSame(0, $antes, "la pieza lanza {$antes} consultas: corre en las siete páginas");
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  El diccionario
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Cada pieza del reparto tiene su texto en los TRES idiomas.**
     *
     * ⚠️⚠️ `Lang::has()` **cae al idioma de respaldo** si no se le pasa el locale: una clave que
     * falte en francés pero esté en español pasaría en verde. Es la trampa que `#506` pagó, y por eso
     * el tercer parámetro va a `false`.
     * ⚠️ Sin esto el fallo es MUDO: `__()` devuelve la clave, así que la gorda de una página
     * rotularía literalmente «site.bands.ask.normas.q» — que es lo que le pasó a `#504` en los tres
     * idiomas a la vez.
     */
    public function test_every_band_has_its_text_in_the_three_locales(): void
    {
        $claves = [];

        foreach (array_keys(LinkBands::WIDE) as $origen) {
            $claves[] = "site.bands.ask.{$origen}.q";
            $claves[] = "site.bands.ask.{$origen}.body";
        }

        foreach (array_unique(array_merge(array_values(LinkBands::WIDE), ...array_values(LinkBands::THIN))) as $destino) {
            $claves[] = "site.bands.go.{$destino}.cta";
            $claves[] = "site.bands.go.{$destino}.what";
        }

        $claves[] = 'site.bands.lead';

        foreach (['es', 'en', 'fr'] as $locale) {
            foreach ($claves as $clave) {
                $this->assertTrue(
                    Lang::has($clave, $locale, false),
                    "falta `{$clave}` en `{$locale}`: la banda se rotularía con la clave en crudo",
                );
            }
        }
    }

    /**
     * **Todo destino del reparto está en el inventario de páginas.**
     *
     * ⚠️ El canvas lo escribe así: *«ningún destino inventa página: todos salen del inventario»*. Un
     * destino que no esté ahí no fallaría — `offered()` no lo encontraría y la pieza no se pintaría
     * nunca, o sea que la fila estaría muerta y en verde.
     */
    public function test_every_destination_of_the_map_exists_in_the_inventory(): void
    {
        $inventario = array_keys(SiteDestinations::PAGES);

        foreach (LinkBands::WIDE as $origen => $destino) {
            $this->assertContains($destino, $inventario, "la gorda de `/{$origen}` apunta fuera del inventario");
        }

        foreach (LinkBands::THIN as $origen => $destinos) {
            foreach ($destinos as $destino) {
                $this->assertContains($destino, $inventario, "una fina de `/{$origen}` apunta fuera del inventario");
            }
            $this->assertLessThanOrEqual(2, count($destinos), "`/{$origen}` declara más de dos finas");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Ayudas
    // ─────────────────────────────────────────────────────────────────────────────────

    private function page(string $ruta): string
    {
        return $this->get(route($ruta))->assertOk()->getContent();
    }

    private function publishBar(): void
    {
        Setting::updateOrCreate(['key' => 'bar.name.es'], ['value' => 'El bar de prueba', 'group' => 'bar']);
        Cache::flush();
        Setting::flushMemo();
    }

    private function apagar(string $pagina): void
    {
        Setting::updateOrCreate(
            ['key' => 'maintenance.page.'.$pagina],
            ['value' => '1', 'group' => 'maintenance'],
        );
        Cache::flush();
        Setting::flushMemo();
    }
}
