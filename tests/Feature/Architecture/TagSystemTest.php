<?php

namespace Tests\Feature\Architecture;

use Tests\Support\ReadsSiteStylesheets;
use Tests\TestCase;

/**
 * **EL SISTEMA DE ETIQUETAS ES UNO, Y CADA CONSUMIDOR SOLO PONE LA COLOCACIÓN**
 * (`specs/idioma-visual-heredado.md`, tanda A).
 *
 * ❗ **Antes de esto no había sistema, y está medido**: la misma función se resolvía con **cinco
 * formas distintas** —radios `--r-xs`, `--r-pill` y `--r-md`; paddings 3/8 · 6/12 · 7/13 · 7/14 ·
 * 8/14; tallas 10 · 10,5 · 11 · 12,5 · 13— y **cuatro rotaciones** (−2°, −5°, +5°, +6°). Es el
 * mismo hallazgo que `#196` con las sombras: *no era una escala con ruido, es que no había ninguna.*
 *
 * ▶ **Dos artboards del cliente visten esto distinto y los DOS entran** (`[DECIDIDO owner]`), con un
 * criterio explícito: si el badge es una **SEÑAL** manda `Elementos Fachada` E2 (contundente); si es
 * un **DATO** que se lee, manda el registro de su landing (mono fino). *Su contradicción no se
 * resuelve eligiendo un ganador: se resuelve diciendo para qué sirve cada uno.*
 *
 * ⚠️⚠️ **Lo que esta guarda vigila de verdad es la EROSIÓN.** Un sistema de etiquetas no se rompe de
 * golpe: se rompe cuando la siguiente tarjeta necesita «un pelín más de padding» y se lo escribe en
 * su propia regla. A los seis meses hay cinco formas otra vez — que es exactamente el estado del que
 * se viene. Por eso el caso de abajo prohíbe que un consumidor declare FORMA, no solo que emita la
 * clase.
 */
class TagSystemTest extends TestCase
{
    use ReadsSiteStylesheets;

    /**
     * Los consumidores convertidos: `selector CSS => clases que tiene que emitir`.
     *
     * **La lista solo crece** cuando una etiqueta más entra al sistema.
     *
     * @var array<string, list<string>>
     */
    private const CONSUMERS = [
        // ⚠️ Aquí estaban `.zone-photo-card__tag` y `.zone-intro__tag`, la etiqueta de edad de las
        // tarjetas de zona. **Se van con su sujeto** (`#302`): las tarjetas se retiraron y la EDAD
        // subió al selector, donde no es una etiqueta sino un dato de la pestaña (`.zone-pick__age`,
        // en el registro mono). El sistema de etiquetas sigue con sus otros consumidores.
        // ⚠️⚠️ Y aquí estaba `.price__special-chip`, que **se va con su sujeto** (`#479`): la tarifa
        // especial dejó de publicarse como un recargo dentro de una cápsula («Suplemento +2 €») y
        // pasó a ser una FRASE con su precio entero («10 € en tarifa especial»),
        // `[DECIDIDO owner, 2026-09-09]` sobre la regla dura del canvas. *Una frase en el flujo del
        // texto no es una etiqueta*, así que exigirle la forma del sistema sería obligarla a ser
        // una cápsula por inercia de lo que fue. La clase se llama ya `.price__special-line`, para
        // que el nombre no siga prometiendo un chip.
        // ⚠️ `.ride-card__badge` se fue en `#482` con el carrusel. El distintivo de una atracción
        // se pinta hoy en `/atracciones`, y allí NO es una etiqueta del sistema: es el segundo
        // `chipDato` de la ficha —cápsula en Nube, mono 11—, que es otro componente del canvas.
        '.price__badge' => ['tag', 'tag--senal', 'tag--tinta'],
        '.svc-photo__tag' => ['tag', 'tag--senal', 'tag--punteada'],
    ];

    /** Propiedades que son FORMA y por tanto del sistema, nunca del consumidor. */
    private const SHAPE = [
        'border-radius', 'padding', 'font-size', 'font-weight', 'font-family',
        'letter-spacing', 'text-transform', 'border',
    ];

    /**
     * Lo que el consumidor SÍ puede escribir: dónde va la etiqueta y cuánto se inclina.
     *
     * ⚠️ `color` está aquí a propósito: sobre una foto lo pone el consumidor porque depende del
     * fondo que tenga debajo, y la punteada se dibuja con `currentColor`.
     */
    private const PLACEMENT = [
        'position', 'top', 'right', 'bottom', 'left', 'z-index', 'margin', 'align-self',
        'pointer-events', 'color', 'gap', 'line-height', 'flex', '--tag-tilt',
    ];

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda
    // ─────────────────────────────────────────────────────────────────────────────────

