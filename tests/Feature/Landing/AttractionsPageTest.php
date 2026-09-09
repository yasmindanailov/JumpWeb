<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Models\Attraction;
use App\Domain\Content\Services\ThemeSettings;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **`/atracciones` — LA PÁGINA QUE LA SECCIÓN 03 NECESITA** (carril de diseño Fase 2 · T2d·1,
 * `specs/rediseno-desde-canvas.md`). Artboard `Atracciones PJP` **1a** + **1c**.
 *
 * ▶ Lo que se vigila aquí NO es el aspecto —eso lo mira el owner y lo midió la sonda—: son las
 * cosas que se romperían **en silencio**, con la página cargando y la suite en verde.
 *
 * ⚠️⚠️ **Y una de ellas es ARITMÉTICA, no marcado.** La cifra grande de cada zona se pinta con el
 * color de esa zona, que llega desde el PANEL; medido sobre esta instalación, los dos colores reales
 * fallan como texto sobre papel (lima **1,85** y cian **2,45**, contra el 3,0 que es el suelo de
 * cualquier texto). Por eso el valor lo DERIVA `ThemeSettings::zoneInk()`, y por eso hay un caso que
 * vuelve a hacer la cuenta con los colores reales: una derivación mal calibrada pinta un número
 * ilegible **sin que nada falle**.
 *
 * ❗❗ **Este fichero ya cambió el diseño una vez, y lo hizo en verde.** La primera versión de la
 * cifra usaba `color-mix` con un porcentaje FIJO: al 60 % el lima de esta instalación daba 4,23 y al
 * 55 % pasaba (4,76)… pero el `#C6FF3A` que el PRODUCTO trae por defecto para Kids se quedaba en
 * **3,14**. *Lo encontró la guarda con los datos del producto, no una relectura del CSS.* De ahí
 * salió la derivación por pasos, que se adapta al color en vez de suponerlo.
 */
class AttractionsPageTest extends TestCase
{
    use RefreshDatabase;

