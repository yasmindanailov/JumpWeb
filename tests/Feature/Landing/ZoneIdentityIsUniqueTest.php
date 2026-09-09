<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Models\Attraction;
use App\Domain\Content\Services\ThemeSettings;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **LA IDENTIDAD DE UNA ZONA EN EL MARCADO ES `slug`, NUNCA `accent`.**
 *
 * ❗❗ **Esto nace de un defecto REAL y medido, no de una precaución** (`#295`). La sección de
 * atracciones identificaba cada carrusel por `accent`, que **agrupa y no identifica**: en datos
 * reales `kids`, `cap` y `cap2` comparten el suyo, así que **tres carruseles emitían el mismo
 * `x-ref` y el mismo `data-zone`**. Medido en navegador: pulsar «Zona KIDS» abría **tres a la vez**
 * —8 tarjetas y dos vacíos, 568 px— y las flechas movían uno cualquiera, porque `$refs` resuelve a
 * uno solo.
 *
 * ⚠️⚠️ **Esta guarda existe porque su antecesora se BORRÓ.** El arreglo viajaba dentro de
 * `ZonesAndRidesUnifiedTest`, que vigilaba la estructura UNIFICADA de zonas y atracciones; el
 * 2026-08-31 el owner pidió volver a la estructura anterior (`#300`) y ese fichero se fue con ella.
 * **El arreglo de identidad SÍ se conservó**, así que se le vuelve a poner red aquí: *si un arreglo
 * sobrevive a la guarda que lo protegía, se queda desnudo y nadie se entera hasta que vuelve a
 * romperse.*
 *
 * ⚠️ **Y la misma raíz ya se había arreglado A MEDIAS antes**: `ThemeColorTest` tiene desde `#230`
 * un caso llamado *«dos zonas que comparten acento ya no comparten color»* — se corrigió el COLOR y
 * la IDENTIDAD se quedó en `accent`. Las dos mitades tienen ahora guarda propia.
 *
 * ❗❗❗ **Y VUELVE A PASAR LO MISMO EN `#482`: el carrusel de la portada se retiró y esta guarda se
 * quedó sin sujeto por SEGUNDA vez.** No se borra —sería la tercera vez que el arreglo se queda
 * desnudo—: se **re-apunta a `/atracciones`**, que es donde hoy vive una pieza por zona (una
 * pestaña y un panel, más su paleta compuesta). *Una guarda re-apuntada no puede quedar más débil
 * que la que sustituye*, así que sigue mirando las mismas tres cosas: que el localizador encuentre
 * algo, que dos zonas con el mismo acento no compartan identidad, y que el color siga saliendo del
 * acento.
 */
class ZoneIdentityIsUniqueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
        app()->setLocale('es');
    }

    /** El HTML de `/atracciones`, una sola vez por llamada. */
    private function pagina(): string
    {
        return (string) $this->get('/atracciones')->assertOk()->getContent();
    }

    /**
     * Todo lo que en el marcado dice **cuál** es una zona, agrupado por sitio.
     *
     * @return array<string, list<string>>
     */
    private function identifiers(): array
    {
        $html = $this->pagina();

        // ⚠️ El sitio cambió DOS veces: `.zone-tab` hasta `#301`, `.zone-pick__tab` en la portada
        // hasta `#482`, y hoy la pestaña y el panel de `/atracciones`. Cada vez lo dijo **la
        // guarda-de-la-guarda de abajo al ponerse ROJA**, que es exactamente para lo que está.
        preg_match_all('/id="atracciones-([a-z0-9-]+)"/', $html, $paneles);
        preg_match_all('/id="rides-tab-([a-z0-9-]+)"/', $html, $pestanas);
        preg_match_all('/aria-controls="atracciones-([a-z0-9-]+)"/', $html, $controlados);

        return [
            'panel' => $paneles[1],
            'pestaña' => $pestanas[1],
            'aria-controls' => $controlados[1],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **El localizador encuentra los tres sitios.**
     *
     * ⚠️ Sin este caso, un cambio de marcado dejaría los tres `preg_match_all` devolviendo listas
     * VACÍAS y el caso de abajo pasaría en verde **sin mirar nada**: una lista vacía no tiene
     * duplicados. Es el fallo que este proyecto ha cometido cuatro veces.
     */
    public function test_the_probe_finds_panels_tabs_and_their_controls(): void
    {
        foreach ($this->identifiers() as $sitio => $encontrados) {
            $this->assertNotEmpty(
                $encontrados,
                "el localizador no encuentra ningún «{$sitio}» en `/atracciones`: o el marcado cambió ".
                'de forma, o esta guarda lleva tiempo pasando sin mirar nada.',
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo que se vigila
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **❗ EL CASO QUE MOTIVÓ LA GUARDA: dos zonas con el MISMO acento son dos zonas distintas.**
     *
     * Con la identidad en `accent` este escenario emite dos veces el mismo `data-zone`, los dos
     * `x-show` se abren juntos y `$refs` resuelve a uno solo.
     *
     * ⚠️ **La zona gemela se CREA aquí a propósito**: el seeder de test no trae dos zonas que
     * compartan acento, así que sin crearla el caso pasaría en vacío — que es exactamente cómo
     * nacieron ciegos dos casos de la guarda anterior.
     */
    public function test_two_zones_sharing_an_accent_do_not_share_an_identity(): void
    {
        $kids = Zone::where('slug', 'kids')->firstOrFail();

        $gemela = Zone::create([
            'slug' => 'kids-gemela', 'name' => ['es' => 'Kids gemela'], 'accent' => $kids->accent,
            'color' => '#0000FF', 'position' => 98, 'is_active' => true, 'show_in_landing' => true,
        ]);

        Attraction::create([
            'zone_id' => $gemela->id, 'name' => ['es' => 'Atracción gemela'],
            'position' => 1, 'is_active' => true,
        ]);

        $this->assertSame(
            $kids->accent, $gemela->accent,
            'las dos zonas ya no comparten acento: el caso ha perdido su sujeto y a partir de aquí '.
            'no comprueba nada.',
        );

        foreach ($this->identifiers() as $sitio => $emitidos) {
            $this->assertContains('kids', $emitidos, "la zona original ha perdido su «{$sitio}»");
            $this->assertContains('kids-gemela', $emitidos, "la zona nueva no emite su «{$sitio}»");

            $this->assertSame(
                count($emitidos), count(array_unique($emitidos)),
                "hay «{$sitio}» repetidos: la identidad ha vuelto a un campo que AGRUPA en vez de ".
                'identificar. Con eso dos zonas se pisan: un `id` duplicado hace que `aria-controls` '.
                'apunte a una sola y que pulsar una pestaña abra el panel de otra.',
            );
        }
    }

    /**
     * **La PALETA sigue saliendo de `accent`, y eso no es una inconsistencia: es el reparto.**
     *
     * ⚠️⚠️ Sin este caso, el arreglo de arriba invita a «terminar el trabajo» moviendo también el
     * color a `slug` — y eso **rompería lo que `accent` existe para hacer**: agrupar zonas bajo una
     * misma paleta. Identidad y color responden a preguntas distintas (*cuál es* / *de qué color
     * va*) y por eso salen de campos distintos.
     */
    public function test_the_zone_palette_still_comes_from_the_accent(): void
    {
        $kids = Zone::where('slug', 'kids')->firstOrFail();

        $gemela = Zone::create([
            'slug' => 'kids-gemela', 'name' => ['es' => 'Kids gemela'], 'accent' => $kids->accent,
            'color' => null, 'position' => 98, 'is_active' => true, 'show_in_landing' => true,
        ]);

        Attraction::create([
            'zone_id' => $gemela->id, 'name' => ['es' => 'Atracción gemela'],
            'position' => 1, 'is_active' => true,
        ]);

        $html = $this->pagina();

        // La paleta viaja COMPUESTA en el `style` del panel (`ThemeSettings::zoneStyle()`), que es
        // donde `#138` la dejó: aquí se lee su `--zone-1`.
        preg_match(
            '/id="atracciones-'.preg_quote($gemela->slug, '/').'"[^>]*style="--zone-1:([^;]+);/',
            $html, $m,
        );

        $this->assertNotEmpty($m, 'el panel de la zona gemela no emite su `--zone-1`');

        $this->assertSame(
            ThemeSettings::colorForAccent(null, $kids->accent),
            $m[1],
            'una zona SIN color propio ha dejado de heredar el de su acento: `accent` ha perdido su '.
            'papel de agrupador y cada zona pinta por su cuenta.',
        );
    }
}