    /** **El lector de reglas encuentra el sistema.** Sin esto, lo de abajo puede vigilar la nada. */
    public function test_the_system_is_declared(): void
    {
        foreach (['.tag', '.tag--senal', '.tag--punteada', '.tag--tinta'] as $selector) {
            $this->assertNotNull(
                $this->rule($selector),
                "el sistema de etiquetas no declara `{$selector}`: o se ha renombrado, o esta guarda ".
                'está mirando otra hoja.',
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo que se vigila
    // ─────────────────────────────────────────────────────────────────────────────────

    /** **La forma sale del sistema**: canto de la escala y giro por PERILLA, no a mano. */
    public function test_the_base_owns_the_shape(): void
    {
        $base = (string) $this->rule('.tag');

        $this->assertStringContainsString(
            'var(--r-pill)', $base,
            'la etiqueta no toma su canto del token de cápsula: un literal aquí saca la forma de la '.
            'escala y `ShapeScaleTest` deja de poder vigilarla.',
        );
        $this->assertStringContainsString(
            'var(--tag-tilt, 0deg)', $base,
            'el giro no es una perilla con defecto 0. Girar es COLOCACIÓN —lo decide quien pega la '.
            'etiqueta encima de algo—, y una etiqueta en el flujo del texto no se gira.',
        );
    }

    /**
     * **UN solo ángulo** (`[DECIDIDO owner]`).
     *
     * Había cuatro (−2°, −5°, +5°, +6°) y su propia landing gira poco y poco: medido, −2°/−3°.
     */
    public function test_every_tilt_is_the_same_single_angle(): void
    {
        $angles = [];

        foreach ($this->siteRules() as $rule) {
            if (preg_match_all('/--tag-tilt:\s*([^;}]+)/', $rule['body'], $found)) {
                foreach ($found[1] as $value) {
                    $angles[trim($value)] = true;
                }
            }
        }

        $this->assertSame(
            ['-2deg'], array_keys($angles),
            'hay más de un ángulo de etiqueta declarado: '.implode(', ', array_keys($angles)).
            '. El owner eligió UNO; cuatro ángulos distintos es de donde se viene.',
        );
    }

    /** **Cada consumidor emite las clases del sistema.** */
    public function test_every_consumer_emits_the_system_classes(): void
    {
        $blade = $this->bladeCorpus();

        foreach (self::CONSUMERS as $selector => $classes) {
            $bare = ltrim($selector, '.');

            $this->assertMatchesRegularExpression(
                '/class="[^"]*(?<![-\w])'.preg_quote($bare, '/').'(?![-\w])/', $blade,
                "`{$selector}` no se emite desde ninguna vista: o ha cambiado de nombre, o esta ".
                'entrada de la lista se ha quedado sin sujeto.',
            );

            foreach ($classes as $class) {
                $this->assertMatchesRegularExpression(
                    '/class="[^"]*(?<![-\w])'.preg_quote($class, '/').'(?![-\w])[^"]*(?<![-\w])'.
                    preg_quote($bare, '/').'(?![-\w])/', $blade,
                    "`{$selector}` no emite `{$class}`: se ha salido del sistema de etiquetas y ".
                    'vuelve a tener forma propia.',
                );
            }
        }
    }

    /**
     * **❗ Y ninguno vuelve a escribir FORMA.** Éste es el que impide la erosión.
     *
     * Un consumidor puede decir DÓNDE va su etiqueta y cuánto se inclina. En cuanto escribe un
     * `padding` o un `border-radius` propio, el sistema deja de serlo — y no falla nada: solo
     * aparece una sexta forma que nadie decidió.
     */
    public function test_no_consumer_redeclares_the_shape(): void
    {
        $offenders = [];

        foreach (self::CONSUMERS as $selector => $_) {
            foreach ($this->siteRules() as $rule) {
                if (! $this->targets($rule['selector'], $selector)) {
                    continue;
                }

                foreach (self::SHAPE as $property) {
                    if (in_array($property, self::PLACEMENT, true)) {
                        continue;
                    }

                    if (preg_match('/(?<![-\w])'.preg_quote($property, '/').'\s*:/', $rule['body'])) {
                        $offenders[] = $rule['sheet'].' · '.$rule['selector'].' · '.$property;
                    }
                }
            }
        }

        sort($offenders);

        $this->assertSame([], array_unique($offenders), implode("\n", [
            'Estos consumidores del sistema de etiquetas vuelven a escribir su propia FORMA:',
            '  · '.implode("\n  · ", array_unique($offenders)),
            '',
            'El consumidor pone la COLOCACIÓN (dónde va, cuánto se inclina, de qué color sobre una',
            'foto). La forma la pone `.tag`. Escribirla aquí es como se vuelve a cinco formas.',
        ]));
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Instrumento
    // ─────────────────────────────────────────────────────────────────────────────────

    /** El cuerpo de la primera regla cuyo selector es exactamente ése, o `null`. */
    private function rule(string $selector): ?string
    {
        foreach ($this->siteRules() as $rule) {
            if ($rule['selector'] === $selector) {
                return $rule['body'];
            }
        }

        return null;
    }

    /**
     * ¿Este selector apunta a esa clase? Cuenta el selector compuesto (`.a .b`) y la lista (`.a, .b`),
     * pero **no** una clase que solo comparta prefijo.
     */
    private function targets(string $selector, string $class): bool
    {
        return preg_match('/(?<![-\w])'.preg_quote($class, '/').'(?![-\w])/', $selector) === 1;
    }

    /** Todas las vistas, sin comentarios de Blade. */
    private function bladeCorpus(): string
    {
        $chunks = [];
        $base = resource_path('views');

        /** @var \SplFileInfo $file */
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $chunks[] = (string) preg_replace('/\{\{--.*?--\}\}/s', ' ', (string) file_get_contents($file->getPathname()));
            }
        }

        return implode("\n", $chunks);
    }
}
