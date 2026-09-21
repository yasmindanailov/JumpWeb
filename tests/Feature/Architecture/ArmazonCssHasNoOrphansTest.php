<?php

namespace Tests\Feature\Architecture;

use Tests\Support\ReadsConsumerCorpus;
use Tests\Support\ReadsSiteStylesheets;
use Tests\TestCase;

/**
 * **EL CSS DEL ARMAZÓN NO PUEDE TENER HUÉRFANOS** (`docs/specs/armazon-y-menu.md`, tanda 2c·0).
 *
 * El armazón —la barra, el menú, los CTA de la esquina, el cajón móvil y el selector de idioma—
 * eran **194 reglas distintas y 733 declaraciones** repartidas entre las dos hojas. Al medirlo
 * para la tanda 2c salió que **33 reglas enteras y 121 declaraciones, el 17 %, no las pinta
 * nadie**: once clases que no aparecen ni en Blade, ni en el JS fuente, ni en el SSR, ni en el
 * bundle compilado. Retiradas en la 2c·0, quedan **161 reglas y 612 declaraciones**.
 *
 * No rompían nada. El daño es el de siempre: **quien va a reescribir el armazón migra 33 reglas
 * que no hacían falta**, y cada una parece una decisión de diseño que hay que respetar.
 *
 * ⚠️ **La primera cifra que se publicó —«232 · 819», «41 muertas»— era FALSA**, y el sesgo era el
 * contrario al de abajo: sumaba COINCIDENCIAS, no reglas. Una regla cuyo selector cita dos
 * familias contaba dos veces. **Un inventario que suma por etiqueta cuenta etiquetas, no
 * sujetos.**
 *
 * ⚠️⚠️ **Aquí un `grep` SÍ es concluyente, y en el cajón no lo sería.** El armazón es Blade SSR
 * y sus clases se escriben enteras; el cajón construye las suyas por concatenación en Vue, y por
 * eso `DEUDA.md` tiene abierta una ficha de ~50 reglas que *parecen* muertas y que un `grep` no
 * puede confirmar. **Por eso este fichero se limita a las familias del armazón** y no se
 * generaliza: fuera de ellas la premisa deja de ser cierta.
 *
 * ❗❗ **Y la lección que costó esta medición**: el primer instrumento dio **9** clases muertas y
 * son **11**. `.nav__scan` y `.nav__reserve` «vivían» dentro de un **comentario de Blade** que
 * explicaba precisamente que ya no se usan. Un corpus que no distingue código de comentario
 * **declara viva la deuda que el comentario está enterrando**. La regla escrita del repo —«un
 * `grep` que no encuentra no demuestra que no exista»— tiene simétrica: **un `grep` que SÍ
 * encuentra tampoco demuestra que exista.** El control positivo de abajo muerde ese caso exacto.
 */
class ArmazonCssHasNoOrphansTest extends TestCase
{
    use ReadsConsumerCorpus;
    use ReadsSiteStylesheets;

    private const SHEETS = 'public/css/*.css';

    /**
     * Las familias de clase que forman el armazón. **La lista solo encoge**: si una familia sale
     * de aquí es porque ya no existe, no porque estorbe.
     */
    private const FAMILIES = [
        'menu',         // el menú a pantalla completa (tanda 2c·1)
        'nav',          // la barra, la marca, los disparadores, el chip de cuenta, la hamburguesa
        'mob-menu',     // el cajón lateral de móvil
        'plan-select',  // el panel de los dos desplegables temáticos
        'cta-ghost',    // el CTA fantasma del header (registro)
        'cta-med',      // el CTA relleno del header (comprar)
        'lang-dd',      // el selector de idioma (vive en el pie)
        'book-bar',     // la barra flotante inferior de móvil
    ];

    /**
     * Clases del armazón que se declaran en CSS sin consumidor y **se toleran a propósito**.
     *
     * Cada entrada tiene que decir por qué. **La lista solo encoge**: es el mismo trato que
     * `ALLOWED_SELECTORS` en `RawColourIsNotATokenTest` y las tres listas de `ShapeScaleTest`.
     *
     * @var array<string, string>
     */
    private const ALLOWED_ORPHANS = [
        // (vacía: la tanda 2c·0 retiró las once que había)
    ];

