<?php

namespace Tests\Feature\Landing;

use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Models\Attraction;
use App\Domain\Content\Services\IllustrationKit;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * **ZONAS Y SUS JUEGOS: UNA SECCIÓN, UN SELECTOR** (`specs/idioma-visual-heredado.md`).
 *
 * `[DECIDIDO owner, 2026-08-31]` (`#302`): *«quitar las tarjetas para seleccionar la zona, porque en
 * las atracciones ya hay un toggle; ese mismo toggle lo ampliamos […] para añadirle la edad a cada
 * zona»* y *«las cards de las atracciones, solamente el título y el tag, sin texto descriptivo ni la
 * edad»*.
 *
 * ▶ Lo que se vigila aquí NO es el aspecto —eso lo mira el owner—: son las cuatro cosas que se
 * romperían **en silencio**, con la página cargando y la suite en verde.
 */
class ZonesSectionTest extends TestCase
{
    use RefreshDatabase;

    private string $publicDir = '';

    /**
     * ⚠️⚠️ **El kit se instala FALSO en un `public/` temporal, y no es un capricho.**
     * `public/img/client-kit.svg` está **gitignorado**: es el paquete de la instalación. Un caso que
     * aseverara contra el kit real pasaría en esta máquina y fallaría en un clon limpio o en CI —o,
     * peor, pasaría por casualidad porque el kit de aquí trae justo la clave que se busca—.
     * Es el mismo aislamiento que usa `IllustrationHoleRenderTest`.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->publicDir = sys_get_temp_dir().'/jw-zonas-'.getmypid().'-'.uniqid();
        mkdir($this->publicDir.'/img', 0o777, true);
        @symlink(base_path('public/build'), $this->publicDir.'/build');
        $this->app->usePublicPath($this->publicDir);
        IllustrationKit::forget();

        $this->seed(LandingContentSeeder::class);
        app()->setLocale('es');
    }

    protected function tearDown(): void
    {
        IllustrationKit::forget();

        if ($this->publicDir !== '' && is_dir($this->publicDir)) {
            @unlink($this->publicDir.'/build');
            @unlink($this->publicDir.'/'.IllustrationKit::PATH);
            @rmdir($this->publicDir.'/img');
            @rmdir($this->publicDir);
        }

        parent::tearDown();
    }

    /** Instala un kit con las claves pedidas en el `public/` del test. */
    private function instalarKit(string ...$claves): void
    {
        $simbolos = '';

        foreach ($claves as $c) {
            $simbolos .= '<symbol id="'.$c.'" viewBox="0 0 64 64"><title>'.$c.'</title>'
                .'<path d="M8 8h48v48H8Z"/></symbol>';
        }

        file_put_contents(
            public_path(IllustrationKit::PATH),
            '<svg xmlns="http://www.w3.org/2000/svg">'.$simbolos.'</svg>',
        );

        // El caché se indexa por `filemtime`, y dos escrituras del mismo segundo comparten marca.
        IllustrationKit::forget();
        clearstatcache();
    }

    private function home(): string
    {
        return (string) $this->get('/')->assertOk()->getContent();
    }

