<?php

namespace Tests\Feature\Theme;

use Tests\TestCase;

/**
 * **LA TARJETA DE CONSENTIMIENTOS: que no vuelva a desbordar, y que el interruptor sea un
 * interruptor** (`specs/auth-con-google.md` §21.2, `#346`).
 *
 * ⚠️⚠️ **Nace de un defecto REAL que encontró el owner y que ninguna guarda podía ver**: al retirar
 * el consentimiento de marketing aparecía una **barra de scroll horizontal** en el cajón. Reproducido
 * en navegador con control — **0 px de desborde antes de pulsar, 82 px después**.
 *
 * ▶ **El mecanismo, y por qué es interesante**: `.account__consent-meta` llevaba `white-space: nowrap`
 * desde que su contenido era «fecha · versión», donde describía algo cierto —nada de eso se puede
 * partir sin quedar mal—. `#344` le añadió al final «retirado el 02/09/2026» y la línea pasó a medir
 * 418 px dentro de un carril de 380, **sin que fallara nada**.
 * ▶ *El `nowrap` no estaba mal: dejó de ser cierto cuando alguien alargó lo que envolvía.* Por eso lo
 * que se vigila aquí no es un valor, es **dónde vive**: lo indivisible es el TROZO, nunca la LÍNEA.
 *
 * ⚠️ La mitad de JavaScript —que la meta llegue en trozos y que un tipo repetido se colapse— la
 * vigila `resources/js/sidebar/account/privacy.test.js` con `node --test`. Aquí va la mitad de CSS y
 * la de marcado, que es la que aquel runner no puede ver.
 */
class ConsentCardTest extends TestCase
{
    private const SHEET = 'public/css/site.css';