    private ?array $sheets = null;

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda — sin esto, todo lo de abajo puede estar verde sin mirar nada
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **El escaneo de las hojas ve el corpus, y ve DENTRO de los `@media`.**
     *
     * 24 de las reglas del armazón viven dentro de una at-rule. Un parser que no descienda las
     * pierde y da un inventario que parece completo.
     */
    public function test_the_stylesheet_scan_sees_the_corpus_including_inside_at_rules(): void
    {
        $classes = $this->armazonClassesInSelectors();

        $this->assertGreaterThan(
            40, count($classes),
            'el escaneo encuentra menos de 40 clases del armazón y hay 62: el parser se ha '.
            'quedado ciego a parte del corpus.',
        );

        // Por NOMBRE, no por umbral: un contador no distingue «leo poco» de «leo otra cosa».
        foreach (['nav__brand', 'mob-menu__panel', 'menu__list', 'cta-med__body', 'lang-dd__panel'] as $needle) {
            $this->assertContains(
                $needle, $classes,
                "el escaneo de las hojas no ve `.{$needle}`, que está declarada",
            );
        }

        /*
         * ❗❗❗ **LA SONDA DE LAS AT-RULES CAMBIA DE FORMA, y es la tercera vez que se cae** (`#668`).
         * Era `.nav__links` hasta que la tanda 2c·1 la retiró, y `.book-bar-visible` hasta que el
         * widget de ofertas se fue —su ÚNICA regla era la que apartaba su lanzador, así que la clase
         * dejó de declararse—. Se buscó una cuarta y **no hay ninguna**: medido, hoy CERO clases del
         * armazón se declaran solo dentro de una at-rule.
         *
         * ▶ *Una sonda atada a un dato real caduca con el dato.* Ésta pregunta lo mismo al
         * INSTRUMENTO, que es lo que de verdad quería comprobar —que el recorrido desciende en las
         * at-rules— y no caduca cuando el CSS cambia.
         * ⚠️ Va en DOS mitades a propósito: sin la segunda, el instrumento podría descender
         * perfectamente sobre un corpus vacío y el caso pasaría sin mirar ninguna hoja.
         */
        $sonda = '.fuera { color: red } @media (min-width: 900px) { .dentro-de-at-rule { color: blue } }';

        $this->assertContains(
            '.dentro-de-at-rule', $this->selectors($sonda),
            'el recorrido NO desciende en las at-rules: todo lo que se declare dentro de un '.
            '`@media` quedaría sin vigilar y las guardas de abajo pasarían sin verlo.',
        );

        // Y la otra mitad: que sobre las hojas REALES haya at-rules con reglas dentro.
        $conAtRules = 0;

        foreach ($this->sheetContents() as $css) {
            $conAtRules += preg_match_all('/@media[^{]*\{/', $css);
        }

        $this->assertGreaterThan(
            20, $conAtRules,
            'las hojas del producto apenas tienen `@media`: o han cambiado de sitio, o el corpus '.
            'que lee esta guarda ya no es el de verdad.',
        );
    }

    /**
     * **El corpus de consumidores NO cuenta los comentarios.**
     *
     * ❗ Éste es el control que motivó el arreglo, y va con el caso REAL: `.nav__scan` no existía
     * en ningún sitio salvo dentro de un `{{-- … --}}` de Blade que explicaba que se había
     * sustituido. Con el corpus en crudo, la clase salía viva y sus 8 reglas se habrían migrado.
     */
    public function test_the_consumer_corpus_does_not_count_comments(): void
    {
        $cases = [
            'blade' => ['{{-- usa nav__zzz-sonda --}}', 'php'],
            'bloque' => ['/* nav__zzz-sonda */', 'js'],
            'linea' => ['// nav__zzz-sonda', 'js'],
            'html' => ['<!-- nav__zzz-sonda -->', 'html'],
        ];

        foreach ($cases as $kind => [$source, $extension]) {
            $this->assertStringNotContainsString(
                'nav__zzz-sonda', $this->stripComments($source, $extension),
                "el limpiador deja pasar una clase escrita dentro de un comentario de tipo «{$kind}»",
            );
        }

        // …y el simétrico: no puede cargarse el código de verdad.
        $this->assertStringContainsString(
            'nav__brand',
            $this->stripComments('<span class="nav__brand">{{-- nav__zzz-sonda --}}</span>', 'php'),
            'el limpiador de comentarios se está llevando por delante marcado real',
        );
    }

