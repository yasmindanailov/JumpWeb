<?php

namespace Tests\Feature\Theme;

use Tests\Support\ReadsSiteStylesheets;
use Tests\TestCase;

/**
 * **LA CINTA `C3`** (`specs/idioma-visual-heredado.md`, T2).
 *
 * «La banda inferior del mural: rótulo sobre tinta, girada, cruzando la página de lado a lado. En
 * la web se mueve despacio.» Sustituye a la marquesina heredada del cliente antiguo en `/servicios`.
 *
 * ⚠️⚠️ **Lo que se vigila aquí no es el aspecto: son las tres cosas que, al romperse, NO fallan.**
 * Una cinta con el interior del ancho justo sigue pintando —solo aparecen dos cuñas de papel en las
 * esquinas—; un bucle con la duración escrita a mano sigue moviéndose —solo que una instalación ya
 * no puede calmarlo—; y una banda que usara la fuente de rotulador seguiría legible —solo que
 * incumpliría la norma del propio cliente—.
 */
class BrandBandTest extends TestCase
{
    use ReadsSiteStylesheets;

    /**
     * **El giro EXIGE que el interior sea más ancho que su caja, y que alguien lo recorte.**
     *
     * Medido en navegador: una banda del ancho exacto de la ventana, girada, deja **dos cuñas de
     * papel** en las esquinas. Sus dos números —`width: 120%` y `margin-left: -10%`— existen para
     * eso, y el recorte lo pone el envoltorio.
     *
     * ⚠️ **Y el recorte NO puede ser `overflow-x` en `html`/`body`**: `#226` midió que eso **rompe
     * todos los `sticky`** del sitio, incluido el hero, que estuvo sin pegarse desde `#195`.
     */
    public function test_the_rotation_has_the_width_and_the_clip_that_make_it_possible(): void
    {
        $envoltorio = $this->rule('.brand-band');
        $interior = $this->rule('.brand-band__inner');

        $this->assertNotNull($envoltorio, 'la cinta no declara su envoltorio: esta guarda mira otra hoja');
        $this->assertNotNull($interior, 'la cinta no declara su interior');

        $this->assertMatchesRegularExpression(
            '/overflow:\s*hidden/', (string) $envoltorio,
            'el envoltorio de la cinta no recorta: la banda girada se saldrá por arriba y por abajo.',
        );

        $this->assertMatchesRegularExpression(
            '/transform:\s*rotate\(/', (string) $interior,
            'el interior de la cinta ya no gira: es el gesto entero de la pieza.',
        );

        // El interior tiene que ser MÁS ancho que su caja, o el giro deja cuñas de papel.
        //
        // ⚠️⚠️ **Esta aserción NACIÓ LAXA y lo demostró la mutación.** Estaba escrita como
        // `/width:\s*1[0-9]{2}%/`, y ese patrón **acepta `100%`** —que es exactamente el valor que
        // rompe la pieza—: la mutación de devolver el ancho justo pasaba en VERDE. Ahora se lee el
        // número y se compara. *Un patrón que describe la FORMA del valor no dice nada sobre el
        // valor; y aquí lo único que importa es que sea mayor que cien.*
        $this->assertMatchesRegularExpression(
            '/width:\s*([0-9.]+)%/', (string) $interior, 'el interior de la cinta no declara ancho en %',
        );
        preg_match('/width:\s*([0-9.]+)%/', (string) $interior, $ancho);

        $this->assertGreaterThan(
            100, (float) $ancho[1],
            'el interior de la cinta no es MÁS ancho que su caja (mide '.$ancho[1].' %): girado, deja '.
            'dos cuñas de papel en las esquinas. No falla nada, solo se ve mal.',
        );
    }

    /**
     * **El alto extra del envoltorio es GEOMETRÍA, no aire.**
     *
     * Sin él la cinta se recorta a sí misma: medido en navegador, **5 elementos cortados y el peor a
     * 22 px** en escritorio, con el rótulo de los extremos partido por el canto de la banda. El
     * número sale de `½ · ancho · sen(giro)` y con el interior al 120 % son `2,51vw` por lado.
     */
    public function test_the_wrapper_reserves_the_height_the_rotation_needs(): void
    {
        $envoltorio = (string) $this->rule('.brand-band');

        $this->assertMatchesRegularExpression(
            '/padding-block:\s*[0-9.]+vw/', $envoltorio,
            'el envoltorio de la cinta no reserva alto para el giro, y en cuanto la banda gire el '.
            'texto de los extremos saldrá cortado por su propio recorte.',
        );
    }

    /**
     * **La duración del bucle sale de un TOKEN, no de un literal.**
     *
     * Es lo que permite que una instalación lo calme sin reescribir `@keyframes` — el mismo trato
     * que `--dur-invite` y `--dur-switch`, y la razón de que exista la lista de ambientales de
     * `MotionScaleTest`. La marquesina que sustituye llevaba `30s` escritos a mano.
     */
    public function test_the_loop_reads_its_duration_from_an_ambient_token(): void
    {
        $pista = (string) $this->rule('.brand-band__track');

        $this->assertMatchesRegularExpression(
            '/animation:[^;]*var\(--dur-cinta\)/', $pista,
            'el bucle de la cinta no lee `--dur-cinta`: con la duración a mano, una instalación no '.
            'puede calmarla.',
        );
        $this->assertStringContainsString(
            'infinite', $pista,
            'el bucle de la cinta ha dejado de serlo, o `MotionScaleTest` dejará de tratarlo como '.
            'ambiental y le exigirá la escala de la interacción.',
        );
    }

    /**
     * **La cinta NO usa la fuente de rotulador, y es una norma del propio cliente.**
     *
     * Su paquete dice de `--font-accent`: *«Guiño: el eslogan, nada más»*, y su auditoría `T-02`
     * limita Permanent Marker a **una por página**. Medido: `/servicios` ya gasta la suya en el
     * eslogan del menú. Su `C3` la usa porque en su mockup la cinta **es** el eslogan; aquí lleva
     * los títulos que escribe el panel.
     */
    public function test_the_band_does_not_spend_the_pages_single_marker_font(): void
    {
        foreach (['.brand-band', '.brand-band__inner', '.brand-band__track', '.brand-band__item'] as $selector) {
            $this->assertStringNotContainsString(
                '--font-accent', (string) $this->rule($selector),
                "`{$selector}` usa la fuente de rotulador. Su norma `T-02` la limita a UNA por ".
                'página y `/servicios` ya la gasta en el eslogan del menú.',
            );
        }
    }

    /** El cuerpo de la regla con ese selector exacto, o `null`. */
    private function rule(string $selector): ?string
    {
        foreach ($this->siteRules() as $rule) {
            if ($rule['selector'] === $selector) {
                return $rule['body'];
            }
        }

        return null;
    }
}
