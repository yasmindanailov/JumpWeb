<?php

namespace Tests\Feature\Theme;

use Tests\TestCase;

/**
 * **EL AVISO DE PRIVACIDAD ES UN AVISO, Y SU ENLACE SE TIENE QUE VER**
 * (`specs/auth-con-google.md` §7.1 y §21.4.3, T8·c, `#350`).
 *
 * La privacidad dejó de ser casilla en las dos altas porque el art. 13 del RGPD pide **informar**, no
 * que se acepte, y la base legal de una reserva es el contrato (art. 6.1.b). ▶ Ese razonamiento
 * **descansa entero en una cosa medible**: que el enlace a la política esté a la vista donde se crea
 * la cuenta. Si no se ve, no se ha informado — y entonces lo que se quitó no fue una casilla
 * redundante, fue el único sitio donde se enseñaba el documento.
 *
 * ⚠️⚠️ **Nace de un defecto REAL que solo vio la CAPTURA**, no la suite: hasta la T8·c el `<a>` de
 * este aviso se pintaba en `rgb(98,106,114)` —**el mismo color exacto que el párrafo**—, sin
 * subrayado y con peso 400. O sea, texto plano con una zona pulsable invisible de 336×32.
 * Venía de `#343`, cuando este tratamiento se estrenó en la pantalla de Google; nadie lo vio porque
 * allí era un aviso más y no el único camino al documento.
 *
 * ⚠️ **Lo que este fichero NO puede comprobar es el color computado**: para eso hace falta un
 * navegador (`storage/app/sonda-google-encima.mjs` y su hermana miden `getComputedStyle`). Lo que sí
 * fija —y es lo que se rompe en silencio— es que la regla EXISTA y que no diga lo mismo que el
 * párrafo que la envuelve.
 */
class PrivacyNoticeIsVisibleTest extends TestCase
{
    private const SHEET = 'public/css/site.css';

    /** Las dos altas. La de Google lo pinta desde `#343`; la de contraseña, desde la T8·c. */
    private const SCREENS = [
        'resources/js/sidebar/steps/RegisterForm.vue',
        'resources/js/sidebar/account/zones/GoogleSignupZone.vue',
    ];

    /**
     * **Guarda de la guarda**: que las fuentes se lean y que los anclajes existan. Sin esto, un
     * renombrado deja lo de abajo buscando en cadenas vacías y pasando en verde.
     */
    public function test_the_scan_reads_its_sources(): void
    {
        foreach ([self::SHEET, ...self::SCREENS] as $path) {
            $this->assertFileExists(base_path($path));
            $this->assertNotSame('', trim($this->read($path)), "`{$path}` se lee vacío.");
        }

        // Control positivo del localizador de reglas: sabe encontrar una que existe seguro.
        $this->assertNotSame('', trim($this->cssRule('.form__hint')));
    }

    /**
     * **Las dos altas lo pintan, y como AVISO.**
     *
     * ⚠️ Se exige el `<p class="form__hint">` y se prohíbe la casilla: devolverle el `<input>` no
     * rompería nada visible —la pantalla seguiría enseñando el enlace— pero reabriría la postura que
     * el owner cerró, y encima solo en una de las dos altas si alguien toca un fichero.
     */
    public function test_both_signups_paint_the_notice_and_not_a_checkbox(): void
    {
        foreach (self::SCREENS as $path) {
            $template = $this->template($path);

            // ⚠️⚠️ **Este localizador CAMBIÓ en `#566`**: pedía el `v-html`, que era la forma en que el
            // aviso llevaba su enlace DENTRO —los 20 px de la grieta 13—. Hoy el párrafo es texto y el
            // documento se abre desde su propia fila, que es lo que comprueba el caso de más abajo.
            $this->assertMatchesRegularExpression(
                '~<p\b[^>]*class="form__hint"[^>]*>\s*\{\{\s*a\(\'register\.privacy_notice\'\)\s*\}\}~',
                $template,
                "`{$path}` ya no pinta el aviso de privacidad como AVISO.\n".
                '▶ Es lo único que enseña la política donde se crea la cuenta (§7.1, art. 13 RGPD).'
            );

            $this->assertStringNotContainsString(
                'privacy_notice\')"></span>', $template,
                "`{$path}` ha devuelto la privacidad a una CASILLA.\n".
                "▶ `[DECIDIDO owner, 2026-09-02]` (T8·c): el RGPD pide informar, no que se acepte. Si\n".
                '  algún día se quiere la casilla, se reabre la decisión — no se repone por costumbre.'
            );
        }
    }

