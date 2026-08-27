<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * **LA FORMA TAMBIÉN ES TEMA: la escala de canto, la ley del motivo y el anillo de foco**
 * (`docs/specs/tema-por-instalacion.md`, tanda 2a).
 *
 * La tanda 1 hizo que el COLOR siguiera al cliente. Esta hace lo mismo con la FORMA, y el
 * problema no era el mismo: en color había 866 usos que ya leían por token y bastaba
 * re-escoparlos; en forma había **95 literales de `border-radius`** y **23 reglas de foco con
 * cinco tratamientos distintos y CERO tokens**. O sea que un cliente podía cambiar toda su
 * marca y sus cantos —y el anillo con el que su visitante navega con teclado— seguían siendo
 * los del primero. No fallaba nada, no avisaba nadie.
 *
 * ⚠️ **Lo que este fichero vigila no es «que haya tokens», es que las EXCEPCIONES no crezcan.**
 * Un canto en literal no rompe nada hoy: rompe el día que una instalación redefine su escala y
 * ese canto se queda quieto mientras los otros 149 se mueven. Por eso las tres listas de abajo
 * **solo pueden encoger**, igual que `ALLOWED_SELECTORS` en `RawColourIsNotATokenTest`.
 *
 * ⚠️⚠️ **Y una trampa que costó una pasada**: un literal de `border-radius` NO es
 * automáticamente deuda. Al medir los 56 que quedaban aparecieron **dos leyes distintas
 * mezcladas**, y meterlas en el mismo saco habría roto una de ellas:
 *
 *   · **canto de contenedor** — el borde de una tarjeta, un control, un badge. Pertenece a la
 *     escala y tiene que salir de un token.
 *   · **motivo cuadrado** — la familia `.jj-block` y sus primos, donde el radio es la FORMA de
 *     un cuadradito decorativo a cada tamaño. Sigue una ley de facto **medida en el propio
 *     producto: radio ≈ lado / 4** (mediana exacta 4,00 sobre 16 declaraciones). Forzarlos a un
 *     escalón fijo convertiría una familia proporcional en cinco cuadrados mal redondeados.
 *
 * La tercera familia —dibujo— ni siquiera es un canto: son las cuatro esquinas del marcador QR,
 * un subrayado tipográfico en `em` y una barra de 2 px de alto.
 */
class ShapeScaleTest extends TestCase
{
    private const SHEETS = 'public/css/*.css';

    /** La escala de canto, CERRADA. Añadir un escalón es una decisión de producto, no un arreglo. */
    private const RADIUS_SCALE = [
        '--r-xs' => 5,
        '--r-sm' => 8,
        '--r-md' => 10,
        '--r-btn' => 14,
        '--r' => 16,
        '--r-lg' => 28,
        '--r-pill' => 999,
    ];

    /**
     * **Familia MOTIVO CUADRADO** — `selector => [lado en px, radio en px]`.
     *
     * Su radio no sale de la escala: sale de la ley `lado / 4`. La lista solo encoge; para que
     * entre uno nuevo hay que poder justificar que es un motivo y no un contenedor.
     */
    private const SQUARE_MOTIF = [
        '.jj-block--xs' => [9, 2.0],
        '.jj-block--sm' => [14, 3.5],
        '.jj-block--md' => [22, 5.0],
        '.jj-block--xl' => [42, 9.0],
        '.bk-context .jj-block' => [11, 3.0],
        '.offw-burst .spark' => [9, 2.0],
        '.svc-marquee__item::after' => [13, 3.5],
        '.svc-ed2__kicker::before' => [12, 3.0],
        // ⚠️ `.hero__stat .sep` vivía aquí y SE RETIRÓ el 2026-08-27 (tanda 2b, paso 1): la familia
        // `.hero__stat` entera estaba MUERTA —cero usos en `resources/`— y se fue con otras cinco.
        // Lo cazó esta misma guarda, que es para lo que está: una excepción sin sujeto tapa el
        // siguiente caso que se llame igual.
        '.bd-proc__cube' => [44, 11.0],
        '.catalog-acc__icon' => [30, 9.0],
        '.pwd-input__toggle' => [32, 6.0],
    ];

