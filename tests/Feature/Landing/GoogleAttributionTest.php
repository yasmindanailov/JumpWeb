<?php

namespace Tests\Feature\Landing;

use App\Domain\Content\Contracts\OriginalText;
use App\Domain\Content\Contracts\Rating;
use App\Domain\Content\Contracts\ReviewSelection;
use App\Domain\Content\Contracts\SocialProof;
use App\Domain\Content\Contracts\Testimonial as TestimonialData;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * **LA ATRIBUCIÓN DE GOOGLE EN LA SECCIÓN 06** (`DECISIONES #494`, `specs/google-reviews.md` §4.6).
 *
 * ❗❗❗ **Esto NO es diseño: son requisitos de la política de Places que hasta hoy se incumplían**,
 * medidos contra `developers.google.com/maps/documentation/places/web-service/policies` el
 * 2026-09-10 y contra la API real del parque (HTTP 200 el mismo día):
 *
 *  1. *«When displaying Places API data **without a Google Map**, you must include the Google
 *     logo»* — y aquí ese supuesto es el caso NORMAL: el mapa de «Visítanos» nace bloqueado hasta
 *     que se aceptan cookies de terceros y la chapa de la cifra se sirve sin ellas (`#491`).
 *  2. *«Each photo and review includes an author attribution (author's avatar image, name, **and
 *     profile link**)»* — se tenían las dos primeras.
 *  3. *«Make end users aware when a review has been **translated** from its original language»* —
 *     medido: las dos reseñas del parque están en español, así que en **inglés y francés** Google
 *     devuelve las dos traducidas. Dos de los tres idiomas del sitio.
 *  4. *«**Visually distinguish** Google Maps Platform Content from other content»*.
 *  5. *«**Don't misrepresent** Google Maps by attributing it with non-Google Maps Platform
 *     content»* — la que gobierna todo lo demás, porque el caso frecuente es el CRUCE.
 *
 * ❗❗❗ **Y una cosa que el owner pidió y NO se puede hacer**: escribir «verificado por Google». Su
 * propia documentación dice *«Reviews aren't verified by Google»*. Hay caso que lo prohíbe.
 *
 * ⚠️⚠️ **EL MODO DE FALLO QUE MÁS PERSIGUE ESTE FICHERO NO ES QUE SE ROMPA: ES QUE SE «ARREGLE».**
 * Un logotipo de tercero en medio de una landing white-label invita a armonizarlo —recolorearlo con
 * el token de marca, moverlo a la cabecera «para que se vea mejor», ponerlo también sobre las
 * opiniones propias «por coherencia»—. Ninguna de esas tres rompe un test de conducta, ninguna la
 * enseña una captura, y las tres incumplen.
 */
class GoogleAttributionTest extends TestCase
{
    use RefreshDatabase;

    /** Los dos ficheros del paquete oficial de atribución. */
    private const ASSETS = [
        'paper' => 'public/images/providers/google-maps-gray.svg',
        'ink' => 'public/images/providers/google-maps-white.svg',
    ];

    private const COMPONENT = 'resources/views/components/site/google-attribution.blade.php';

    private const SHEET = 'public/css/landing.css';

    /**
     * **La huella de la geometría OFICIAL**: sha1 de los atributos `d` concatenados en el orden del
     * fichero, tomada del paquete que Google publica en
     * `developers.google.com/static/maps/documentation/images/Google_Maps_Attribution_Assets.zip`
     * (descargado el 2026-09-10).
     *
     * ⚠️ **Va el NÚMERO y no una lista de nombres**, que es la doctrina de `GoogleButtonBrandingTest`
     * y de `IconSetAnatomyTest`: así un redibujo «que se ve igual» pone esto en rojo. Si Google
     * publica una marca nueva, quien la traiga cambia la constante A SABIENDAS.
     */
    private const OFFICIAL_GEOMETRY = '58740ece3aa3b1f0e72e9759eb4d567a2eb2ccc6';

