<?php

namespace Tests\Feature\Theme;

use Tests\TestCase;

/**
 * **EL BOTÓN DE GOOGLE LLEVA LA MARCA DE GOOGLE** (`specs/auth-con-google.md` §21.1, `#345`).
 *
 * `[DECIDIDO owner, 2026-09-02]`: el botón oficial, variante CLARA y píldora. Hasta hoy era un
 * `.btn--zone` con el color de marca de la INSTALACIÓN y la palabra «Google» como única pista — que
 * es admisible pero no es su botón, y hay ficha en `DEUDA.md` desde `#343` diciéndolo.
 *
 * ⚠️⚠️ **Este fichero existe porque NINGUNA otra guarda ve este botón, y está medido**: el
 * manifiesto congelado de `SidebarDomContractTest` tiene **cero** ocurrencias de «google», porque
 * sus fixtures no pasan `urls.google` y el componente es un `v-if="href"` — o sea que sin claves no
 * emite ni un nodo. Todo el marcado y toda la piel de esta pieza estaban fuera de cobertura.
 *
 * ⚠️⚠️ **Y el modo de fallo que persigue no es que se rompa: es que se ARREGLE.** El impulso natural
 * de cualquiera que mire esta web —y de cualquier guarda de coherencia visual— es devolver este
 * botón al idioma de la casa: el color de acción, el canto de `--r-btn`, el gris del tema. Eso no
 * rompe nada, no lo enseña ninguna captura y **incumple las directrices de Google**, que prohíben
 * recolorear su marca. Lo único que lo puede cazar es una guarda que sepa que esta pieza es una
 * EXCEPCIÓN deliberada.
 */
class GoogleButtonBrandingTest extends TestCase
{
    private const ASSET = 'public/images/providers/google.svg';

    private const COMPONENT = 'resources/js/sidebar/steps/GoogleButton.vue';

    private const SHEET = 'public/css/site.css';

    /**
     * **La huella de la geometría OFICIAL**, tomada del asset que Google distribuye con su propia
     * librería (`gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg`).
     *
     * Es el sha1 de los cuatro atributos `d` concatenados en el orden del fichero. Verificado el
     * 2026-09-02 rasterizando el original y el nuestro a 472×480 con `rsvg-convert`: **0 píxeles
     * distintos de 226.560**, con control (borrando un `<path>` salen 15.855).
     *
     * ⚠️ **Va el NÚMERO y no una lista de nombres**, que es la doctrina de `IconSetAnatomyTest`: así
     * un redibujo «que se ve igual» pone esto en rojo. Si Google publica una marca nueva, quien la
     * traiga cambia esta constante A SABIENDAS y deja dicho de dónde la sacó.
     */
    private const OFFICIAL_GEOMETRY = 'a96c46a4564b26166342b38cc1f914ad781ff6d9';

    /** Sus cuatro colores de marca, en el orden en que el fichero los declara. */
    private const OFFICIAL_FILLS = ['#4285F4', '#34A853', '#FBBC05', '#EA4335'];

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Que las tres fuentes se lean de verdad.** Sin esto, un renombrado deja todo lo de abajo
     * comparando cadenas vacías y en verde — que es como este repo perdió `#113`.
     */
    public function test_the_scan_reads_its_three_sources(): void
    {
        foreach ([self::ASSET, self::COMPONENT, self::SHEET] as $path) {
            $this->assertFileExists(base_path($path));
            $this->assertNotSame('', trim($this->read($path)), "`{$path}` se lee vacío.");
        }

        // Control positivo: el extractor de geometría SACA algo de este fichero.
        $this->assertCount(4, $this->paths(), 'el extractor de `<path>` ha dejado de ver el dibujo.');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  1 · El dibujo es el suyo, sin tocar
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_the_mark_is_googles_own_drawing(): void
    {
        $paths = $this->paths();

        $this->assertSame(
            self::OFFICIAL_GEOMETRY,
            sha1(implode('', array_column($paths, 'd'))),
            'El dibujo de `'.self::ASSET."` ya no es el de Google.\n".
            "▶ Se trae de `gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg`, que es el asset\n".
            "  que Google distribuye con FirebaseUI, y NO se redibuja: `#211` midió lo que cuesta\n".
            "  reconstruir a ojo un logotipo de marca (29 % de píxeles distintos).\n".
            '▶ Si de verdad Google ha cambiado su marca, actualiza `OFFICIAL_GEOMETRY` diciendo de dónde.'
        );

        $this->assertSame(
            self::OFFICIAL_FILLS,
            array_column($paths, 'fill'),
            'Los colores de la «G» han cambiado. Sus directrices prohíben recolorear la marca.'
        );
    }

    /**
     * **Ni `currentColor` ni ninguna otra puerta para recolorearla.**
     *
     * Es la mitad que explica por qué esta pieza NO es un icono del set: `IconSetAnatomyTest` exige
     * exactamente lo contrario (`currentColor` sin excepciones), y por eso vive fuera de
     * `views/components/icons`, como FICHERO. Las dos reglas son ciertas y hablan de cosas distintas.
     */
    public function test_the_mark_cannot_be_recoloured(): void
    {
        $svg = $this->read(self::ASSET);

        // El comentario de cabecera SÍ nombra `currentColor` para explicar por qué no lo lleva: se
        // desnuda antes de mirar, o la guarda acusaría a su propia explicación.
        $naked = (string) preg_replace('/<!--.*?-->/s', '', $svg);

        $this->assertStringNotContainsString('currentColor', $naked,
            'La «G» no puede heredar color del tema: es la marca de un tercero, no un icono del set.');

        $this->assertDoesNotMatchRegularExpression('/\bvar\(\s*--/', $naked,
            'Un token del tema dentro de la marca la haría cambiar de color por instalación.');
    }

    /**
     * **El fichero no trae nada activo.** Va dentro de un `<img>`, donde un SVG es inerte (`#254`),
     * pero se sirve por su propia URL y ahí sí se puede navegar: lo que entra en `public/` se
     * comprueba igual que lo que se sirve en línea.
     */
    public function test_the_mark_carries_nothing_active(): void
    {
        $svg = $this->read(self::ASSET);

        foreach (['<script', '<foreignObject', 'javascript:', 'xlink:href="http', 'href="http'] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $svg,
                '`'.self::ASSET."` trae `{$needle}`: un SVG servido por su URL propia no puede llevar nada activo.");
        }

        $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=/i', $svg,
            'Hay un manejador `on…=` dentro de la marca.');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  2 · El componente enseña ESE fichero, y no se viste de la instalación
    // ─────────────────────────────────────────────────────────────────────────────────

