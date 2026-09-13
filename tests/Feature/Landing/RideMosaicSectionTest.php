<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Models\Attraction;
use App\Domain\Content\Services\RideMosaic;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **SECCIÓN 03 · «QUÉ HAY DENTRO» · EL MOSAICO DE CINCO** (carril de diseño Fase 2 · T2d·2,
 * `DECISIONES #482`). Artboard `Juegos PJP` **6a** + `Escritorio PJP` **4b**.
 *
 * ▶ **Esta guarda SUSTITUYE a las cuatro que se fueron con el carrusel** —las del selector de zona y
 * las de la tarjeta de atracción, que vivían en `ZonesSectionTest`—. *Una guarda re-apuntada no
 * puede quedar más débil que la que sustituye*, así que aquí se vigila lo que la sección promete
 * ahora: cinco piezas con su reparto, dos veladas que no son destino, y una puerta que dice la
 * verdad.
 *
 * ⚠️⚠️ **Lo que NO se vigila aquí es el aspecto**: la geometría la midió la sonda de navegador
 * (736×490 la grande, 352×229 las dos que se leen, y las veladas cubriendo los 1120 de su fila) y la
 * mira el owner. Aquí están las cosas que se romperían **en silencio**.
 */
class RideMosaicSectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
        app()->setLocale('es');
    }

    private function home(): string
    {
        return (string) $this->get('/')->assertOk()->getContent();
    }

    /** El recorte de la sección 03, del `<section id="rides-section">` a su cierre. */
    private function seccion(): string
    {
        preg_match('#<section id="rides-section".*?</section>#s', $this->home(), $m);

        return $m[0] ?? '';
    }

    /** @return list<string> el papel de cada celda, en el orden en que se pintan */
    private function papeles(): array
    {
        preg_match_all('/data-papel="([a-z]+)"/', $this->seccion(), $m);

        return $m[1];
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ⚠️ Sin este caso, un cambio de marcado dejaría el recorte VACÍO y todo lo de abajo pasaría
     * mirando una cadena de cero caracteres. Es el fallo que este proyecto ha cometido cinco veces.
     */
    public function test_the_probe_frames_a_real_section(): void
    {
        $seccion = $this->seccion();

        $this->assertGreaterThan(1000, strlen($seccion), 'el recorte de la sección 03 es sospechosamente corto');
        $this->assertStringContainsString('mosaic__cell', $seccion, 'no hay ni una celda de mosaico dentro');
        $this->assertNotEmpty($this->papeles(), 'ninguna celda declara su papel');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo que se vigila
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **EL REPARTO: una grande, dos que se leen y dos veladas.**
     *
     * ⚠️ Y las tres con nombre **no salen todas de la misma zona**, que es lo que el artboard
     * justifica: *«antes se nombraban dos de Jump y una de Kids, así que la madre de un niño de 4
     * años veía un solo juego de su zona con nombre»*. Con «las cinco primeras por orden», en esta
     * instalación las cinco saldrían de Jump.
     */
    public function test_the_mosaic_shows_three_named_and_two_veiled_from_more_than_one_zone(): void
    {
        $conJuegos = Zone::where('show_in_landing', true)->get()
            ->filter(fn (Zone $z): bool => $z->attractions()->where('is_active', true)->exists());
        $this->assertGreaterThan(1, $conJuegos->count(), 'con una sola zona este caso no vigila el reparto');

        $this->assertSame(['grande', 'chica', 'chica', 'velada', 'velada'], $this->papeles());

        preg_match_all('/mosaic__zone">([^<]+)</', $this->seccion(), $m);
        $this->assertCount(3, $m[1], 'las que se leen no son tres');
        $this->assertGreaterThan(1, count(array_unique($m[1])),
            'las tres que se leen salen de la MISMA zona: el reparto del artboard existe justamente para eso');
    }

    /**
     * **UNA VELADA NO ES UN DESTINO.** Es la condición con la que el velo entró en el sistema: *«lo
     * que se oculta no puede ser un destino, y un nombre a medio velo se queda sin contraste»*.
     *
     * ⚠️ Las tres cosas van juntas —sin nombre, sin enlace y fuera del árbol de accesibilidad—: una
     * celda velada que siguiera siendo enlace se alcanzaría tabulando y no se vería.
     */
    public function test_a_veiled_cell_has_no_name_no_link_and_no_voice(): void
    {
        preg_match_all('#<li class="mosaic__cell" data-papel="velada"(.*?)</li>#s', $this->seccion(), $m);

        $this->assertCount(2, $m[1], 'no hay dos celdas veladas');

        foreach ($m[1] as $celda) {
            $this->assertStringContainsString('aria-hidden="true"', $celda, 'una velada sigue teniendo voz');
            $this->assertStringNotContainsString('<a ', $celda, 'una velada sigue siendo un enlace');
            $this->assertStringNotContainsString('mosaic__name', $celda, 'una velada sigue llevando nombre');
            $this->assertStringContainsString('mosaic__veil', $celda, 'una velada no lleva velo');
        }
    }

    /**
     * **LAS QUE SE LEEN LLEVAN A `/atracciones`, con SU zona ya elegida** (`[DECIDIDO owner]`).
     *
     * ⚠️ La zona viaja en la QUERY porque un hash no llega al servidor: es la misma razón por la que
     * la página la lee de `?zona=` y no de un ancla.
     */
    public function test_a_named_cell_leads_to_the_page_with_its_own_zone(): void
    {
        preg_match_all('#<a class="mosaic__link" href="([^"]+)"#', $this->seccion(), $m);

        $this->assertCount(3, $m[1], 'las tres que se leen no son enlaces');

        foreach ($m[1] as $href) {
            $this->assertStringContainsString(route('atracciones'), $href);
            $this->assertMatchesRegularExpression('/[?&]zona=[a-z0-9-]+/', $href,
                'el enlace no lleva la zona: quien pulsa una foto de Kids aterriza en la pestaña de otra');
        }
    }

    /**
     * **LA CIFRA DE LA ENTRADILLA Y LA DE LA PUERTA SON LA MISMA, Y SON DATO.**
     *
     * ⚠️⚠️ Es lo que sustituye a la chapa del «18 más» que el recorte del canvas se llevó. Si las dos
     * salieran de sitios distintos, la sección diría «23 atracciones dentro» y su puerta «ver las 21»
     * — y el cliente que las cuenta es el que se entera.
     */
    public function test_the_count_is_data_and_the_two_places_that_publish_it_agree(): void
    {
        $total = Zone::where('show_in_landing', true)->get()
            ->sum(fn (Zone $z): int => $z->attractions()->where('is_active', true)->count());
        $this->assertGreaterThan(5, $total, 'con cinco o menos no hay puerta que comprobar');

        $seccion = $this->seccion();
        // La entradilla dice «N atracciones: trampolines…» desde `#587` (antes «N atracciones dentro»).
        $this->assertStringContainsString($total.' atracciones:', $seccion);
        $this->assertStringContainsString('Ver las '.$total.' atracciones', $seccion);

        // Y se MUEVE con el dato: una atracción menos, dos cifras menos.
        Attraction::where('is_active', true)->firstOrFail()->update(['is_active' => false]);

        $seccion = $this->seccion();
        $this->assertStringContainsString(($total - 1).' atracciones:', $seccion);
        $this->assertStringContainsString('Ver las '.($total - 1).' atracciones', $seccion);
    }

    /**
     * **EL VELO SON DOS CELDAS O NINGUNA**, y no es simetría: es su significado. El artboard descartó
     * su propia opción 4a por esto — *«con solo dos de tres veladas el degradado deja de decir "la
     * sección se acaba" y dice "estas fotos están borrosas"»*.
     *
     * ⚠️ De ahí sale la degradación: con cinco atracciones o menos no hay «más» detrás del velo, así
     * que la sección enseña las tres que se leen y ninguna velada.
     */
    public function test_a_small_park_gets_no_veil_at_all(): void
    {
        // Se deja el parque en CUATRO atracciones: más de las que se leen y menos de las que piden
        // velo. Es el borde exacto donde una sola velada sería una banda a medias.
        $vivas = Attraction::where('is_active', true)->orderBy('id')->pluck('id');
        $this->assertGreaterThan(4, $vivas->count(), 'el caso nace sin sujeto');
        Attraction::whereIn('id', $vivas->slice(4))->update(['is_active' => false]);

        $papeles = $this->papeles();

        $this->assertNotContains('velada', $papeles, 'con cuatro atracciones el velo promete un «más» que no existe');
        $this->assertSame(RideMosaic::CON_NOMBRE, count($papeles), 'el mosaico no encogió a las que se leen');
    }

    /** Sin atracciones no hay mosaico NI puerta: una puerta a una página vacía no es una puerta. */
    public function test_without_attractions_there_is_neither_mosaic_nor_door(): void
    {
        $this->assertNotEmpty($this->papeles(), 'el caso nace sin sujeto');

        Attraction::query()->update(['is_active' => false]);

        $seccion = $this->seccion();
        $this->assertStringNotContainsString('mosaic__cell', $seccion);
        $this->assertStringNotContainsString('rides__door', $seccion);
    }
}