    /** El trozo de la portada que va de la sección de zonas a la siguiente. */
    private function seccion(): string
    {
        $html = $this->home();
        $i = strpos($html, 'id="zones"');
        $j = strpos($html, 'id="pricing"');

        $this->assertNotFalse($i, 'la portada ya no tiene la sección `#zones`');
        $this->assertNotFalse($j, 'no encuentro dónde acaba la sección: ha cambiado el marcado');

        return substr($html, $i, $j - $i);
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **El localizador acota algo que existe y no está vacío.**
     *
     * ⚠️ Sin este caso, un cambio de marcado dejaría `seccion()` devolviendo una cadena corta o
     * vacía y todos los `assertStringNotContainsString` de abajo pasarían **sin mirar nada**: en el
     * vacío no hay nada que encontrar. Es el fallo que este proyecto ha cometido cuatro veces.
     */
    public function test_the_probe_frames_a_real_section(): void
    {
        $seccion = $this->seccion();

        $this->assertGreaterThan(
            2000, strlen($seccion),
            'la sección acotada es sospechosamente corta: el localizador probablemente no está '.
            'midiendo lo que cree.',
        );

        $this->assertStringContainsString('zone-pick__tab', $seccion, 'no hay selector de zona dentro');
        $this->assertStringContainsString('ride-card', $seccion, 'no hay tarjetas de atracción dentro');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo que se vigila
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **UNA sección, UN titular.**
     *
     * Antes eran dos —zonas y atracciones— con dos cabeceras del mismo molde, una detrás de otra.
     * ⚠️ Volver a añadir un `<h2>` aquí no rompe nada visible: solo devuelve el molde que `#297`
     * diagnosticó, y nadie se entera hasta que alguien vuelve a mirar la portada entera.
     */
    public function test_the_zones_section_has_exactly_one_heading(): void
    {
        $this->assertSame(
            1, preg_match_all('/<h2\b/', $this->seccion()),
            'la sección de zonas y juegos tiene más de un `<h2>`: ha vuelto la cabecera doble.',
        );
    }

    /**
     * **Las DOS anclas siguen vivas, y no es cosmético: hay seis enlaces apuntándolas.**
     *
     * `/#zones` lo enlazan el menú (×2) y el pie (×2); `/#rides`, el menú y el pie. Si una sección
     * se funde con otra y el ancla se pierde, esos enlaces siguen existiendo y **dejan de llevar a
     * ninguna parte** — el navegador no falla, simplemente no salta.
     */
    public function test_both_anchors_survive_the_merge(): void
    {
        $html = $this->home();

        foreach (['zones', 'rides'] as $ancla) {
            $this->assertMatchesRegularExpression(
                '/\bid="'.$ancla.'"/', $html,
                "el ancla `#{$ancla}` ya no existe, y hay enlaces del menú y del pie apuntándola.",
            );

            $this->assertMatchesRegularExpression(
                '/href="[^"]*#'.$ancla.'"/', $html,
                "nadie enlaza `#{$ancla}`: este caso ha perdido su motivo y habría que revisarlo.",
            );
        }
    }

    /**
     * **La tarjeta de atracción NO emite descripción ni edad.**
     *
     * ⚠️⚠️ **Los dos textos se CREAN aquí, y ese detalle ES el caso.** Aseverar contra los datos del
     * seeder saldría verde por casualidad el día que una atracción se quede sin descripción; con un
     * valor propio e inconfundible, si la vista vuelve a pintarlo, se ve.
     */
    public function test_the_ride_card_shows_neither_description_nor_age(): void
    {
        $ride = Attraction::query()->firstOrFail();
        $ride->update([
            'description' => ['es' => 'DescripcionQueNoDebeSalirZZ'],
            'age' => ['es' => 'EdadQueNoDebeSalirZZ'],
            'name' => ['es' => 'NombreQueSiSaleZZ'],
        ]);

        $seccion = $this->seccion();

        $this->assertStringContainsString(
            'NombreQueSiSaleZZ', $seccion,
            'la atracción no llega a la portada: el caso ha perdido su sujeto y no comprueba nada.',
        );

        $this->assertStringNotContainsString(
            'DescripcionQueNoDebeSalirZZ', $seccion,
            'la tarjeta vuelve a pintar la descripción de la atracción.',
        );

        $this->assertStringNotContainsString(
            'EdadQueNoDebeSalirZZ', $seccion,
            'la tarjeta vuelve a pintar la edad de la atracción.',
        );
    }

    /**
     * **TODAS las tarjetas ofrecen un camino, no solo la que se vende.**
     *
     * `[DECIDIDO owner]` (`#303`: «añade un CTA a las cards para que el usuario sepa que tiene que
     * clicarlo»). ⚠️ **No existe página de detalle de atracción**, así que el CTA lleva a reservar la
     * ZONA —el parque vende por zona, no por atracción— y las dos ramas llaman a la MISMA acción.
     *
     * ⚠️⚠️ **Las dos mitades hacen falta.** Sin la primera, volver al CTA solo en la comprable
     * dejaría 22 de 23 tarjetas mudas y pasaría en verde. Sin la segunda, un cambio que pusiera
     * PRECIO a todas —que es enseñar el precio de algo que no se vende— tampoco lo vería nadie.
     */
    public function test_every_ride_card_offers_a_way_in_and_only_the_sellable_shows_a_price(): void
    {
        $seccion = $this->seccion();

        // ⚠️ Acotado al ELEMENTO: `class="ride-card` casa también con `ride-card__viz`, `__img`,
        // `__name`… La primera versión contó **115 tarjetas donde hay 23** y acusó al producto de un
        // defecto que era del contador. *Es la trampa de la subcadena, otra vez.*
        $tarjetas = preg_match_all('/<article class="ride-card[ "]/', $seccion);
        $ctas = preg_match_all('/<button [^>]*class="[^"]*\bride-card__cta\b/', $seccion);

        $this->assertGreaterThan(0, $tarjetas, 'no hay tarjetas: el caso ha perdido su sujeto');

        $this->assertSame(
            $tarjetas, $ctas,
            "hay {$tarjetas} tarjetas y {$ctas} CTA: alguna tarjeta se ha quedado sin decir qué hacer.",
        );

        $this->assertLessThan(
            $tarjetas, preg_match_all('/class="ride-card__price"/', $seccion),
            'TODAS las tarjetas enseñan precio: se está anunciando el precio de algo que no se vende.',
        );
    }

    /**
     * **La EDAD subió al selector — que es lo que se pidió— y una zona sin edad no deja hueco.**
     *
     * ⚠️ Las dos mitades hacen falta. Sin la primera, retirar la edad del selector pasaría en verde
     * y el dato se perdería del todo (venía de la tarjeta, que ya no está). Sin la segunda, pintar
     * un separador o un guion para una zona sin edad tampoco lo vería nadie.
     */
    public function test_the_picker_carries_the_age_and_omits_it_when_missing(): void
    {
        Zone::where('slug', 'jump')->update(['age_range' => ['es' => 'EdadDeZonaZZ']]);
        Zone::where('slug', 'kids')->update(['age_range' => null]);

        $seccion = $this->seccion();

        $this->assertMatchesRegularExpression(
            '/<span class="zone-pick__age">\s*EdadDeZonaZZ\s*<\/span>/', $seccion,
            'el selector ya no lleva la edad de la zona.',
        );

        $this->assertSame(
            1, preg_match_all('/class="zone-pick__age"/', $seccion),
            'hay más etiquetas de edad que zonas con edad: una zona sin el dato está emitiendo el '.
            'contenedor vacío.',
        );
    }

    /**
     * **El selector pide el dibujo de SU zona por `slug`, no por `accent`.**
     *
     * ⚠️⚠️ **La zona GEMELA se crea aquí, y ese detalle ES el caso.** La primera versión aseveraba
     * que se piden `zone-jump` y `zone-kids`, y **pasaba en verde con la mutación puesta**: en la BD
     * de test `accent` y `slug` VALEN LO MISMO para esas dos zonas, así que pedir por uno o por otro
     * daba idéntico resultado. *Un caso sin sujeto no vigila nada, y no se nota hasta que se muta.*
     * ▶ Con una zona cuyo `accent` es `kids` y cuyo `slug` no lo es, la diferencia se ve: por `slug`
     * pide un dibujo que el kit no trae —y no emite nada, que es el modo de fallo elegido—; por
     * `accent` pediría **otra vez** el de `kids` y lo pintaría, agrupando dos zonas bajo un dibujo.
     */
    public function test_the_picker_asks_for_the_zone_illustration_by_slug(): void
    {
        $this->instalarKit('zone-kids');

        $kids = Zone::where('slug', 'kids')->firstOrFail();

        Zone::create([
            'slug' => 'kids-gemela', 'name' => ['es' => 'Kids gemela'], 'accent' => $kids->accent,
            'color' => '#0000FF', 'position' => 98, 'is_active' => true, 'show_in_landing' => true,
        ]);

        $seccion = $this->seccion();

        $this->assertStringContainsString(
            'Kids gemela', $seccion,
            'la zona gemela no llega al selector: el caso ha perdido su sujeto.',
        );

        $this->assertSame(
            1, preg_match_all('/<use href="[^"]*#zone-kids"/', $seccion),
            "el dibujo `zone-kids` se pide MÁS DE UNA VEZ.\n".
            '▶ La clave se está componiendo con `accent`, que AGRUPA: dos zonas distintas acaban '.
            'enseñando el mismo dibujo. La identidad de una zona es su `slug`.',
        );
    }

    /**
     * **❗ TODA RANURA DECLARADA TIENE UNA PANTALLA QUE LA PINTA.**
     *
     * ⚠️⚠️ **Esta regla estaba escrita en tres sitios y no la imponía nadie.** `IllustrationKit`
     * dice que la lista «solo crece, y solo con su consumidor en el mismo cambio»;
     * `hueco-ilustracion.md` §15·3 lo repite; y el motivo es concreto: declarar ranuras antes de que
     * exista la pantalla que las pinta **es lo que dejó los 19 dibujos de `#257` esperando**.
     * ▶ Hasta hoy eso dependía de que cada agente se acordara. Ahora falla la suite.
     *
     * ⚠️ Y `SLOTS` vacía es un estado VÁLIDO —lo ha estado tres veces—, así que el caso no exige que
     * haya ranuras: exige que las que haya tengan consumidor.
     */
    public function test_every_declared_slot_is_painted_by_a_screen(): void
    {
        $vistas = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($f) => str_ends_with($f->getFilename(), '.blade.php'))
            ->map(fn ($f) => (string) file_get_contents($f->getPathname()))
            // ⚠️ Sin los comentarios: una lápida que NOMBRA una ranura retirada la haría parecer
            // viva. Es el fallo que `#293` documentó con el motivo copiado a mano.
            ->map(fn ($s) => (string) preg_replace('/\{\{--.*?--\}\}/s', '', $s))
            ->implode("\n");

        $this->assertStringContainsString(
            'x-site.ilu', $vistas,
            'el corpus de vistas no contiene ni un `<x-site.ilu>`: el escáner no está mirando lo que '.
            'cree, y este caso pasaría en vacío.',
        );

        foreach (IllustrationKit::SLOTS as $ranura) {
            $this->assertStringContainsString(
                $ranura, $vistas,
                "la ranura `{$ranura}` está declarada y NINGUNA vista la pinta.\n".
                "▶ Una ranura vive exactamente lo que vive su consumidor. Declararla antes es lo que\n".
                '  dejó los 19 dibujos de `#257` esperando: o entra su pantalla, o sale de `SLOTS`.',
            );
        }
    }
}