    /**
     * ⚠️⚠️ **LA de este fichero**: el enlace se distingue del texto que lo rodea.
     *
     * Se comprueban las DOS mitades y no una: el color por sí solo no basta si alguien lo apunta al
     * mismo gris del aviso, y el subrayado por sí solo se pierde en un párrafo gris de 12 px. Y se
     * prohíbe explícitamente `--fg-mute`, que es el color del párrafo: era exactamente el defecto.
     */
    public function test_the_link_inside_a_notice_looks_like_a_link(): void
    {
        // ⚠️⚠️ **Se mira `.form__hint button` desde `#566`, y NO es una relajación**: al sacar el enlace
        // de privacidad a su propia fila, esta regla se quedó sin `<a>` que vigilar… y al buscarle
        // sujeto apareció el que llevaba ahí desde siempre. `NoPasswordHint` pinta un `<button>` dentro
        // de una pista —«¿no tienes contraseña? · Recupérala»— **sin ninguna regla**: heredaba
        // `color: inherit` del párrafo y salía sin subrayado, o sea EXACTAMENTE el defecto que este
        // fichero existe para que no vuelva, una pantalla más allá y sin que nadie lo viera.
        // ▶ Los dos selectores comparten cuerpo, así que comprobar uno comprueba los dos.
        $rule = $this->cssRule('.form__hint button');

        $this->assertMatchesRegularExpression(
            '/text-decoration:\s*underline/', $rule,
            "El control dentro de una pista ha perdido el subrayado: en un párrafo gris es la mitad\n".
            'que de verdad lo separa del texto.'
        );

        $this->assertMatchesRegularExpression(
            '/color:\s*var\(--/', $rule,
            'El control dentro de una pista ha dejado de declarar color propio: heredaría el del aviso.'
        );

        $this->assertStringNotContainsString(
            '--fg-mute', $rule,
            "Se pinta con `--fg-mute`, que es **el color del párrafo que lo envuelve**.\n".
            '▶ Ése era el defecto exacto que este fichero existe para que no vuelva: medido en'.
            " navegador,\n  enlace y párrafo daban `rgb(98,106,114)` los dos."
        );
    }

    /**
     * **Y el documento se puede ABRIR: la política tiene su propio control en las dos altas** (`#566`).
     *
     * ⚠️⚠️ Ésta es la mitad que sostiene el razonamiento entero del fichero desde que el enlace salió
     * de la frase. El aviso informa; lo que hace que se haya **informado de verdad** es que el
     * documento esté a un toque. Un párrafo que menciona «nuestra política de privacidad» sin nada
     * que la abra cumple menos que la casilla que se quitó.
     *
     * ⚠️ Se comprueban las dos mitades: el rótulo (o la fila sale MUDA, el hueco de `#333`) y el
     * `href` (o la fila no lleva a ninguna parte, y eso lo pasa el gate en verde).
     */
    public function test_the_policy_has_its_own_control_in_both_signups(): void
    {
        foreach (self::SCREENS as $path) {
            $template = $this->template($path);

            $this->assertStringContainsString(
                "a('register.privacy_read')",
                $template,
                "`{$path}` no pinta el rótulo de la fila que abre la política."
            );

            $this->assertMatchesRegularExpression(
                '~<a\s[^>]*:href="(privacyUrl|urls\.privacy[^"]*)"[^>]*class="cal-more legal-more"~',
                $template,
                "`{$path}` no abre la política desde una fila con la receta compartida.\n".
                '▶ La URL viaja SUELTA (`urls.privacy`) y la forma la declara `.cal-more`: si esta fila '.
                'copia sus valores, se queda atrás en cuanto alguien ajuste la otra (`#562`).'
            );
        }
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(base_path($path));
    }

    /** El `<template>` del componente, sin el `<script setup>` — que cita estas claves al explicarlas. */
    private function template(string $path): string
    {
        $source = $this->read($path);
        $at = strpos($source, '<template>');

        $this->assertNotFalse($at, "`{$path}` ya no tiene `<template>`.");

        return substr($source, $at);
    }

    /**
     * El CUERPO de una regla, por su selector EXACTO.
     *
     * ⚠️ Se ancla con la llave y se corta en la primera `}`: buscar por subcadena haría que
     * `.form__hint` casara también dentro de `.form__hint a`, que es la trampa de `#264` (`width`
     * casa dentro de `stroke-width`) aplicada a un selector.
     */
    private function cssRule(string $selector): string
    {
        $css = $this->read(self::SHEET);
        $pattern = '/(?<![\w.-])'.preg_quote($selector, '/').'\s*\{([^}]*)\}/';

        $this->assertSame(1, preg_match($pattern, $css, $m),
            "No hay ninguna regla `{$selector}` en `".self::SHEET.'` (o hay más de una forma de escribirla).');

        return $m[1];
    }
}
