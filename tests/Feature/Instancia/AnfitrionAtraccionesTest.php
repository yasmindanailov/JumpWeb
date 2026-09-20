<?php

namespace Tests\Feature\Instancia;

use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Models\Attraction;
use App\Domain\Platform\Models\Setting;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * **El ANFITRIÓN MÍNIMO de `/atracciones`** — lo que el producto sirve sin paquete de instancia (F5 · T2b,
 * `DECISIONES #657`). Es marcado del PRODUCTO, y por eso aquí SÍ se mira el HTML (`#649`): tiene que pintar
 * TODO lo que el contrato de vista le da —una pestaña y un panel por zona, la cifra con su paleta compuesta,
 * una ficha por atracción con su foto, su qué-es y sus dos chips, y la nota de acceso del parque—.
 *
 * ⚠️⚠️ **Y tiene que conservar los tres sitios donde el marcado dice CUÁL es una zona** (`id` de panel, `id`
 * de pestaña y su `aria-controls`), porque `ZoneIdentityIsUniqueTest` vigila ahí un defecto real —tres zonas
 * con el mismo acento abriendo a la vez— y esa guarda ya se quedó dos veces sin sujeto al retirarse la pieza
 * que miraba (`#295`, `#482`). Si alguien simplifica este anfitrión, que sea sabiendo eso.
 *
 * ⚠️ Sin arte a propósito: ni fachada ni pegatina de zona. El diseño es de la instancia.
 */
class AnfitrionAtraccionesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LandingContentSeeder::class);
        $this->withSession(['locale' => 'es']);
        app()->setLocale('es');
    }

    private function html(string $query = ''): string
    {
        return (string) $this->get('/atracciones'.$query)->assertOk()->assertViewIs('anfitrion.atracciones')->getContent();
    }

    /** @return Collection<int, Zone> Las zonas que viajan, en su orden. */
    private function zonas(): Collection
    {
        return $this->get('/atracciones')->assertOk()->original->getData()['zones'];
    }

    public function test_it_paints_a_tab_and_a_panel_for_every_zone_that_travels(): void
    {
        $html = $this->html();
        $zonas = $this->zonas();

        $this->assertGreaterThan(1, $zonas->count(), 'sin dos zonas este caso no distingue nada');

        foreach ($zonas as $zona) {
            $this->assertStringContainsString('id="atracciones-'.$zona->slug.'"', $html, "la zona «{$zona->slug}» no tiene panel");
            $this->assertStringContainsString('id="rides-tab-'.$zona->slug.'"', $html, "la zona «{$zona->slug}» no tiene pestaña");
            $this->assertStringContainsString('aria-controls="atracciones-'.$zona->slug.'"', $html, "la pestaña de «{$zona->slug}» no controla su panel");
        }

        $this->assertSame($zonas->count(), substr_count($html, 'class="tabset__tab"'));
    }

    /** ⚠️ Con una sola zona no se pinta la pestaña: un control de una opción no elige nada. */
    public function test_a_single_zone_gets_no_tablist(): void
    {
        $zonas = $this->zonas();
        $this->assertGreaterThan(1, $zonas->count(), 'el caso nace sin sujeto');

        Zone::whereNotIn('slug', [$zonas->first()->slug])->update(['show_in_landing' => false]);

        $html = $this->html();
        $this->assertStringNotContainsString('role="tablist"', $html, 'con una sola zona se pinta una pestaña que no elige nada');
        $this->assertStringContainsString('id="atracciones-'.$zonas->first()->slug.'"', $html, 'sin pestañas desapareció también el panel');
        $this->assertStringNotContainsString('aria-labelledby="rides-tab-', $html, 'el panel apunta a una pestaña que no existe');
    }

    public function test_it_paints_one_tile_per_published_attraction_with_its_photo_and_chips(): void
    {
        $zonas = $this->zonas();
        $publicadas = $zonas->sum(fn (Zone $z): int => $z->attractions->count());

        // ⚠️⚠️ **El caso PONE la foto, no la hereda del sembrador** (`#663`). El material gráfico del
        // cliente salió del repo, y el seeder guarda `null` cuando el fichero no está: en una máquina
        // sin el paquete instalado NINGUNA atracción tenía foto y este caso moría por su propia
        // guarda de «nace sin sujeto» —siendo el producto correcto—. Lo que aquí se vigila es que el
        // anfitrión PINTE la foto que haya, no que esta instalación la tenga.
        $zonas->first()->attractions->first()->update(['image' => 'images/attractions/jump_saltos_libres.webp']);

        $html = $this->html();

        $this->assertSame($publicadas, substr_count($html, '<li class="ride-tile">'), 'no se pinta una ficha por atracción publicada');

        $ride = $zonas->first()->attractions->first();
        $this->assertStringContainsString(e($ride->tr('name')), $html);
        $this->assertStringContainsString((string) $ride->imageUrl(), $html, 'la foto no sale por `imageUrl()`');

        $conFoto = substr_count($html, 'ride-tile__img');
        $this->assertGreaterThan(0, $conFoto, 'el caso nace sin sujeto: ninguna atracción tiene foto');

        // ⚠️ Sin foto NO se reserva hueco: un cuadrado gris esperando es peor que una ficha de texto.
        $ride->update(['image' => null]);
        $this->assertSame($conFoto - 1, substr_count($this->html(), 'ride-tile__img'));
    }

    /** El qué-es y los dos chips se pintan SOLO si el panel los tiene: ni hueco ni etiqueta vacía. */
    public function test_the_description_and_the_two_chips_follow_the_data(): void
    {
        /*
         * ⚠️ **El caso necesita SUJETO, y lo enseñó la mutación**: el seeder describe las 23, así que
         * «se pinta siempre» y «se pinta solo si lo tiene» cuentan IGUAL, y el mutante que pintaba el
         * «qué es» en todas sobrevivía. Se deja una muda a propósito.
         */
        $this->zonas()->first()->attractions->first()->update(['description' => null]);

        $html = $this->html();

        $this->assertSame(
            $this->publicadasCon('description'),
            substr_count($html, 'ride-tile__desc'),
            'se pinta un «qué es» por cada atracción que lo tiene, ni uno más',
        );
        $this->assertSame($this->publicadasCon('age'), substr_count($html, 'ride-tile__age'));
        $this->assertSame($this->publicadasCon('badge'), substr_count($html, 'ride-tile__badge'));

        // Y una ficha sin NINGUNO de los dos pierde el renglón entero: un `<p>` vacío es un hueco.
        $chips = substr_count($html, 'ride-tile__chips');
        $this->assertGreaterThan(0, $chips, 'el caso nace sin sujeto: ninguna ficha tiene chips');

        $this->zonas()->first()->attractions->first()->update(['age' => null, 'badge' => null]);
        $this->assertSame($chips - 1, substr_count($this->html(), 'ride-tile__chips'));
    }

    /** @return int Cuántas atracciones publicadas traen ese campo traducido. */
    private function publicadasCon(string $campo): int
    {
        return $this->zonas()->flatMap->attractions->filter(fn (Attraction $a): bool => (bool) $a->tr($campo))->count();
    }

    /**
     * La cifra de cada zona lleva su PALETA COMPUESTA, que es lo que hace legible el número
     * (`ThemeSettings::zoneStyle()` emite `--zone-1`, `--zone-2` y `--zone-ink` de una vez).
     * ⚠️ Y sin tramo de edad la frase cae a la que nombra la zona: «atracciones para » no se publica.
     */
    public function test_the_zone_figure_carries_its_palette_and_its_phrase(): void
    {
        $zona = $this->zonas()->first();
        $html = $this->html();

        $this->assertStringContainsString('<span class="rides-zone__n">'.$zona->attractions->count().'</span>', $html);
        $this->assertStringContainsString('--zone-ink:', $html, 'la cifra no recibe la paleta compuesta de su zona');
        $this->assertStringContainsString(e(__('landing.attractions.count_phrase', ['age' => $zona->tr('age_range')])), $html);

        $zona->forceFill(['age_range' => null])->save();
        $html = $this->html();
        $this->assertStringContainsString(e(__('landing.attractions.count_phrase_plain', ['zone' => $zona->tr('name')])), $html);
        $this->assertStringNotContainsString('atracciones para <', $html, 'se publica la frase con el hueco de la edad vacío');
    }

    /**
     * ⚠️⚠️ La nota del pie es LA MISMA que la de la sección 01 de la portada (`landing.zones_access`,
     * `#587`), no una copia: se dice igual en todas las superficies. Sin nota no hay pie.
     * ⚠️ Y la página no recupera una salida propia a las zonas: quien ofrece destinos es la banda.
     */
    public function test_the_foot_repeats_the_park_rule_and_offers_no_exit_of_its_own(): void
    {
        $this->assertStringNotContainsString('page__foot-rule', $this->html(), 'sin nota del panel se pinta el pie igual');

        $nota = 'Los menores de 4 entran en Kids con un adulto.';
        Setting::updateOrCreate(['key' => 'landing.zones_access.es'], ['value' => $nota, 'group' => 'landing']);
        Setting::flushMemo();

        $html = $this->html();
        $this->assertStringContainsString('<p class="page__foot-rule">'.e($nota).'</p>', $html);
        $this->assertStringNotContainsString('href="'.url('/#zones').'"', $html,
            'la página ha recuperado una salida propia a las zonas: los destinos los ofrece la banda');
    }

    /** El anfitrión es el motor sin el arte: ni fachada ni pegatina de zona (eso es de la instancia). */
    public function test_the_host_carries_no_art(): void
    {
        $html = $this->html();

        $this->assertStringNotContainsString('fac-p--page-atracciones', $html);
        $this->assertStringNotContainsString('zone-sticker', $html);
    }

    /** La zona que llega por `?zona=` es la que arranca visible, y las demás nacen ocultas. */
    public function test_the_arriving_zone_is_the_one_that_starts_visible(): void
    {
        $zonas = $this->zonas();
        $segunda = $zonas->get(1);
        $this->assertNotNull($segunda, 'el caso nace sin sujeto');

        $html = $this->html('?zona='.$segunda->slug);

        $this->assertStringContainsString('x-data="{ zone: \''.$segunda->slug.'\' }"', $html);

        /*
         * ⚠️ `x-cloak` va en las que NO llegan activas, y no en «todas menos la primera»: con `?zona=kids`
         * la primera es Jump, y ocultar por posición pintaría Jump un instante antes de cambiar. Se mira
         * la etiqueta de apertura de cada panel, no el número de apariciones en la página: `x-cloak` lo
         * usan también otras piezas del armazón, y contarlas todas mediría cualquier cosa.
         */
        foreach ($zonas as $zona) {
            $this->assertMatchesRegularExpression('/<section[^>]*id="atracciones-'.$zona->slug.'"[^>]*>/', $html);
            preg_match('/<section[^>]*id="atracciones-'.$zona->slug.'"[^>]*>/', $html, $panel);

            $zona->slug === $segunda->slug
                ? $this->assertStringNotContainsString('x-cloak', $panel[0], 'la zona que llega elegida nace oculta')
                : $this->assertStringContainsString('x-cloak', $panel[0], "el panel de «{$zona->slug}» se pinta un instante antes de esconderse");
        }
    }
}