    public function test_the_button_shows_the_asset_and_names_it_only_once(): void
    {
        $vue = $this->componentTemplate();

        $this->assertMatchesRegularExpression(
            '~:src="MARK"~', $vue,
            'El botón ya no pinta la marca desde una constante enlazada. ⚠️ Con `src="/images/…"` '.
            'literal, Vite lo trata como un import y el build SSR FALLA (`UNRESOLVED_IMPORT`, medido).'
        );

        $this->assertStringContainsString("const MARK = '/images/providers/google.svg'", $this->read(self::COMPONENT),
            'La ruta de la marca ha cambiado o ha dejado de ser raíz-relativa.');

        // `alt=""`: el nombre accesible lo da el rótulo. Con `alt="Google"` un lector de pantalla
        // diría «Google, Continuar con Google».
        $this->assertMatchesRegularExpression('~<img\b[^>]*\salt=""~', $vue,
            'La marca tiene que ir con `alt=""`: el nombre accesible lo pone el rótulo del botón.');
    }

    public function test_the_button_does_not_wear_the_installations_colours(): void
    {
        $vue = $this->componentTemplate();

        $this->assertStringNotContainsString('btn--zone', $vue,
            "El botón de Google ha vuelto a llevar `btn--zone`, que es el color de marca de la\n".
            "INSTALACIÓN. Es lo que llevaba hasta `#345` y lo que el owner pidió cambiar: un botón\n".
            'de un tercero disfrazado del anfitrión no se reconoce como suyo.'
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  3 · La piel son los valores de su guía, y no los del tema
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * Los valores de la variante CLARA, tal y como los publica
     * `developers.google.com/identity/branding-guidelines` (leída el 2026-09-02).
     */
    public function test_the_skin_uses_googles_published_values(): void
    {
        $rule = $this->cssRule('.auth__google');

        foreach (['#fff' => 'el fondo', '#1f1f1f' => 'el texto', '#747775' => 'el borde'] as $hex => $what) {
            $this->assertStringContainsString($hex, $rule,
                "`.auth__google` ya no declara {$what} de la variante clara de Google (`{$hex}`)."
            );
        }
    }

    /**
     * ⚠️⚠️ **La que de verdad protege**: que nadie «armonice» el botón con el tema del cliente.
     *
     * Un `background: var(--action)` aquí no rompe ningún test de conducta, no lo enseña ninguna
     * captura y hace que el botón cambie de color en cada instalación — que es exactamente lo que
     * sus directrices prohíben.
     */
    public function test_the_skin_does_not_derive_from_the_installations_palette(): void
    {
        $rule = $this->cssRule('.auth__google');

        foreach (['--action', '--zone-1', '--zone-2', '--on-brand', '--bg-card'] as $token) {
            $this->assertStringNotContainsString($token, $rule,
                "`.auth__google` lee `{$token}`: eso hace que la marca de Google cambie de color\n".
                'según la instalación. Sus colores son suyos y no son tema.'
            );
        }

        // El hover tampoco: heredar `.btn:hover` lo pintaría del color de acción del cliente.
        $hover = $this->cssRule('.auth__google:hover');
        $this->assertStringNotContainsString('--action', $hover,
            'El hover del botón de Google vuelve a pintarse con el color de acción de la instalación.');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Utilidades
    // ─────────────────────────────────────────────────────────────────────────────────

    private function read(string $path): string
    {
        return (string) file_get_contents(base_path($path));
    }

    /** @return list<array{d:string, fill:string}> */
    private function paths(): array
    {
        preg_match_all(
            '/<path\b[^>]*\bd="([^"]+)"[^>]*\bfill="([^"]+)"/',
            $this->read(self::ASSET), $m, PREG_SET_ORDER
        );

        return array_map(fn (array $p): array => ['d' => $p[1], 'fill' => strtoupper($p[2])], $m);
    }

    /** El `<template>` del componente, sin el docblock —que CITA `btn--zone` al contar la historia—. */
    private function componentTemplate(): string
    {
        $source = $this->read(self::COMPONENT);
        $at = strpos($source, '<template>');

        $this->assertNotFalse($at, '`'.self::COMPONENT.'` ya no tiene `<template>`.');

        return substr($source, $at);
    }

    /**
     * El CUERPO de una regla, localizada por su selector EXACTO.
     *
     * ⚠️ Se ancla en `selector {` con la llave y se corta en la primera `}`: buscar por subcadena
     * haría que `.auth__google` casara también dentro de `.auth__google-mark`, que es la trampa de
     * `#264` (`width` casa dentro de `stroke-width`) aplicada a un selector.
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