    /**
     * **Familia DIBUJO y RESET** — el radio no es el canto de un contenedor.
     *
     * Cada entrada dice POR QUÉ, porque una lista de excepciones sin motivo es una lista que
     * crece. La lista solo encoge.
     */
    private const DRAWING = [
        '.qr-corner--tl' => 'una de las cuatro esquinas del marcador QR: es el dibujo',
        '.qr-corner--tr' => 'ídem',
        '.qr-corner--bl' => 'ídem',
        '.qr-corner--br' => 'ídem',
        '.qr-slot' => 'el hueco del QR dentro del marcador, al 100% de su caja',
        '.nav__period-block' => 'el punto de la marca, en `em`: escala con la tipografía, no con la caja',
        '.hero__title .blink' => 'subrayado tipográfico con padding en `em`',
        '.reserve h2 .fill' => 'ídem',
        '.offw-gift .cft' => 'lazo del regalo del widget de ofertas: dibujo',
        '.offw-burst .spark.star' => 'punta de estrella: `0` es la forma, no un reset',
        '.slider-progress' => 'barra de 2 px de alto — el radio la hace una píldora aplastada',
        '.cal__dot' => 'muestra de color de la leyenda del calendario: su radio es la forma del swatch, '.
            'no el canto de un contenedor. Con lado 12 y radio 4 su ratio es 3,00 y no cumple la ley '.
            'del motivo; tokenizarlo a `--r-xs` lo movería +1px, así que se deja y se anota',
        '.invite-card__deco' => 'adorno de la invitación, que se captura con `html2canvas`',
        '.bd-card__conf' => 'confeti del diseñador de invitaciones: dibujo',
        '.hero__chip:focus-visible' => 'anillo de foco sobre el vídeo del hero (ver el bloque de foco)',
        '.hero--full .hero__stage' => 'RESET: anula un radio heredado',
        '.plan-select__panel a' => 'RESET: ídem',
        '.bd-proc__cube@media' => 'el cubo del proceso a otro tamaño dentro de un @media: sigue la ley',
    ];

    /** Las tres reglas de foco que NO pueden usar `--focus-color`, y por qué. */
    private const FOCUS_EXCEPTIONS = [
        '.skip-link:focus-visible' => 'pinta sobre su propio fondo oscuro, que aún no declara superficie',
        '.hero__chip:focus-visible' => 'pinta sobre el vídeo del hero, que aún no declara superficie',
        '.gf-fiche__head:focus-visible' => 'anillo INTERIOR (offset −2px) en el acento de zona, no en la tinta',
    ];

    /** @var ?list<string> */
    private ?array $sheets = null;

    /** @var ?list<array{selector: string, value: string, media: bool}> */
    private ?array $radii = null;

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guardas de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **El escaneo ve de verdad el corpus, y ve DENTRO de los `@media`.**
     *
     * La primera versión del instrumento que midió esta tanda no descendía en las at-rules y daba
     * 3 reglas de `.nav` donde hay 45. Un contador global no lo habría cazado: hay que aseverar
     * que se ven declaraciones que SOLO existen dentro de un `@media`.
     */
    public function test_the_scan_sees_the_corpus_including_inside_at_rules(): void
    {
        $radii = $this->radii();

        $this->assertGreaterThan(
            200, count($radii),
            'el escaneo encuentra menos de 200 `border-radius` y hay ~219: el parser se ha quedado '.
            'ciego a parte del corpus y todo lo de abajo estaría verde sin mirar nada.',
        );

        $this->assertNotEmpty(
            array_filter($radii, fn (array $r) => $r['media']),
            'el escaneo no ve ni un `border-radius` dentro de un `@media`, y los hay: el parser no '.
            'desciende en las at-rules. Es el defecto exacto que la medida de esta tanda cazó.',
        );

        // Por NOMBRE, no por umbral: un contador no distingue «leo poco» de «leo otra cosa».
        foreach (['.foot__strip', '.bd-card', '.offw-card'] as $needle) {
            $this->assertNotEmpty(
                array_filter($radii, fn (array $r) => str_contains($r['selector'], $needle))
                    ?: array_filter($this->sheetContents(), fn (string $c) => str_contains($c, $needle)),
                "el escaneo no ve `{$needle}` en ninguna hoja",
            );
        }
    }