    /** Texto normal en WCAG 2.1. La cifra es grande y le bastaría 3,0; se exige el techo a propósito. */
    private const UMBRAL_AA = 4.5;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LandingContentSeeder::class);
        app()->setLocale('es');
    }

    public function test_the_page_lists_every_active_attraction_of_every_zone(): void
    {
        $zonas = Zone::where('show_in_landing', true)->orderBy('position')->get()
            ->filter(fn (Zone $z): bool => $z->attractions()->where('is_active', true)->exists());

        $this->assertGreaterThan(1, $zonas->count(), 'sin al menos dos zonas con atracciones este fichero no vigila nada');

        $html = $this->get('/atracciones')->assertOk()->getContent();

        $total = 0;

        foreach ($zonas as $zona) {
            $this->assertStringContainsString('id="atracciones-'.$zona->slug.'"', $html,
                "la zona «{$zona->slug}» no tiene panel");

            foreach ($zona->attractions()->where('is_active', true)->get() as $ride) {
                $this->assertStringContainsString(e($ride->tr('name')), $html,
                    "«{$ride->tr('name')}» no se pinta");
                $total++;
            }
        }

        // El recuento de la entradilla sale de lo que la página ENSEÑA. Dos cifras del mismo hecho
        // tienen que salir del mismo sitio o divergen sin que nada falle.
        $this->assertStringContainsString('Las '.$total.' atracciones del parque', $html);
        $this->assertSame($total, substr_count($html, 'class="ride-tile"'),
            'el número de fichas pintadas no coincide con las atracciones activas');
    }

    /**
     * ⚠️ **Una zona sin atracciones no tiene pestaña**: un control que abre una rejilla vacía no
     * informa de nada. En esta instalación «Cumpleaños» es exactamente ese caso —es una zona de
     * venta, no un sitio con juegos que enseñar—.
     */
    public function test_a_zone_with_no_attractions_gets_neither_tab_nor_panel(): void
    {
        $vacia = Zone::create([
            'slug' => 'sin-juegos',
            'name' => ['es' => 'Sin juegos'],
            'accent' => 'jump',
            'position' => 9,
            'show_in_landing' => true,
        ]);

        $this->assertSame(0, $vacia->attractions()->count(), 'el caso nace sin sujeto: la zona tiene atracciones');

        $html = $this->get('/atracciones')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="atracciones-sin-juegos"', $html);
        $this->assertStringNotContainsString('id="rides-tab-sin-juegos"', $html);
    }

    public function test_an_inactive_attraction_is_not_published(): void
    {
        $ride = Attraction::where('is_active', true)->firstOrFail();
        $nombre = $ride->tr('name');

        $this->assertStringContainsString(e($nombre), $this->get('/atracciones')->getContent());

        $ride->update(['is_active' => false]);

        $this->assertStringNotContainsString(e($nombre), $this->get('/atracciones')->getContent());
    }

    /**
     * ⚠️⚠️ **La zona de llegada viaja en la QUERY y no en el hash**, porque un hash no llega al
     * servidor: una pestaña elegida por ancla solo funciona con JavaScript. El precedente roto está
     * al lado —`#478` enlazó a `/precios#zona-<slug>` y esa página emite cero `id="zona-…"`—.
     * ⚠️ Y un valor desconocido **no es un error**: es la primera zona. Un 404 por un parámetro
     * tecleado a mano haría desaparecer la página.
     */
    public function test_the_query_picks_the_arriving_zone_and_an_unknown_value_falls_back(): void
    {
        $zonas = Zone::where('show_in_landing', true)->orderBy('position')->get()
            ->filter(fn (Zone $z): bool => $z->attractions()->where('is_active', true)->exists())->values();

        $primera = $zonas->first();
        $segunda = $zonas->get(1);

        $this->assertNotNull($segunda, 'hace falta una SEGUNDA zona o el caso no distingue elegir de caer al defecto');

        $html = $this->get('/atracciones?zona='.$segunda->slug)->assertOk()->getContent();
        $this->assertStringContainsString("x-data=\"{ zone: '".$segunda->slug."' }\"", $html);

        $html = $this->get('/atracciones?zona=no-existe')->assertOk()->getContent();
        $this->assertStringContainsString("x-data=\"{ zone: '".$primera->slug."' }\"", $html);

        $html = $this->get('/atracciones')->assertOk()->getContent();
        $this->assertStringContainsString("x-data=\"{ zone: '".$primera->slug."' }\"", $html);
    }

    /**
     * ⚠️ **Sin foto no se reserva hueco.** Un cuadrado gris esperando es peor que una ficha de
     * texto, que es la regla que el canvas escribió para la tarjeta del bar.
     */
    public function test_an_attraction_without_a_photo_leaves_no_empty_box(): void
    {
        $conFoto = substr_count($this->get('/atracciones')->getContent(), 'ride-tile__img');
        $this->assertGreaterThan(0, $conFoto, 'el caso nace sin sujeto: ninguna atracción tiene foto');

        Attraction::where('is_active', true)->firstOrFail()->update(['image' => null]);

        $this->assertSame($conFoto - 1, substr_count($this->get('/atracciones')->getContent(), 'ride-tile__img'));
    }

    /**
     * **LA CIFRA DE ZONA TIENE QUE LEERSE SOBRE EL PAPEL, Y ESO ES UNA CUENTA.**
     *
     * Se rehace aquí con los colores REALES: los de las zonas de la instalación contra el papel que
     * declara la hoja. Si alguien toca la derivación —o el panel recibe un color de zona más
     * claro—, este caso se pone rojo antes de que un número ilegible llegue a producción.
     *
     * ⚠️ Y comprueba las DOS mitades: que el color derivado se lea **y** que el token llegue a la
     * página. Derivarlo bien y no emitirlo pinta la cifra con el respaldo y no se entera nadie.
     */
    public function test_the_zone_figure_is_legible_on_paper(): void
    {
        $this->assertStringContainsString('color: var(--zone-ink', file_get_contents(public_path('css/landing.css')),
            'la cifra de zona ya no lee `--zone-ink`: revisa este caso antes de borrarlo');

        $papel = $this->paperColour();

        $zonas = Zone::where('show_in_landing', true)->get()
            ->filter(fn (Zone $z): bool => $z->attractions()->where('is_active', true)->exists());
        $this->assertGreaterThan(0, $zonas->count(), 'el caso nace sin sujeto: ninguna zona tiene atracciones');

        foreach ($zonas as $zona) {
            $color = ThemeSettings::colorForAccent($zona->color, $zona->accent);
            $tinte = ThemeSettings::zoneInk($color);
            $ratio = $this->contrast($tinte, $papel);

            $this->assertGreaterThanOrEqual(self::UMBRAL_AA, $ratio, sprintf(
                'la cifra de «%s» sale a %.2f sobre el papel (zona %s → `--zone-ink` %s): por debajo de %.1f no se lee.',
                $zona->slug, $ratio, $color, $tinte, self::UMBRAL_AA,
            ));

            // Y el token tiene que LLEGAR a la página: derivarlo bien y no emitirlo pinta la cifra
            // con el fallback y nadie se entera.
            $this->assertStringContainsString('--zone-ink:'.$tinte, $this->get('/atracciones')->getContent(),
                "el panel de «{$zona->slug}» no emite su `--zone-ink`");
        }
    }

    /**
     * **CONTROL POSITIVO del caso de arriba**: un color de zona claro TIENE que salir oscurecido.
     *
     * Sin esto, la guarda pasaría igual con `zoneInk()` devolviendo el color tal cual, porque los
     * colores de esta instalación podrían pasar solos algún día. Aquí se comprueba que el mecanismo
     * **actúa**: el `#C6FF3A` que el producto trae por defecto para Kids da 1,53 en crudo.
     */
    public function test_a_pale_zone_colour_is_actually_darkened(): void
    {
        $papel = $this->paperColour();

        $crudo = '#C6FF3A';
        $this->assertLessThan(3.0, $this->contrast($crudo, $papel), 'el control nace sin sujeto: ese color ya se leía');

        $tinte = ThemeSettings::zoneInk($crudo);

        $this->assertNotSame($crudo, $tinte, '`zoneInk()` devolvió el color sin tocar: el mecanismo no actúa');
        $this->assertGreaterThanOrEqual(self::UMBRAL_AA, $this->contrast($tinte, $papel));
    }

    /**
     * ⚠️ Y la derivación **reproduce la variante oscura del propio sistema**, que es lo que la hace
     * defendible: cuatro pasos sobre el Lima Bote dan el Lima 800 que el artboard escribe a mano.
     */
    public function test_the_derivation_lands_on_the_systems_own_dark_variant(): void
    {
        $this->assertSame('#627411', ThemeSettings::zoneInk('#A3C21C'));
    }

    /**
     * ⚠️ **La regla del pie es LA MISMA cadena que la sección 01**, no una copia. Es la regla del
     * parque y se dice igual en todas las superficies; escribirla otra vez es cómo dos sitios acaban
     * publicando la misma norma con dos redacciones.
     */
    public function test_the_foot_repeats_the_park_rule_verbatim_and_leads_back_to_the_zones(): void
    {
        $html = $this->get('/atracciones')->assertOk()->getContent();

        $this->assertStringContainsString(e(__('landing.zones.rule')), $html);
        $this->assertStringContainsString('href="'.url('/#zones').'"', $html);
    }

    /**
     * El PAPEL vigente en esta máquina: el que declara el producto, sobrescrito por el del paquete
     * si existe.
     *
     * ⚠️ `client.css` está **gitignorado**, así que un caso que dependiera de él pasaría aquí y
     * fallaría en un clon limpio. Por eso el producto es el suelo y el paquete solo afina — y por
     * eso `zoneInk()` deriva contra el papel del PRODUCTO, que es el más oscuro de los dos y por
     * tanto el lado seguro.
     */
    private function paperColour(): string
    {
        $papel = '#F4EFE3';

        foreach (['css/landing.css', 'css/client.css'] as $hoja) {
            if (! is_file(public_path($hoja))) {
                continue;
            }

            // Solo el primer `:root`: los bloques por superficie redefinen `--bg`, y aquí se mide el
            // PAPEL, que es la superficie de esta página.
            $css = file_get_contents(public_path($hoja));
            $raiz = substr($css, 0, strpos($css, '}') ?: null);

            if (preg_match('/--bg:\s*(#[0-9A-Fa-f]{6})/', $raiz, $m)) {
                $papel = $m[1];
            }
        }

        return $papel;
    }

    private function contrast(string $a, string $b): float
    {
        $x = $this->luminance($a);
        $y = $this->luminance($b);

        return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
    }

    private function luminance(string $hex): float
    {
        $lin = static fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        [$r, $g, $b] = $this->rgb($hex);

        return 0.2126 * $lin($r / 255) + 0.7152 * $lin($g / 255) + 0.0722 * $lin($b / 255);
    }

    /** @return array{int, int, int} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