    /**
     * **El corpus está poblado y no casa con cualquier cosa.**
     */
    public function test_the_consumer_corpus_is_real(): void
    {
        $corpus = $this->consumerCorpus();

        $this->assertGreaterThan(
            1_000_000, strlen($corpus),
            'el corpus de consumidores es sospechosamente pequeño: alguna carpeta no se está leyendo',
        );

        $this->assertStringContainsString('nav__brand', $corpus, 'el corpus no ve una clase viva');
        $this->assertStringNotContainsString('nav__zzz-no-existe', $corpus, 'el corpus casa con cualquier cosa');
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo que se vigila
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Ninguna clase del armazón se declara en CSS sin que alguien la pinte.**
     *
     * Es un trinquete, no una limpieza: lo que evita es que la próxima sustitución deje atrás su
     * antecesora, que es exactamente cómo se acumularon las once que retiró la 2c·0.
     */
    public function test_no_armazon_class_is_declared_without_a_consumer(): void
    {
        $corpus = $this->consumerCorpus();
        $orphans = [];

        foreach ($this->armazonClassesInSelectors() as $class) {
            if (isset(self::ALLOWED_ORPHANS[$class])) {
                continue;
            }

            if (! str_contains($corpus, $class)) {
                $orphans[] = $class;
            }
        }

        sort($orphans);

        $this->assertSame(
            [], $orphans,
            'Estas clases del armazón se declaran en `public/css/` y no las pinta NADIE —ni Blade, '.
            "ni el JS fuente, ni el SSR, ni el bundle—:\n  .".implode("\n  .", $orphans)."\n\n".
            'O se retiran de la hoja, o entran en `ALLOWED_ORPHANS` con el motivo escrito. '.
            'Migrar CSS que no pinta nadie es trabajo que se tira, y encima parece una decisión '.
            'de diseño que hay que respetar.',
        );
    }

    /**
     * **Las excepciones declaradas siguen teniendo sujeto.**
     *
     * Una excepción cuyo selector ya no existe no es inofensiva: **tapa al siguiente que se
     * llame igual.** Lo cazó esta misma guarda en `ShapeScaleTest` durante la tanda 2b.
     */
    public function test_every_declared_exception_still_has_a_subject(): void
    {
        $declared = $this->armazonClassesInSelectors();
        $huerfanas = array_values(array_diff(array_keys(self::ALLOWED_ORPHANS), $declared));

        // Se asevera SIEMPRE, también con la lista vacía: un test que no ejecuta ninguna
        // aserción sale «risky» y deja de ser una guarda — es un hueco con aspecto de verde.
        $this->assertSame(
            [], $huerfanas,
            '`ALLOWED_ORPHANS` tolera clases que ya no se declaran en ninguna hoja: '.
            implode(', ', $huerfanas).'. Una excepción sin sujeto no es inofensiva: '.
            'tapa al siguiente que se llame igual.',
        );
    }

    /**
     * **Y AL REVÉS: ningún MODIFICADOR que un componente emite se queda sin regla** (`#253`).
     *
     * ⚠️⚠️ El caso de arriba vigila el CSS que nadie pinta. Éste vigila lo contrario, y es el que
     * faltaba: **`.lang-dd--up` se retiró en `#205`, `#233` volvió a pedirla y nadie la restauró**.
     * El componente aceptaba `:up`, el pie lo pasaba, y durante cinco días el prop **no hacía
     * absolutamente nada**: el panel seguía abriéndose hacia abajo y saliéndose de la pantalla. No
     * fallaba, no avisaba y ninguna guarda lo veía. Lo cazó el ojo del owner.
     *
     * ▶ *Un modificador sin regla es un `class=""` de más, y encima uno que alguien lee como si
     * significara algo.* Es más peligroso que una regla muerta: la regla muerta no promete nada.
     */
    public function test_no_modifier_a_component_emits_is_missing_its_rule(): void
    {
        // ⚠️⚠️ **Los COMENTARIOS se blanquean, y sin eso esta guarda nace CIEGA** — comprobado por
        // mutación en el momento de escribirla: al renombrar `.lang-dd--up` el caso seguía en
        // verde, porque el nombre aparecía en el comentario que explica la regla. Es la cuarta vez
        // que este repo tropieza con lo mismo: *un `grep` que SÍ encuentra tampoco demuestra que
        // la cosa exista donde crees*.
        $css = implode("\n", $this->siteSheets());

        $huerfanos = [];

        foreach (glob(resource_path('views/components/site/*.blade.php')) ?: [] as $vista) {
            $blade = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($vista));

            // Dos formas de emitir una clase: literal en `class="…"` y condicional en el array de
            // `$attributes->class([...])`, que es justo por donde entró el fallo.
            // ⚠️⚠️ **En el array condicional el modificador es la CLAVE, no el valor**
            // (`['lang-dd--up' => $up]`), y la primera versión de esta guarda buscaba el valor. Con
            // eso **no veía el único caso que existe**, así que salía verde con y sin la regla. La
            // mutación tuvo que hacerse tres veces para dar con ello: la primera renombró una de las
            // dos reglas, la segunda las dos —y seguía verde—, y solo entonces se miró el patrón.
            // ▶ *Una guarda que nunca ha estado roja no ha demostrado nada.*
            preg_match_all('/class="([^"]*)"/', $blade, $literales);
            preg_match_all("/'([a-z0-9_-]+(?:__[a-z0-9-]+)?--[a-z0-9-]+)'\s*=>/", $blade, $condicionales);

            $clases = $condicionales[1];
            foreach ($literales[1] as $lista) {
                foreach (preg_split('/\s+/', $lista) ?: [] as $c) {
                    $clases[] = $c;
                }
            }

            foreach (array_unique($clases) as $clase) {
                // Solo modificadores (`bloque--mod` o `bloque__elem--mod`) y nada interpolado.
                if (str_contains($clase, '{') || preg_match('/^[a-z][a-z0-9-]*(__[a-z0-9-]+)?--[a-z0-9-]+$/', $clase) !== 1) {
                    continue;
                }
                if (! str_contains($css, '.'.$clase)) {
                    $huerfanos[] = basename($vista).' · .'.$clase;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($huerfanos)), implode("\n", [
            'Estos modificadores se emiten desde un componente y NO tienen ninguna regla:',
            '  · '.implode("\n  · ", array_unique($huerfanos)),
            '',
            'Un modificador sin regla no falla: no hace nada. Y el siguiente que lea el componente',
            'va a creer que sí — que es lo que pasó con `.lang-dd--up`, que estuvo cinco días',
            'aceptando un `:up` que no abría el panel hacia arriba.',
        ]));
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Instrumento
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * Clases de las familias del armazón que aparecen en algún SELECTOR de las hojas.
     *
     * Se miran los selectores y no el fichero entero: una clase citada dentro de un `content:`
     * o de una `url()` no es una regla.
     *
     * @return list<string>
     */
    private function armazonClassesInSelectors(): array
    {
        $pattern = '/\.('.implode('|', array_map(
            fn (string $f) => preg_quote($f, '/').'[\w-]*',
            self::FAMILIES,
        )).')/';

        $found = [];

        foreach ($this->sheetContents() as $css) {
            foreach ($this->selectors($css) as $selector) {
                if (preg_match_all($pattern, $selector, $matches)) {
                    foreach ($matches[1] as $class) {
                        $found[$class] = true;
                    }
                }
            }
        }

        $out = array_keys($found);
        sort($out);

        return $out;
    }

    /**
     * Los selectores de una hoja, descendiendo en las at-rules.
     *
     * @return list<string>
     */
    private function selectors(string $css): array
    {
        $out = [];
        $this->walkSelectors($css, $out);

        return $out;
    }

    /** @param list<string> $out */
    private function walkSelectors(string $css, array &$out): void
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
                $this->walkSelectors($body, $out);
            } elseif (! str_starts_with($selector, '@')) {
                $out[] = $selector;
            }

            $selectorStart = $i + 1;
        }
    }

    /** @return list<string> */
    private function sheetContents(): array
    {
        if ($this->sheets !== null) {
            return $this->sheets;
        }

        $out = [];

        foreach (glob(base_path(self::SHEETS)) ?: [] as $path) {
            // ⚠️ **La hoja de una INSTALACIÓN queda fuera, y no es un descuido.** `client.css`
            // no es del producto: existe precisamente para que un cliente declare sus valores
            // —literales incluidos, que es de lo que está hecho un paquete de tema— y juzgarla
            // con las reglas del producto sería prohibirle hacer aquello para lo que existe.
            // ▶ Y además la hacía MENTIR al gate: una guarda que asevera por hoja cambiaba el
            // recuento de aserciones según si la máquina tenía o no un paquete instalado, así
            // que el `pre-push` bloqueaba en una máquina o en la otra. Medido el 2026-08-28 al
            // montar el paquete del segundo cliente. Mismo criterio que `SidebarStyleWiringTest`,
            // que enumera las hojas del producto en vez de barrer la carpeta.
            if (basename($path) === 'client.css') {
                continue;
            }

            // ⚠️ Los comentarios se BLANQUEAN conservando la longitud, no se borran: al borrarlos,
            // un `/* … */` pegado al selector de la línea de arriba deja el localizador ciego a esa
            // regla. Es la misma cautela que `ShapeScaleTest`, y allí ya costó una pasada.
            $out[] = (string) preg_replace_callback(
                '#/\*.*?\*/#s',
                fn (array $m) => str_repeat(' ', strlen($m[0])),
                (string) file_get_contents($path),
            );
        }

        return $this->sheets = $out;
    }
}