    /** El color que Google publica para cada variante. No son tema: son suyos. */
    private const OFFICIAL_FILLS = ['paper' => '#5E5E5E', 'ink' => '#FFFFFF'];

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Que las fuentes se lean de verdad.** Sin esto, un renombrado deja todo lo de abajo
     * comparando cadenas vacías y en verde — que es como este repo perdió `#113`.
     */
    public function test_the_scan_reads_its_sources(): void
    {
        foreach ([...array_values(self::ASSETS), self::COMPONENT, self::SHEET] as $ruta) {
            $this->assertFileExists(base_path($ruta));
            $this->assertNotSame('', trim($this->read($ruta)), "`{$ruta}` se lee vacío.");
        }

        // Control positivo: el extractor SACA dibujo de estos ficheros.
        $this->assertCount(2, $this->paths(self::ASSETS['paper']),
            'el extractor de `<path>` ha dejado de ver el dibujo del logotipo.');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  1 · El asset es el suyo, sin tocar, y se puede PINTAR
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_the_mark_is_googles_own_drawing_in_both_variants(): void
    {
        foreach (self::ASSETS as $superficie => $ruta) {
            $paths = $this->paths($ruta);

            $this->assertSame(
                self::OFFICIAL_GEOMETRY,
                sha1(implode('', array_column($paths, 'd'))),
                "El dibujo de `{$ruta}` ya no es el de Google.\n".
                "▶ Se trae del paquete oficial de atribución y NO se redibuja: `#211` midió lo que\n".
                "  cuesta reconstruir a ojo un logotipo de marca (29 % de píxeles distintos).\n".
                '▶ Si Google ha cambiado su marca, actualiza `OFFICIAL_GEOMETRY` diciendo de dónde.'
            );

            $this->assertSame(
                [self::OFFICIAL_FILLS[$superficie]],
                array_values(array_unique(array_column($paths, 'fill'))),
                "`{$ruta}` ya no lleva el color que Google publica para esa variante. Sus\n".
                'directrices prohíben recolorear la marca: el color se elige cambiando de FICHERO.'
            );
        }
    }

    /**
     * ❗❗❗ **LAS DOS VARIANTES COMPARTEN GEOMETRÍA, Y ESO ES UNA TRAMPA CONOCIDA.**
     *
     * `#275` la pagó con el logotipo del cliente: *«la misma geometría vive en DOS sitios — arreglar
     * solo uno la deja BLANCA y no falla nada»*. Aquí pasaría igual: alguien actualiza el asset de
     * papel y no el de tinta, y el logotipo queda de un dibujo en la tarjeta y de otro en la chapa
     * **sin que ninguna suite se entere**. Este caso las ata.
     */
    public function test_both_variants_are_the_same_drawing(): void
    {
        $huellas = [];

        foreach (self::ASSETS as $superficie => $ruta) {
            $huellas[$superficie] = sha1(implode('', array_column($this->paths($ruta), 'd')));
        }

        $this->assertSame($huellas['paper'], $huellas['ink'],
            "Las dos variantes del logotipo han dejado de ser el mismo dibujo.\n".
            '▶ Google publica un único logotipo en varios colores: si cambia, se cambian LAS DOS.');
    }

    /**
     * ❗❗❗ **QUE EL SVG SEA XML BIEN FORMADO, Y ESTE CASO NACIÓ DE UN DEFECTO REAL.**
     *
     * Al instalar los assets se les puso una cabecera de procedencia que citaba tokens CSS con su
     * prefijo de dos guiones. **XML prohíbe esa secuencia dentro de un comentario** —a diferencia de
     * HTML, que la tolera—, y un SVG servido como `image/svg+xml` se parsea como XML estricto.
     *
     * ⚠️⚠️ **Lo que lo hace peligroso es cómo falla**: el fichero seguía respondiendo **HTTP 200 con
     * sus 7,6 KB**, el marcado seguía siendo correcto y **la caja seguía midiendo 98×18**, porque los
     * atributos `width`/`height` del `<img>` reservan el hueco. O sea: **el logotipo estaba invisible
     * y ni la suite ni una medición de geometría lo veían.** Lo delató `naturalWidth`, que valía 0.
     * ▶ *Que el fichero llegue y mida bien no es que se pinte* — la lección de `#263` por otra puerta.
     */
    public function test_the_mark_is_well_formed_xml_and_can_actually_be_painted(): void
    {
        foreach (self::ASSETS as $ruta) {
            $previo = libxml_use_internal_errors(true);
            libxml_clear_errors();

            $doc = simplexml_load_string($this->read($ruta));
            $errores = libxml_get_errors();

            libxml_clear_errors();
            libxml_use_internal_errors($previo);

            $this->assertNotFalse($doc,
                "`{$ruta}` NO es XML bien formado, así que el navegador no lo pinta —aunque se sirva\n".
                "con HTTP 200 y la caja siga midiendo 98×18—.\n".
                '▶ Causa habitual: dos guiones seguidos dentro del comentario de cabecera (un token '.
                "CSS citado con su prefijo).\n".
                '▶ libxml dice: '.trim($errores[0]->message ?? 'sin detalle')
            );
        }
    }

    /**
     * **Ni `currentColor` ni ninguna otra puerta para recolorear la marca de un tercero.**
     *
     * Es la mitad que explica por qué esta pieza NO es un icono del set: `IconSetAnatomyTest` exige
     * exactamente lo contrario, y por eso vive fuera de `views/components/icons`, como FICHERO.
     */
    public function test_the_mark_cannot_be_recoloured(): void
    {
        foreach (self::ASSETS as $ruta) {
            // La cabecera SÍ nombra `currentColor` para explicar por qué no lo lleva: se desnuda
            // antes de mirar, o la guarda acusaría a su propia explicación.
            $desnudo = (string) preg_replace('/<!--.*?-->/s', '', $this->read($ruta));

            $this->assertStringNotContainsString('currentColor', $desnudo,
                "`{$ruta}` puede heredar color del tema: es la marca de un tercero, no un icono del set.");

            $this->assertDoesNotMatchRegularExpression('/\bvar\(\s*--/', $desnudo,
                "Un token del tema dentro de `{$ruta}` la haría cambiar de color por instalación.");
        }
    }

    /** **El fichero no trae nada activo.** Se sirve por su propia URL y ahí sí se puede navegar. */
    public function test_the_mark_carries_nothing_active(): void
    {
        foreach (self::ASSETS as $ruta) {
            $svg = $this->read($ruta);

            foreach (['<script', '<foreignObject', 'javascript:', 'xlink:href="http', 'href="http'] as $aguja) {
                $this->assertStringNotContainsStringIgnoringCase($aguja, $svg,
                    "`{$ruta}` trae `{$aguja}`: un SVG servido por su URL propia no puede llevar nada activo.");
            }

            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $svg,
                "Hay un manejador `on…=` dentro de `{$ruta}`.");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  2 · La piel son los valores de la GUÍA de Google, no los del tema
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **El alto cae en el rango que Google publica (16–19 px) y la proporción no se puede romper.**
     *
     * ⚠️ Los dos ejes van fijos a propósito: con solo el alto, un `img{max-width}` heredado o un
     * contenedor estrecho lo encogen a lo ancho y **lo deforman**, que es lo primero que sus
     * directrices prohíben.
     */
    public function test_the_skin_keeps_googles_published_size_and_ratio(): void
    {
        $regla = $this->cssRule('.gmaps-attr');

        preg_match('/height:\s*(\d+)px/', $regla, $alto);
        $this->assertNotEmpty($alto, '`.gmaps-attr` ya no declara un alto en píxeles.');
        $this->assertGreaterThanOrEqual(16, (int) $alto[1],
            'El logotipo baja del alto MÍNIMO que publica Google (16 px).');
        $this->assertLessThanOrEqual(19, (int) $alto[1],
            'El logotipo pasa del alto MÁXIMO que publica Google (19 px).');

        $this->assertMatchesRegularExpression('/width:\s*\d+px/', $regla,
            "`.gmaps-attr` ha perdido el ancho fijo: sin él un contenedor estrecho lo DEFORMA, que es\n".
            'lo que «Maintain the aspect ratio of the logo to prevent distortion» prohíbe.');

        $this->assertStringContainsString('flex: none', $regla,
            '`.gmaps-attr` puede encogerse dentro de un flex y bajar del alto mínimo.');
    }

    /**
     * ⚠️⚠️ **La que de verdad protege**: que nadie «armonice» el logotipo con el tema del cliente.
     *
     * Un `filter`, un `opacity` o un `var(--…)` aquí no rompen ningún test de conducta, no los enseña
     * ninguna captura y hacen que la marca de Google cambie con cada instalación.
     */
    public function test_the_skin_does_not_derive_from_the_installations_palette(): void
    {
        $regla = $this->cssRule('.gmaps-attr');

        foreach (['--action', '--zone-1', '--zone-2', '--on-brand', '--fg', '--money'] as $token) {
            $this->assertStringNotContainsString($token, $regla,
                "`.gmaps-attr` lee `{$token}`: eso hace que la marca de Google cambie de color según\n".
                'la instalación. Sus colores son suyos y no son tema.');
        }

        foreach (['filter:', 'opacity:', 'mix-blend-mode'] as $palanca) {
            $this->assertStringNotContainsString($palanca, $regla,
                "`.gmaps-attr` declara `{$palanca}`: es una forma de alterar la marca sin tocar su color.");
        }
    }

    /**
     * **«Ver en Google» va SIEMPRE a la derecha de su fila** (`[DECIDIDO owner, 2026-09-10]`).
     *
     * ⚠️⚠️ **Y por eso es `margin-left: auto` y no `justify-content: space-between`**: el botón «Ver
     * más» solo aparece cuando el texto se recorta —se decide MIDIENDO, no contando caracteres—, así
     * que con una opinión corta la fila tiene **un solo hijo** y `space-between` lo dejaría a la
     * izquierda. Es el caso normal, no un borde: la reseña que sale hoy en la portada no lo tiene.
     */
    public function test_the_google_link_is_pinned_to_the_right_even_when_its_alone(): void
    {
        $regla = $this->cssRule('.rev__acts > .rev__more');

        $this->assertStringContainsString('margin-left: auto', $regla,
            "«Ver en Google» ha dejado de anclarse a la derecha por sí mismo.\n".
            '▶ Con `space-between`, una opinión sin «Ver más» lo devuelve a la izquierda.');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  3 · Dónde sale, y sobre todo dónde NO
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ❗❗❗ **LA INVARIANTE QUE MANDA: NUNCA HAY DATO DE PLACES SIN UNA MARCA DE GOOGLE A LA VISTA.**
     *
     * ⚠️⚠️ **Sustituye a «la chapa lleva siempre su logotipo», que es lo que este caso decía hasta
     * que el owner pidió retirarlo** (`#494`: *«el icono de Google Maps debajo del titular lo
     * quitamos»*). No se relajó la guarda: **cambió la premisa y se reescribió** —el precedente de
     * `#324`—, y la propiedad que queda es más fuerte que la que sustituye, porque cubre las dos
     * combinaciones en vez de una.
     *
     * La regla es de la política: *«When displaying Places API data without a Google Map, you must
     * include the Google logo»*. Da igual dónde salga; lo que no puede pasar es que no salga.
     */
    public function test_places_data_is_never_shown_without_a_google_mark(): void
    {
        foreach (['google', 'cms'] as $fuente) {
            $this->conGoogle(rating: true, opiniones: $fuente);

            $this->assertStringContainsString('google-maps', $this->seccion(),
                "Con la chapa de Google y opiniones «{$fuente}», la sección enseña dato de Places\n".
                "SIN una sola marca de Google en toda la página.\n".
                '▶ «When displaying Places API data without a Google Map, you must include the Google logo».');
        }
    }

    /**
     * **La chapa lleva su atribución, debajo de las estrellas** (`[owner, 2026-09-10]`).
     *
     * ⚠️ Es dato de Places y esta vista no enseña ningún mapa de Google: el supuesto exacto de
     * *«you must include the Google logo»*. Sin esto, el CRUCE —chapa de Google sobre opiniones
     * propias, que es el caso frecuente— dejaría la cifra sin una sola marca de Google.
     */
    public function test_the_score_badge_carries_the_google_logo(): void
    {
        foreach (['google', 'cms'] as $fuente) {
            $this->conGoogle(rating: true, opiniones: $fuente);

            $this->assertStringContainsString('google-maps-white.svg', $this->trozo($this->seccion(), 'rev-score'),
                "Con opiniones «{$fuente}», la chapa de la cifra no lleva el logotipo de Google Maps.");
        }
    }

    /** Sobre tinta va la variante blanca y sobre papel la gris: lo elige el MARCADO, no el tema. */
    public function test_each_surface_gets_the_variant_google_publishes_for_it(): void
    {
        $this->conGoogle(rating: true, opiniones: 'google');
        $seccion = $this->seccion();

        $this->assertStringContainsString('google-maps-white.svg', $this->trozo($seccion, 'rev-score'),
            'La chapa va sobre tinta: le toca la variante blanca.');

        $this->assertStringContainsString('google-maps-gray.svg', $this->trozo($seccion, 'rev__card'),
            'La tarjeta va sobre papel: le toca la variante gris.');
    }

    /**
     * ❗❗❗ **EL CASO QUE MÁS PROTEGE: EL CRUCE.**
     *
     * La chapa y las opiniones **no vienen de la misma fuente**, y el caso frecuente es justamente
     * ése —la cifra se sirve sin consentimiento y las reseñas no—. Con chapa de Google encima de
     * opiniones propias, el logotipo tiene que salir **en la chapa y en ninguna otra parte**: sobre
     * una opinión que escribió el parque sería *«misrepresent Google Maps by attributing it with
     * non-Google Maps Platform content»*.
     *
     * ▶ Es el mismo defecto que la captura cazó en `#491` con la entradilla, pero peor: aquél era una
     * frase inexacta y éste es un incumplimiento de marca.
     */
    public function test_with_a_google_score_over_own_reviews_the_logo_stays_in_the_badge(): void
    {
        $this->conGoogle(rating: true, opiniones: 'cms');
        $seccion = $this->seccion();

        $this->assertStringContainsString('google-maps-white.svg', $this->trozo($seccion, 'rev-score'),
            'La chapa sigue siendo de Google y necesita su atribución.');

        $tarjetas = $this->trozo($seccion, 'rev__card');

        $this->assertStringNotContainsString('google-maps', $tarjetas,
            "Una opinión PROPIA sale con el logotipo de Google encima.\n".
            "▶ Es el cruce, y es el caso frecuente: la cifra se sirve sin consentimiento y las\n".
            "  reseñas no, así que lo normal es chapa de Google sobre opiniones del parque.\n".
            '▶ «Don\'t misrepresent Google Maps by attributing it with non-Google Maps Platform content».');
    }

    /**
     * **Sin nada de Google, la sección no nombra a Google por ninguna parte.**
     *
     * ⚠️ Incluye la nota de política: una frase sobre cómo Google gestiona sus reseñas, puesta sobre
     * contenido propio, afirma que ese contenido viene de Google.
     */
    public function test_with_no_google_at_all_nothing_of_googles_appears(): void
    {
        $this->conGoogle(rating: false, opiniones: 'cms');
        $seccion = $this->seccion();

        $this->assertStringNotContainsString('google-maps', $seccion,
            'La sección pinta el logotipo de Google sin servir un solo dato suyo.');

        $this->assertStringNotContainsString(__('landing.reviews.google_policy'), $seccion,
            'La nota sobre la política de Google sale sin que haya nada de Google en la sección.');
    }

    /**
     * ❗❗❗ **EL LOGOTIPO NO PUEDE SUBIR A LA CABECERA DE LA SECCIÓN.**
     *
     * Es el sitio al que lo mueve cualquiera que quiera «que se vea más», y es justo el que la
     * política descarta: la atribución va *«within the same visual container»* que el dato que
     * acredita. Arriba abarcaría también las opiniones propias.
     */
    public function test_the_logo_never_sits_in_the_section_header(): void
    {
        $this->conGoogle(rating: true, opiniones: 'google');

        $cabecera = $this->trozo($this->seccion(), 'sec-head');

        $this->assertStringNotContainsString('google-maps', $cabecera,
            "El logotipo ha subido a la cabecera de la sección.\n".
            "▶ Desde ahí acredita TODO lo que hay debajo, y debajo puede haber opiniones propias.\n".
            '▶ La atribución va dentro del contenedor visual del dato que acredita.');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  4 · La atribución del AUTOR, completa
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Avatar, nombre Y enlace al perfil** — las tres, que es lo que R3 pide y hasta `#494` eran dos.
     */
    public function test_a_google_review_credits_its_author_with_everything_available(): void
    {
        $this->conGoogle(rating: true, opiniones: 'google');
        $tarjeta = $this->trozo($this->seccion(), 'rev__card');

        $this->assertStringContainsString('lh3.googleusercontent.com', $tarjeta, 'falta el avatar del autor');
        $this->assertStringContainsString('Ana Ejemplo', $tarjeta, 'falta el nombre del autor');
        $this->assertStringContainsString('maps.google.com/contrib', $tarjeta,
            "Falta el enlace al PERFIL del autor.\n".
            '▶ «author attribution (author\'s avatar image, name, and profile link)».');
    }

    /**
     * El dato del perfil nunca acaba en un `href` con un esquema raro (`SEC-07`): la vista tampoco lo pinta si le
     * llega. ⚠️ La mitad que probaba el SANEO en el servicio de Places se retiró con Places (`#771`, `§3.quater`: su
     * sujeto se fue); la del Perfil de Empresa lo sanea donde nace (`IncomingGoogleReview`).
     */
    public function test_a_profile_url_with_a_strange_scheme_never_reaches_the_dom(): void
    {
        $this->conGoogle(rating: true, opiniones: 'google', perfilRaro: true);
        $this->assertStringNotContainsString('javascript:', $this->seccion());
    }

    /** Una opinión PROPIA no tiene perfil en ninguna parte, así que su nombre no es enlace. */
    public function test_an_own_review_does_not_link_its_author_anywhere(): void
    {
        $this->conGoogle(rating: false, opiniones: 'cms');

        $this->assertDoesNotMatchRegularExpression('~<p class="rev__author">\s*<a~', $this->seccion(),
            'El nombre de una opinión propia es un enlace: promete una ficha que no existe.');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  5 · La traducción
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Una reseña traducida lo dice, y el original viaja servido.**
     *
     * ⚠️ El aviso va como TEXTO, no dentro del botón: sin JavaScript el botón no se pinta y el aviso
     * —que es lo obligatorio— tiene que salir igual.
     */
    public function test_a_translated_review_says_so_and_serves_the_original(): void
    {
        $this->conGoogle(rating: true, opiniones: 'google', traducida: true);
        $tarjeta = $this->trozo($this->seccion(), 'rev__card');

        $this->assertStringContainsString('rev__xlat-note', $tarjeta,
            "Una reseña traducida no avisa de que lo está.\n".
            '▶ «Make end users aware when a review has been translated from its original language».');

        // ⚠️⚠️ **Y que el aviso se VEA, no solo que esté.** Una mutación que le ponía `hidden` pasaba
        // en verde: el marcado seguía ahí y la aserción de arriba lo encontraba. *Que la pieza llegue
        // no es que se vea* — la lección de `#263`, y aquí lo que se esconde es un requisito.
        $this->assertDoesNotMatchRegularExpression('/<p class="rev__xlat"[^>]*\b(hidden|x-cloak)\b/', $tarjeta,
            'El aviso de traducción se sirve OCULTO: existe en el marcado y no lo lee nadie.');

        $this->assertStringContainsString('Texto original sin traducir', $tarjeta,
            'El texto sin traducir no viaja en el marcado: no hay forma de verlo.');

        // ⚠️⚠️ **El idioma del fixture NO es el de la página ni el de las reseñas reales, a
        // propósito.** Con `es` —lo natural aquí— una mutación que clavara `lang="es"` en la
        // plantilla pasaba en verde: el valor esperado coincidía con el valor clavado. Con `de` el
        // atributo solo puede venir del DATO.
        $this->assertStringContainsString('lang="de"', $tarjeta,
            'El original no declara SU idioma: un lector de pantalla lo leería con la voz equivocada.');
    }

    /** **Sin traducción no se avisa de nada**, o la reseña se anunciaría como traducida de sí misma. */
    public function test_an_untranslated_review_says_nothing_about_translation(): void
    {
        $this->conGoogle(rating: true, opiniones: 'google', traducida: false);

        $this->assertStringNotContainsString('rev__xlat', $this->trozo($this->seccion(), 'rev__card'),
            'Una reseña que NO está traducida anuncia que lo está.');
    }

    /**
     * ⚠️⚠️ **La señal es el IDIOMA, nunca comparar los dos textos.**
     *
     * Google devuelve `originalText` SIEMPRE, traducida o no: en español los dos vienen con
     * `languageCode: es` y el mismo contenido. «Hay `originalText`» no significa «está traducida».
     */
    public function test_the_translation_signal_is_the_language_code_and_not_the_presence_of_original(): void
    {
        $mismo = new TestimonialData(
            text: 'Igual', author: 'A', rating: 5, when: null, url: null,
            source: TestimonialData::SOURCE_GOOGLE,
        );

        $this->assertFalse($mismo->isTranslated(),
            'Sin original, una reseña se declara traducida.');

        $traducida = new TestimonialData(
            text: 'Same', author: 'A', rating: 5, when: null, url: null,
            source: TestimonialData::SOURCE_GOOGLE,
            originalText: new OriginalText('Igual', 'es'),
        );

        $this->assertTrue($traducida->isTranslated());
    }

    /**
     * ⚠️⚠️ **`ext-intl` NO es un requisito declarado de este producto**, así que nombrar el idioma
     * tiene que poder fallar hacia lo seguro: se avisa igual, sin el nombre.
     */
    public function test_naming_the_language_is_optional_and_never_prints_a_raw_code(): void
    {
        $this->assertNull((new OriginalText('x', 'zzz'))->languageName(),
            'Un código de idioma desconocido se imprimiría en crudo: «Traducida del zzz».');

        if (class_exists(\Locale::class)) {
            $this->assertSame('español', (new OriginalText('x', 'es'))->languageName());
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  6 · Lo que NO se puede decir
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ❗❗❗ **«VERIFICADO POR GOOGLE» NO SE ESCRIBE, Y NO ES UN MATIZ.**
     *
     * Es lo que el owner pidió con esas palabras (`#494`), y la propia documentación de Google dice
     * literalmente lo contrario de sus reseñas: *«Reviews aren't verified by Google, but Google
     * checks for and removes fake content when it's identified»*. Ponerlo afirmaría en su nombre algo
     * que ellos niegan.
     *
     * ⚠️ Este caso existe porque la petición era razonable y volverá a hacerse: sin él, el siguiente
     * que la reciba la implementa y nada se pone rojo.
     */
    public function test_the_section_never_claims_google_verified_anything(): void
    {
        $this->conGoogle(rating: true, opiniones: 'google');
        $seccion = mb_strtolower($this->seccion());

        foreach (['verificado por google', 'verified by google', 'vérifié par google'] as $prohibida) {
            // ⚠️ La frase de la política CONTIENE la prohibida en su forma negada («aren't verified
            // by Google»): se comprueba sobre el texto sin ella, o la guarda acusaría a la frase que
            // existe justamente para decir la verdad.
            $sinPolitica = str_replace(mb_strtolower(__('landing.reviews.google_policy')), '', $seccion);

            $this->assertStringNotContainsString($prohibida, $sinPolitica,
                "La sección afirma que Google verifica las reseñas.\n".
                '▶ Su documentación dice lo contrario: «Reviews aren\'t verified by Google».');
        }
    }

    /** Y la nota que SÍ se escribe sale cuando hay algo de Google, con su redacción. */
    public function test_googles_review_policy_is_stated_when_theres_google_content(): void
    {
        $this->conGoogle(rating: true, opiniones: 'google');

        $this->assertStringContainsString(__('landing.reviews.google_policy'), $this->seccion(),
            '▶ «Inform end users of Google\'s review policy when displaying reviews and average rating».');
    }

    /**
     * **Con solo la chapa la nota sigue haciendo falta**: la política nombra expresamente la
     * valoración media, no solo las reseñas.
     */
    public function test_the_policy_note_also_covers_the_score_on_its_own(): void
    {
        $this->conGoogle(rating: true, opiniones: 'cms');

        $this->assertStringContainsString(__('landing.reviews.google_policy'), $this->seccion(),
            'Con chapa de Google sobre opiniones propias, la nota de política desaparece.');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Utilidades
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * Monta la sección con la fuente que se le pida, sin tocar Google ni la caché.
     *
     * ⚠️ **Se dobla el CONTRATO y no el servicio**: es lo que la vista conoce, y así el caso no
     * depende de si hay caché, clave o consentimiento.
     */
    private function conGoogle(bool $rating, string $opiniones, bool $traducida = false, bool $perfilRaro = false): void
    {
        $this->seed(LandingContentSeeder::class);

        $cifra = $rating ? new Rating(4.8, 320, 'https://maps.google.com/?cid=1', TestimonialData::SOURCE_GOOGLE) : null;

        $lista = $opiniones === 'google'
            ? collect([new TestimonialData(
                text: 'Translated text.',
                author: 'Ana Ejemplo',
                rating: 5,
                when: 'hace 2 meses',
                url: 'https://maps.google.com/reviews/1',
                source: TestimonialData::SOURCE_GOOGLE,
                avatarUrl: 'https://lh3.googleusercontent.com/a/foto',
                authorUrl: $perfilRaro ? null : 'https://maps.google.com/contrib/1',
                originalText: $traducida ? new OriginalText('Texto original sin traducir', 'de') : null,
            )])
            // ⚠️ `Testimonial` no tiene factory (la siembra a mano es lo que hace `ReviewsSectionTest`):
            // aquí basta con el DTO, porque lo que se dobla es el CONTRATO, no el modelo.
            : collect([1, 2])->map(fn (int $i) => new TestimonialData(
                text: "Opinión propia número {$i}.",
                author: "Persona {$i}",
                rating: 5,
                when: 'hace 2 meses',
                url: null,
                source: TestimonialData::SOURCE_CMS,
            ))->values();

        $this->app->bind(SocialProof::class, fn () => new class($cifra, $lista) implements SocialProof
        {
            public function __construct(private ?Rating $cifra, private Collection $lista) {}

            public function rating(): ?Rating
            {
                return $this->cifra;
            }

            public function testimonials(): Collection
            {
                return $this->lista;
            }

            public function reviewsAwaitConsent(): bool
            {
                return false;
            }

            // ⚠️ Los dos que añadió `#732` (T2·6 de `google-business-profile.md` §4.3·9). El doble
            // finge la fuente de **Places**, que es de la que va este fichero: sus opiniones SÍ
            // necesitan permiso y **no filtra por estrellas**, así que no declara selección.
            public function reviewsNeedConsent(): bool
            {
                return true;
            }

            public function selection(): ?ReviewSelection
            {
                return null;
            }
        });
    }

    /** El HTML de la sección 06, acotado — nunca la página entera (`#295`). */
    private function seccion(): string
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        $desde = strpos($html, '<section id="reviews"');
        $this->assertNotFalse($desde, 'la sección 06 no se pinta: la guarda estaría vigilando el vacío');

        $hasta = strpos($html, '</section>', $desde);

        return substr($html, $desde, $hasta - $desde);
    }

    /**
     * Los ELEMENTOS de la sección que llevan una clase, concatenados con su contenido.
     *
     * ⚠️⚠️ **Acota al elemento contando su anidamiento, y la primera versión NO lo hacía**: cortaba
     * «desde la clase hasta la siguiente aparición de esa clase», así que una clase que sale UNA vez
     * —`sec-head`— se llevaba **el resto de la sección entera**, chapa y tarjetas incluidas.
     * ▶ Es la trampa de `#314` en pequeño: *un localizador que depende de qué viene DESPUÉS no acota
     * un elemento, acota un tramo de documento*. Lo cazó el caso de la cabecera, pero mientras tanto
     * los otros pasaban **por accidente**: buscaban el logotipo en un trozo que contenía la sección
     * casi completa, así que habrían pasado con el logotipo en cualquier sitio.
     */
    private function trozo(string $seccion, string $clase): string
    {
        $patron = '/<(\w+)\b[^>]*\sclass="[^"]*(?<![\w-])'.preg_quote($clase, '/').'(?![\w-])[^"]*"[^>]*>/';

        preg_match_all($patron, $seccion, $m, PREG_OFFSET_CAPTURE);

        $this->assertNotEmpty($m[0], "`{$clase}` no aparece en la sección: la guarda estaría vigilando el vacío");

        $trozos = '';

        foreach ($m[0] as $i => [$apertura, $inicio]) {
            $etiqueta = $m[1][$i][0];

            // Elementos vacíos: no tienen cierre que buscar.
            if (in_array($etiqueta, ['img', 'input', 'br', 'hr'], true)) {
                $trozos .= $apertura;

                continue;
            }

            $trozos .= substr($seccion, $inicio, $this->largoDelElemento($seccion, $inicio, $etiqueta, strlen($apertura)));
        }

        return $trozos;
    }

    /** Cuánto ocupa el elemento que empieza en `$inicio`, contando aperturas y cierres del mismo tipo. */
    private function largoDelElemento(string $html, int $inicio, string $etiqueta, int $largoApertura): int
    {
        $profundidad = 1;
        $cursor = $inicio + $largoApertura;
        $abre = '/<'.$etiqueta.'\b/i';
        $cierra = '</'.$etiqueta.'>';

        while ($profundidad > 0) {
            $siguienteCierre = strpos($html, $cierra, $cursor);

            if ($siguienteCierre === false) {
                return strlen($html) - $inicio;
            }

            // ¿Cuántas aperturas del mismo tipo hay entre el cursor y ese cierre?
            $entre = substr($html, $cursor, $siguienteCierre - $cursor);
            $profundidad += preg_match_all($abre, $entre) - 1;
            $cursor = $siguienteCierre + strlen($cierra);
        }

        return $cursor - $inicio;
    }

    /** @return array<int,array{d:string,fill:string}> */
    private function paths(string $ruta): array
    {
        preg_match_all('/<path\b[^>]*>/', $this->read($ruta), $m);

        return array_map(function (string $tag): array {
            preg_match('/\sd="([^"]+)"/', $tag, $d);
            preg_match('/\sfill="([^"]+)"/', $tag, $f);

            return ['d' => $d[1] ?? '', 'fill' => $f[1] ?? ''];
        }, $m[0]);
    }

    private function cssRule(string $selector): string
    {
        $css = $this->read(self::SHEET);

        preg_match('/'.preg_quote($selector, '/').'\s*\{([^}]*)\}/', $css, $m);

        $this->assertNotEmpty($m, "`{$selector}` no tiene regla en ".self::SHEET);

        return $m[1];
    }

    private function read(string $ruta): string
    {
        return (string) file_get_contents(base_path($ruta));
    }
}