    private const ZONE = 'resources/js/sidebar/account/zones/PrivacyZone.vue';

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_the_scan_reads_its_sources(): void
    {
        foreach ([self::SHEET, self::ZONE] as $path) {
            $this->assertFileExists(base_path($path));
        }

        // Control: el localizador de reglas SACA algo, y saca lo que dice sacar.
        $this->assertStringContainsString('white-space', $this->rule('.account__consent-part'));
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  1 · Lo indivisible es el TROZO, no la LÍNEA
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_the_meta_line_can_wrap_but_each_part_cannot(): void
    {
        $linea = $this->rule('.account__consent-meta');
        $trozo = $this->rule('.account__consent-part');

        $this->assertStringNotContainsString('nowrap', $linea,
            "`.account__consent-meta` vuelve a ser `nowrap`, y ése ES el defecto de `#346`.\n".
            "Medido en navegador: con la cláusula de retirada dentro, la línea mide 418 px en un\n".
            "carril de 380 → **82 px de desborde y barra de scroll horizontal en el cajón**.\n".
            '▶ Lo indivisible es cada TROZO (`.account__consent-part`), nunca la línea entera.'
        );

        $this->assertStringContainsString('nowrap', $trozo,
            "`.account__consent-part` ha dejado de ser `nowrap`: entonces una FECHA se puede partir\n".
            'por la mitad («02/09/» arriba y «2026» abajo), que es lo que el `nowrap` original evitaba.'
        );
    }

    /** Y la fila tiene que poder partirse, o rótulo y meta compiten por el mismo renglón. */
    public function test_the_row_itself_wraps(): void
    {
        $this->assertMatchesRegularExpression(
            '/flex-wrap:\s*wrap/', $this->rule('.account__consents li'),
            'La fila de un consentimiento ya no envuelve: sin eso crece hacia fuera del carril en '.
            'vez de partirse, que es la otra mitad del desborde de `#346`.'
        );
    }

    /**
     * **El separador lo dibuja la hoja, no el módulo.** Es lo que permite que un trozo ausente —una
     * versión vacía— no deje un «·» colgando, y por eso el módulo puede devolver una lista limpia.
     */
    public function test_the_separator_is_drawn_between_parts_by_the_sheet(): void
    {
        $css = $this->read(self::SHEET);

        $this->assertMatchesRegularExpression(
            '/\.account__consent-part \+ \.account__consent-part::before\s*\{[^}]*content/',
            $css,
            'El «·» entre trozos ha dejado de dibujarlo el CSS. Si vuelve a la cadena del módulo, '.
            'un trozo ausente deja un separador colgando y la línea vuelve a ser indivisible.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  2 · El interruptor es un interruptor
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * ⚠️⚠️ **`role="switch"` no es adorno.** Con `checkbox`, un lector de pantalla anuncia «casilla,
     * no marcada» y quien no ve espera un botón de «Guardar» que no existe: esto se guarda al
     * soltarlo. Es la diferencia entre una elección que se ENVÍA y un estado que se CAMBIA.
     */
    public function test_the_marketing_control_is_a_switch_and_not_a_checkbox(): void
    {
        $vue = $this->read(self::ZONE);

        // ⚠️⚠️ Se acota al CONTROL DE MARKETING, no al fichero. La primera versión de este caso
        // aseveraba «no hay ningún `<label class="check">` en la pantalla» y salió ROJA con el
        // producto sano: en esa misma zona vive la casilla del DESCARGO, que sí es una casilla —se
        // marca y se envía con un botón—. *Una guarda que acusa a lo que está bien está mal escrita.*
        $this->assertSame(1, preg_match('/<input\b[^>]*setMarketing[^>]*>/s', $vue, $m),
            'No hay exactamente un control enlazado a `setMarketing` en la pantalla de privacidad.');

        $control = $m[0];

        $this->assertStringContainsString('role="switch"', $control,
            'El control de marketing ha dejado de declararse `role="switch"`. Con `checkbox`, un '.
            'lector de pantalla anuncia «casilla, no marcada» y quien no ve espera un «Guardar» que '.
            'no existe: esto se guarda al soltarlo.'
        );

        $this->assertStringContainsString('class="switch__input"', $control,
            'El marketing ha vuelto a dibujarse como casilla. `[DECIDIDO owner, 2026-09-02]`: es un '.
            'interruptor, porque se guarda al soltarlo y no con un botón de enviar.'
        );
    }

    /**
     * ⚠️⚠️ **Encendido es `--ok`, no `--action`** — `#254`, y lo dijo `ActionFillTest` con el chip de
     * «Abierto ahora»: acción es el control que hace AVANZAR; «encendido» es un ESTADO. Si alguien lo
     * cambia al color de acción, el interruptor pasa a parecer un botón de comprar.
     */
    public function test_the_switch_paints_its_on_state_with_the_state_colour(): void
    {
        $encendido = $this->rule('.switch__input:checked');

        $this->assertStringContainsString('var(--ok)', $encendido,
            '`.switch__input:checked` ya no se pinta con `--ok`.');

        $this->assertStringNotContainsString('--action', $encendido,
            '«Encendido» se ha pintado con el color de ACCIÓN. Es un estado, no un control que avanza.');
    }

    /**
     * **El control es el propio `<input>`**, y de ahí depende el anillo de foco: `landing.css` tiene
     * una lista blanca CERRADA (`*:focus { outline: none }` + seis selectores), y
     * `input[type="checkbox"]:focus-visible` está dentro. Un input escondido a 0×0 con la pista
     * pintada al lado dibujaría el anillo sobre nada — la trampa de `#295`.
     */
    public function test_the_switch_is_the_input_itself_so_the_focus_ring_lands_on_it(): void
    {
        $regla = $this->rule('.switch__input');

        $this->assertMatchesRegularExpression('/appearance:\s*none/', $regla,
            'El interruptor ha dejado de dibujarse sobre el propio `<input>`.');

        foreach (['width', 'height'] as $eje) {
            $this->assertDoesNotMatchRegularExpression("/{$eje}:\\s*0(?![.\\d])/", $regla,
                "El `<input>` del interruptor se ha encogido a 0 en `{$eje}`: el anillo de foco ".
                'caería sobre una caja invisible y el control quedaría enfocable pero no accesible.');
        }

        // Y la lista blanca del foco sigue cubriendo a este control.
        $this->assertStringContainsString('input[type="checkbox"]:focus-visible',
            $this->read('public/css/landing.css'),
            'La lista blanca del foco ya no cubre las casillas, y el interruptor es una.');
    }

    /** El suelo táctil de `#264`: 44 px, puestos en la etiqueta como el chip de cuenta. */
    public function test_the_switch_row_keeps_the_44_floor(): void
    {
        $this->assertStringContainsString('min-height: var(--tap-min)', $this->rule('.switch'),
            'La fila del interruptor ha perdido el suelo táctil de 44 px (`#264`). La pista mide 26 '.
            'de alto: lo que se toca es la fila entera.');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Utilidades
    // ─────────────────────────────────────────────────────────────────────────────────

    private function read(string $path): string
    {
        return (string) file_get_contents(base_path($path));
    }

    /**
     * El cuerpo de una regla por selector EXACTO.
     *
     * ⚠️ Se ancla con la llave y se corta en la primera `}`, y el selector no puede ir precedido de
     * carácter de nombre: buscar por subcadena haría que `.switch__input` casara dentro de
     * `.switch__input:checked` — la trampa de `#264` (`width` casa dentro de `stroke-width`).
     */
    private function rule(string $selector): string
    {
        $pattern = '/(?<![\w.-])'.preg_quote($selector, '/').'\s*\{([^}]*)\}/';

        $this->assertSame(1, preg_match($pattern, $this->read(self::SHEET), $m),
            "No hay una regla `{$selector}` en `".self::SHEET.'`.');

        return $m[1];
    }
}