    /**
     * **El clasificador caza sus propios ejemplos.**
     *
     * Sin esto, una expresión regular que dejara de reconocer `var()` mandaría todos los cantos a
     * la lista de literales —o al revés, todos a la de tokens— y el fichero seguiría verde.
     */
    public function test_the_classifier_catches_its_own_examples(): void
    {
        $cases = [
            'var(--r-md)' => 'token',
            'var(--r-pill)' => 'token',
            '50%' => 'circle',
            '10px' => 'literal',
            '0' => 'literal',
            '0.18em' => 'literal',
            '7px 0 0 0' => 'literal',
            '3.5px' => 'literal',
        ];

        foreach ($cases as $value => $expected) {
            $this->assertSame(
                $expected, $this->classify($value),
                "el clasificador ha dejado de entender «{$value}»",
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo que se vigila
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **La escala de canto está declarada entera y es estrictamente creciente.**
     *
     * Lo segundo no es cosmética: dos escalones que se crucen —o que valgan lo mismo— hacen que
     * elegir entre ellos deje de significar nada, y el siguiente que necesite un canto meterá un
     * literal porque «ninguno encaja».
     */
    public function test_the_radius_scale_is_declared_whole_and_strictly_increasing(): void
    {
        $root = $this->rootTokens();
        $previous = null;

        foreach (self::RADIUS_SCALE as $token => $expected) {
            $this->assertArrayHasKey(
                $token, $root,
                "`{$token}` no está declarado en ningún `:root`: la escala de canto tiene un hueco ".
                'y las reglas que lo usaran caerían al valor inicial, en silencio.',
            );

            $this->assertSame(
                "{$expected}px", $root[$token],
                "`{$token}` vale «{$root[$token]}» y esta guarda espera «{$expected}px». Si el cambio ".
                'es intencionado, se actualiza AQUÍ y en la spec — no se relaja la aserción: estos '.
                'valores salieron de minimizar el movimiento sobre los 23 literales que había.',
            );

            if ($previous !== null) {
                $this->assertGreaterThan(
                    $previous, $expected,
                    "la escala de canto no es creciente en `{$token}`",
                );
            }

            $previous = $expected;
        }
    }

    /**
     * **Todo `border-radius` en literal pertenece a una de las tres familias declaradas.**
     *
     * Ésta es la aserción con más valor del fichero, y la que muerde al escribir un canto nuevo a
     * mano. Un cuarto caso no existe: o es escala, o es círculo, o es motivo, o es dibujo.
     */
    public function test_every_remaining_literal_belongs_to_a_declared_family(): void
    {
        $allowed = array_merge(array_keys(self::SQUARE_MOTIF), array_keys(self::DRAWING));
        $orphans = [];

        foreach ($this->radii() as $rule) {
            if ($this->classify($rule['value']) !== 'literal') {
                continue;
            }

            foreach ($allowed as $known) {
                $bare = str_replace('@media', '', $known);

                if ($rule['selector'] === $bare || in_array($bare, $this->splitSelectors($rule['selector']), true)) {
                    continue 2;
                }
            }

            $orphans[] = "{$rule['selector']}  →  {$rule['value']}";
        }

        $this->assertSame(
            [], $orphans,
            "hay `border-radius` en literal que no son ni escala ni excepción declarada:\n  ".
            implode("\n  ", $orphans)."\n\n".
            "Antes de añadirlo a una lista, decide QUÉ es:\n".
            "  · canto de un contenedor  → usa un token de la escala (--r-xs … --r-lg)\n".
            "  · círculo                 → `50%`\n".
            "  · motivo cuadrado         → SQUARE_MOTIF, y tiene que cumplir la ley lado/4\n".
            "  · dibujo o reset          → DRAWING, con el porqué escrito\n".
            'Las dos listas SOLO ENCOGEN: si estás ampliándolas, casi seguro es un canto.',
        );
    }

    /**
     * **Las dos listas de excepción no tienen entradas muertas.**
     *
     * Una excepción cuyo selector ya no existe es una excepción que tapa el siguiente caso con el
     * mismo nombre. Es el control que `RawColourIsNotATokenTest` aprendió a llevar.
     */
    public function test_the_exception_lists_have_no_dead_entries(): void
    {
        $css = implode("\n", $this->sheetContents());
        $dead = [];

        foreach (array_merge(array_keys(self::SQUARE_MOTIF), array_keys(self::DRAWING), array_keys(self::FOCUS_EXCEPTIONS)) as $selector) {
            $needle = str_replace(['@media', ':focus-visible'], '', $selector);

            if (! str_contains($css, $needle)) {
                $dead[] = $selector;
            }
        }

        $this->assertSame(
            [], $dead,
            'estas excepciones ya no tienen sujeto en el CSS y hay que RETIRARLAS: '.implode(', ', $dead),
        );
    }

    /**
     * **La familia del motivo cuadrado cumple su ley: radio ≈ lado / 4.**
     *
     * Es lo que impide que la lista de excepciones se convierta en un cajón: para entrar hay que
     * cumplir la ley, y si un miembro deja de cumplirla es que era un canto disfrazado.
     *
     * La tolerancia (±20 %) no es generosidad: es la dispersión REAL medida en el producto
     * —ratios de 3,00 a 5,33 con mediana exacta 4,00—, y estrecharla haría caer a miembros que
     * llevan ahí desde antes de que la ley se descubriera.
     */
    public function test_the_square_motif_family_obeys_its_law(): void
    {
        $offenders = [];

        foreach (self::SQUARE_MOTIF as $selector => [$side, $radius]) {
            $ratio = $side / $radius;

            if ($ratio < 3.2 || $ratio > 5.4) {
                $offenders[] = sprintf('%s: lado %dpx / radio %spx = %.2f', $selector, $side, $radius, $ratio);
            }
        }

        $this->assertSame(
            [], $offenders,
            "estos miembros del motivo cuadrado no cumplen `lado / 4`:\n  ".implode("\n  ", $offenders)."\n".
            'Si el valor es correcto, no es un motivo: es un canto, y va a la escala.',
        );

        // Y la ley, medida sobre la familia entera, tiene que seguir dando ~4.
        $ratios = array_map(fn (array $m) => $m[0] / $m[1], array_values(self::SQUARE_MOTIF));
        sort($ratios);
        $median = $ratios[intdiv(count($ratios), 2)];

        $this->assertEqualsWithDelta(
            4.0, $median, 0.35,
            'la mediana de `lado / radio` de la familia se ha ido de 4: la ley que justifica esta '.
            'lista de excepciones ha dejado de ser cierta, y con ella la excepción.',
        );
    }

    /**
     * **El anillo de foco sale de tokens, y sus excepciones son exactamente tres.**
     *
     * Un cliente tiene que poder cambiar el color con el que se ve su foco de teclado. Antes de
     * esta tanda no podía: eran 23 reglas con literales y cinco tratamientos distintos.
     */
    public function test_the_focus_ring_comes_from_tokens(): void
    {
        $root = $this->rootTokens();

        foreach (['--focus-w', '--focus-color', '--focus-outline'] as $token) {
            $this->assertArrayHasKey($token, $root, "falta el token de foco `{$token}`");
        }

        $this->assertStringContainsString(
            'var(--focus-w)', $root['--focus-outline'],
            '`--focus-outline` no se compone del grosor tokenizado: cambiar `--focus-w` dejaría de '.
            'tener efecto y el token sería decorativo.',
        );
        $this->assertStringContainsString(
            'var(--focus-color)', $root['--focus-outline'],
            '`--focus-outline` no se compone del color tokenizado: un cliente no podría cambiarlo.',
        );

        // Ninguna regla de foco puede volver a escribir el anillo a mano, salvo las tres declaradas.
        $offenders = [];

        foreach ($this->focusOutlineRules() as $rule) {
            if (str_contains($rule['value'], 'var(--focus-outline)')) {
                continue;
            }

            if ($rule['value'] === 'none' || $rule['value'] === '0') {
                continue; // se sustituye por `box-shadow` o por `border-color`; lo vigila el test de abajo
            }

            foreach (array_keys(self::FOCUS_EXCEPTIONS) as $known) {
                if (in_array($known, $this->splitSelectors($rule['selector']), true)) {
                    continue 2;
                }
            }

            $offenders[] = "{$rule['selector']}  →  outline: {$rule['value']}";
        }

        $this->assertSame(
            [], $offenders,
            "estas reglas escriben el anillo de foco a mano en vez de usar `var(--focus-outline)`:\n  ".
            implode("\n  ", $offenders)."\n".
            'Si de verdad necesita otro color, va a FOCUS_EXCEPTIONS con su porqué — y esa lista '.
            'solo encoge: las tres que hay desaparecen cuando el hero declare superficie.',
        );
    }

    /**
     * **Ningún `outline: none` se queda sin sustituto visible.**
     *
     * Es el hallazgo `M-01` de la auditoría del propio cliente, severidad Alta, traído a nuestro
     * código: matar el contorno sin poner nada en su sitio deja a quien navega con teclado sin
     * saber dónde está, y no lo caza ningún test de los que había.
     */
    public function test_no_focus_rule_kills_the_outline_without_a_replacement(): void
    {
        $offenders = [];

        foreach ($this->focusRules() as $rule) {
            if (! preg_match('/(?<![-\w])outline\s*:\s*(none|0)\b/', $rule['body'])) {
                continue;
            }

            // El sustituto vale si es un anillo (`box-shadow`) o un cambio de borde bien visible.
            $hasRing = (bool) preg_match('/(?<![-\w])box-shadow\s*:/', $rule['body']);
            $hasBorder = (bool) preg_match('/(?<![-\w])border(-color)?\s*:/', $rule['body']);

            // `*:focus { outline: none }` es el reset global que da paso a `:focus-visible`: correcto.
            $isGlobalReset = str_starts_with($rule['selector'], '*:focus') && ! str_contains($rule['selector'], 'visible');

            if (! $hasRing && ! $hasBorder && ! $isGlobalReset) {
                $offenders[] = $rule['selector'];
            }
        }

        $this->assertSame(
            [], $offenders,
            "estas reglas apagan el contorno de foco y no ponen nada en su sitio:\n  ".
            implode("\n  ", $offenders)."\n".
            'Pon un `box-shadow` de anillo o un `border-color` que se distinga, o quita el '.
            '`outline: none`.',
        );
    }

    /**
     * **La tira de marca DERIVA y no lleva ni un color en crudo.**
     *
     * Es la misma regla que la paleta de tinta de `[data-surface]`: si un escalón se teclea, el
     * cliente cambia su marca y esa franja se queda con la del primero, sin fallar y sin avisar.
     */
    public function test_the_brand_strip_derives_from_the_brand_tokens(): void
    {
        $root = $this->rootTokens();

        foreach (['--strip-1', '--strip-2', '--strip-3', '--strip-4', '--strip-5'] as $token) {
            $this->assertArrayHasKey($token, $root, "falta `{$token}`: la tira del pie tiene un hueco");

            $this->assertMatchesRegularExpression(
                '/var\(\s*--(zone|brand)-[12]\s*\)/', $root[$token],
                "`{$token}` vale «{$root[$token]}» y no lee ningún token de marca: es un color en ".
                'crudo, y el pie del cliente se quedaría con la franja del primero.',
            );

            $this->assertDoesNotMatchRegularExpression(
                '/#[0-9a-fA-F]{3,8}\b/', $root[$token],
                "`{$token}` trae un hex literal. La tira DERIVA; no se teclea.",
            );
        }

        // Y no puede pintarse con un color semántico: dejaría de significar lo que significa
        // (hallazgo `C-04` de la auditoría del cliente), y `--attn` vale lo mismo que `--zone-2`.
        foreach (['--strip-1', '--strip-2', '--strip-3', '--strip-4', '--strip-5'] as $token) {
            $this->assertDoesNotMatchRegularExpression(
                '/var\(\s*--(ok|err|warn|attn|refund)\b/', $root[$token],
                "`{$token}` usa un token SEMÁNTICO como decoración: `--ok` significa «reserva ".
                'confirmada» y `--err` «algo ha fallado». Si decoran, dejan de informar.',
            );
        }

        // ⚠️ Y ninguna franja puede ser una MEZCLA de los dos colores de marca. Se escribió así
        // primero y la medida lo tumbó: con dos colores casi complementarios —los del segundo
        // cliente— la franja del medio salía barro (`#868D7D`, croma 0,025), y en `oklab` era
        // exactamente igual de malo (0,024). El producto no elige la marca de su cliente, así
        // que no puede apostar a que sus dos colores interpolen bien. Ciclan.
        foreach (['--strip-1', '--strip-2', '--strip-3', '--strip-4', '--strip-5'] as $token) {
            $this->assertStringNotContainsString(
                'color-mix', $root[$token],
                "`{$token}` mezcla dos colores de marca. Con un par casi complementario la mezcla ".
                'sale gris sucio, y no lo arregla cambiar de espacio de color: medido, `oklab` da '.
                'croma 0,024 y `srgb` 0,025. Las franjas CICLAN sobre los colores que haya.',
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Instrumento
    // ─────────────────────────────────────────────────────────────────────────────────

    /** @return list<string> */
    private function sheetContents(): array
    {
        if ($this->sheets !== null) {
            return $this->sheets;
        }

        $out = [];

        foreach (glob(base_path(self::SHEETS)) ?: [] as $path) {
            // ⚠️ Los comentarios se BLANQUEAN conservando la longitud, no se borran: al borrarlos,
            // un `/* … */` pegado al selector de la línea de arriba hacía que el localizador diera
            // CERO reglas donde había una. Lo pagó el script que hizo las conversiones.
            $out[] = (string) preg_replace_callback(
                '#/\*.*?\*/#s',
                fn (array $m) => str_repeat(' ', strlen($m[0])),
                (string) file_get_contents($path),
            );
        }

        return $this->sheets = $out;
    }

    /**
     * Todas las declaraciones de `border-radius`, descendiendo en `@media` y compañía.
     *
     * @return list<array{selector: string, value: string, media: bool}>
     */
    private function radii(): array
    {
        if ($this->radii !== null) {
            return $this->radii;
        }

        $out = [];

        foreach ($this->rules() as $rule) {
            if (preg_match('/(?<![-\w])border(-[a-z]+)*-radius\s*:\s*([^;}]+)/', $rule['body'], $m)) {
                $out[] = [
                    'selector' => $rule['selector'],
                    'value' => trim($m[2]),
                    'media' => $rule['media'],
                ];
            }
        }

        return $this->radii = $out;
    }

    /** @return list<array{selector: string, body: string, media: bool}> */
    private function focusRules(): array
    {
        return array_values(array_filter(
            $this->rules(),
            fn (array $r) => str_contains($r['selector'], ':focus'),
        ));
    }

    /** @return list<array{selector: string, value: string}> */
    private function focusOutlineRules(): array
    {
        $out = [];

        foreach ($this->focusRules() as $rule) {
            if (preg_match('/(?<![-\w])outline\s*:\s*([^;}]+)/', $rule['body'], $m)) {
                $out[] = ['selector' => $rule['selector'], 'value' => trim($m[1])];
            }
        }

        return $out;
    }

    /**
     * Reglas de las hojas, DESCENDIENDO en las at-rules de bloque.
     *
     * @return list<array{selector: string, body: string, media: bool}>
     */
    private function rules(): array
    {
        $out = [];

        foreach ($this->sheetContents() as $css) {
            $this->walk($css, false, $out);
        }

        return $out;
    }

    /** @param list<array{selector: string, body: string, media: bool}> $out */
    private function walk(string $css, bool $inMedia, array &$out): void
    {
        $length = strlen($css);
        $depth = 0;
        $selectorStart = 0;
        $bodyStart = 0;
        $selector = '';

        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];

            if ($char === '{') {
                if ($depth === 0) {
                    $selector = trim((string) preg_replace('/\s+/', ' ', substr($css, $selectorStart, $i - $selectorStart)));
                    $bodyStart = $i + 1;
                }
                $depth++;

                continue;
            }

            if ($char !== '}') {
                continue;
            }

            $depth--;

            if ($depth !== 0) {
                continue;
            }

            $body = substr($css, $bodyStart, $i - $bodyStart);

            if (preg_match('/^@(media|supports|layer|container|scope)\b/', $selector)) {
                $this->walk($body, true, $out);
            } elseif (! str_starts_with($selector, '@')) {
                $out[] = ['selector' => $selector, 'body' => $body, 'media' => $inMedia];
            }

            $selectorStart = $i + 1;
        }
    }

    /**
     * Tokens declarados en cualquier `:root` de las hojas.
     *
     * @return array<string, string>
     */
    private function rootTokens(): array
    {
        $out = [];

        foreach ($this->rules() as $rule) {
            if ($rule['selector'] !== ':root') {
                continue;
            }

            foreach ($this->declarations($rule['body']) as $declaration) {
                if (! str_contains($declaration, ':')) {
                    continue;
                }

                [$property, $value] = explode(':', $declaration, 2);
                $property = trim($property);

                if (str_starts_with($property, '--')) {
                    $out[$property] = trim($value);
                }
            }
        }

        return $out;
    }

    /**
     * Parte un cuerpo en declaraciones respetando los paréntesis anidados.
     *
     * ⚠️ Un `explode(';')` a secas parte por dentro de `color-mix(in srgb, var(--fg) 10%, …)` en
     * cuanto lleve un `;` — y ese es el defecto que hizo que el primer inventario de colores de
     * este repo diera 76 donde había 234 (`DECISIONES #143` §8).
     *
     * @return list<string>
     */
    private function declarations(string $body): array
    {
        $out = [];
        $buffer = '';
        $depth = 0;
        $length = strlen($body);

        for ($i = 0; $i < $length; $i++) {
            $char = $body[$i];

            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }

            if ($char === ';' && $depth === 0) {
                if (trim($buffer) !== '') {
                    $out[] = trim($buffer);
                }
                $buffer = '';

                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $out[] = trim($buffer);
        }

        return $out;
    }

    /** @return list<string> */
    private function splitSelectors(string $selector): array
    {
        return array_map('trim', explode(',', $selector));
    }

    /** `token` · `circle` · `literal` */
    private function classify(string $value): string
    {
        if (str_contains($value, 'var(')) {
            return 'token';
        }

        return trim($value) === '50%' ? 'circle' : 'literal';
    }
}
