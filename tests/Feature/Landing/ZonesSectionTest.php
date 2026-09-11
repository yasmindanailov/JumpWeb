<?php

namespace Tests\Feature\Landing;

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
    /**
     * El subárbol de `<section id="zones">`, aislado del resto del documento.
     *
     * ⚠️⚠️ **Antes recortaba «desde `id="zones"` hasta `id="pricing"`», y eso ataba la guarda al
     * ORDEN de las secciones.** Al pasar tarifas delante de zonas (`#314`) el recorte se comió el
     * resto de la portada y el caso del encabezado único contó **cuatro `<h2>`**: la guarda fallaba
     * con el producto sano. ▶ *Un localizador que depende de qué sección viene después no está
     * acotando una sección, está acotando un tramo de página.* Ahora se acota al ELEMENTO, que es
     * lo único que no cambia al reordenar.
     */
    private function seccion(): string
    {
        return $this->acota('zones', 'la portada ya no tiene la sección `#zones`');
    }

    /**
     * **La sección de ATRACCIONES, que desde `#478` es una sección propia.**
     *
     * ⚠️⚠️ Hasta esa tanda el selector de zona y las tarjetas de atracción vivían DENTRO de
     * `#zones`, así que `seccion()` los alcanzaba. Al separar 01 («Para quién», dos tarjetas de
     * zona) de 03 («Qué hay dentro»), el sujeto de cinco casos **cambió de sitio sin cambiar de
     * naturaleza** — y eso es re-apuntar, no relajar: el localizador nuevo acota igual de estrecho,
     * al ELEMENTO y no a un tramo de página, que es la lección de `#314`.
     */
    private function seccionRides(): string
    {
        return $this->acota('rides-section', 'la portada ya no tiene la sección de atracciones');
    }

    /** Acota una `<section>` por su id, al elemento y nunca «hasta la siguiente». */
    private function acota(string $id, string $mensaje): string
    {
        $html = $this->home();

        $this->assertStringContainsString('<section id="'.$id.'"', $html, $mensaje);

        preg_match('#<section id="'.preg_quote($id, '#').'".*?</section>#s', $html, $m);

        $this->assertNotEmpty($m, 'no encuentro dónde acaba la sección: ha cambiado el marcado');

        return $m[0];
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
        $zonas = $this->seccion();
        $rides = $this->seccionRides();

        $this->assertGreaterThan(
            600, strlen($zonas),
            'la sección de zonas acotada es sospechosamente corta: el localizador probablemente no '.
            'está midiendo lo que cree.',
        );
        $this->assertGreaterThan(
            1000, strlen($rides),
            'la sección de atracciones acotada es sospechosamente corta.',
        );

        // ⚠️ Cada localizador se comprueba con SU sujeto, y por separado. Antes los dos vivían en
        // la misma sección, así que un solo bloque bastaba; desde `#478` mirar el sujeto equivocado
        // dejaría uno de los dos recortes sin validar y sus casos pasarían en el vacío.
        $this->assertStringContainsString('zone-card__name', $zonas, 'no hay tarjetas de zona dentro de `#zones`');
        // ⚠️ La sección de atracciones dejó de tener selector y carrusel en `#482`: hoy es el
        // MOSAICO de cinco fotos. Lo que vigila su forma vive en `RideMosaicSectionTest`; aquí solo
        // se comprueba que el recorte sigue enmarcando algo real.
        $this->assertStringContainsString('mosaic__cell', $rides, 'no hay mosaico dentro de la sección de atracciones');

        // Y que de verdad son DOS secciones distintas: si alguien las volviera a fundir, los dos
        // recortes devolverían el mismo texto y todo lo de arriba seguiría pasando.
        $this->assertNotSame($zonas, $rides, 'zonas y atracciones vuelven a ser la misma sección');
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
     *
     * ⚠️⚠️ **`#528`: la guarda de la guarda ya NO busca un `<x-site.ilu>`**, porque desde entonces no
     * lo pinta ninguna vista —la silueta de la banda vieja de `/cumpleanos` era la última, y la página
     * rehecha no lleva dibujo—. Buscarlo convertía un estado válido en un fallo. Lo que tiene que
     * probar es que el escáner LEE las vistas, y eso lo demuestra la cabecera de página, que pintan
     * todas las interiores (`#525`).
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
            'x-site.page-head', $vistas,
            'el corpus de vistas no contiene ni una cabecera de página: el escáner no está mirando lo '.
            'que cree, y este caso pasaría en vacío.',
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
