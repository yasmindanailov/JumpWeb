<?php

namespace Tests\Feature\Architecture;

use App\Http\Instancia\InstanceViews;
use Tests\Support\ReadsConsumerCorpus;
use Tests\Support\ReadsSiteStylesheets;
use Tests\TestCase;

/**
 * **NINGUNA PIEZA DEL MATERIAL DE FACHADA PUEDE QUEDARSE SIN PANTALLA QUE LA PINTE**
 * (`specs/elementos-fachada.md`, `specs/hueco-ilustracion.md`).
 *
 * ❗ **Esta guarda nace de un defecto REAL, no de una precaución.** La primera pasada del material
 * (`#286`) dejó en `site.css` dos reglas que **no pinta nadie**: `.brand-dots` —la tira punteada de
 * `C2`— y `.grain--zona` —la mitad densa en color de zona—. Las dos entraron con sus números al
 * dígito, las dos están documentadas como si fueran decisiones de diseño, y **ninguna aparece en
 * una sola pantalla**. Medido: `dots: 0` en las doce capturas del sitio.
 *
 * ▶ **Y ese es exactamente el mecanismo que dejó los 19 dibujos de `#257` esperando años**: material
 * que entra sin consumidor no falla, no avisa, y el siguiente que lo lea creerá que significa algo.
 * La regla escrita —*una pieza nace en el mismo cambio que su consumidor*— necesitaba un trinquete.
 *
 * ⚠️ **Hermana de {@see ArmazonCssHasNoOrphansTest}, y por separado a propósito**: aquélla se limita
 * a las familias del armazón porque fuera de ellas su premisa —clases escritas enteras en Blade—
 * deja de ser cierta. Aquí la premisa se cumple igual y por el mismo motivo (todo esto es Blade
 * SSR), así que el sujeto es otro pero el instrumento es el mismo: {@see ReadsConsumerCorpus}.
 *
 * ⚠️⚠️ **Lo que enseñó escribirla, y vale para la próxima pieza**: `.ilu--plano` y `.ilu--contorno`
 * salían huérfanas **con el producto sano**, porque el componente las componía con `'ilu--'.$trato`
 * y en el marcado no existe esa cadena. La salida NO fue una excepción: fue **escribir las tres
 * clases enteras** en el componente. Una clase que se arma por concatenación es invisible para
 * cualquier inventario —es la razón por la que `DEUDA.md` tiene ~50 reglas del cajón que *parecen*
 * muertas y nadie puede confirmar—, así que hacerla literal no es complacer a la guarda: es que el
 * producto se pueda medir.
 */
class FacadeCssHasNoOrphansTest extends TestCase
{
    use ReadsConsumerCorpus;
    use ReadsSiteStylesheets;

    /**
     * Las familias del material de fachada. **La lista solo crece con el material que entra.**
     *
     * ⚠️ `rays` no casa con `.offw-rays`: el patrón ancla la clase al PRINCIPIO. Son dos piezas
     * distintas —el estallido animado del widget de ofertas y el abanico quieto de la fachada— y
     * mezclarlas haría que retirar una tapase a la otra.
     */
    private const FAMILIES = [
        'grain',        // A1 · trama de puntos, y A2 · la que se apaga
        'spray',        // A5 · niebla
        'rays',         // A3 · el abanico quieto
        'brand-band',   // C3 · la cinta del eslogan
        'brand-strip',  // C2 · la tira, continua y en cuñas
        'brand-dots',   // C2 · la tira punteada
        'ilu',          // el hueco de ilustración por instalación
        'fac',          // la capa del mural y sus colocaciones (`#580`)
        'arc',          // F3 · el arco de rebote
        'trio',         // G4 · el trío de niños y su soporte
    ];

    /**
     * Clases de fachada que se declaran sin consumidor y **se toleran a propósito**.
     *
     * Cada entrada tiene que decir por qué, y **la lista solo encoge** — mismo trato que
     * `ALLOWED_SELECTORS` en `RawColourIsNotATokenTest` y que las tres listas de `ShapeScaleTest`.
     *
     * @var array<string, string>
     */
    private const ALLOWED_ORPHANS = [
        // (vacía: `.brand-dots` y `.grain--zona` se retiraron, y los tres tratamientos del hueco
        //  pasaron a escribirse enteros en el componente en vez de componerse por concatenación)
    ];

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Guarda de la guarda — sin esto, lo de abajo puede estar verde sin mirar nada
    // ─────────────────────────────────────────────────────────────────────────────────

    /** **El escaneo ve las hojas, y las ve por NOMBRE**: un contador no distingue «leo poco» de «leo otra cosa». */
    public function test_the_stylesheet_scan_sees_the_facade_family(): void
    {
        $classes = $this->facadeClassesInSelectors();

        // ⚠️ `spray` salió de esta lista en `#535`: la niebla A5 perdió su única pantalla al
        // retirarse el mapa de `/contacto` y se fue de la hoja, así que el control quedó apuntando a
        // una clase que ya no está —y puso este caso en rojo con el producto sano, que es
        // exactamente lo que una guarda-de-la-guarda tiene que hacer—. Las cinco que quedan siguen
        // declaradas y cubren las dos hojas.
        foreach (['grain', 'grain--fade', 'rays', 'brand-strip--wedge', 'ilu'] as $needle) {
            $this->assertContains(
                $needle, $classes,
                "el escaneo de las hojas no ve `.{$needle}`, que está declarada: el parser se ha ".
                'quedado ciego a parte del corpus.',
            );
        }
    }

    /** **El corpus está poblado y no casa con cualquier cosa.** */
    public function test_the_consumer_corpus_is_real(): void
    {
        $corpus = $this->consumerCorpus();

        $this->assertGreaterThan(
            1_000_000, strlen($corpus),
            'el corpus de consumidores es sospechosamente pequeño: alguna carpeta no se está leyendo',
        );
        $this->assertStringContainsString('grain', $corpus, 'el corpus no ve una clase viva');
        $this->assertStringNotContainsString('grain--zzz-no-existe', $corpus, 'el corpus casa con cualquier cosa');
    }

    /**
     * **El corpus NO se demuestra vivo a sí mismo.**
     *
     * ⚠️⚠️ Es la ceguera más peligrosa de esta guarda y no la ve ningún otro caso: si las hojas de
     * `public/css` entraran en el corpus, **toda clase declarada estaría «viva» por estar declarada**
     * y el test de huérfanas saldría verde vigilando la nada. El centinela es `--rayos-size`, un
     * token que hoy solo existe dentro de la hoja; si algún día alguien lo escribe en una vista, este
     * caso se cae y hay que elegir otro — que es lo que tiene que pasar, no quedarse verde.
     *
     * ⚠️⚠️ **Y aquí hay una lección de MUTACIÓN que cuesta creerse el resultado equivocado.** La
     * defensa son DOS piezas —el corpus solo recorre `public/build`, *y* además excluye
     * `public/css`— y **mutar una sola no muerde**, porque la otra sigue tapando el agujero:
     *
     *   · quitar la exclusión, a secas          → verde (el corpus no llega a `public/css` de todos modos)
     *   · ampliar el corpus a `public/`, a secas → verde (la exclusión lo detiene: la defensa FUNCIONA)
     *   · **las dos a la vez**                   → **ROJO**, que es el defecto de verdad
     *
     * ▶ *Una mutación que no muerde puede ser una mutación DÉBIL* (`panel-navegacion.md` §7.4·2).
     * Y antes de eso pasó lo de siempre: la primera pasada de esta mutación **no se llegó a aplicar**
     * —`python3 -c "…"` entre comillas dobles y bash expandiendo `$file` en el patrón de búsqueda—,
     * o sea que el «no muerde» era del instrumento. Se comprueba que la mutación está PUESTA antes de
     * concluir nada de ella.
     */
    public function test_the_corpus_does_not_include_the_stylesheets_it_measures(): void
    {
        $this->assertStringContainsString(
            '--rayos-size', implode("\n", $this->siteSheets()),
            'el centinela ya no está en la hoja: elige otro token que solo viva en `public/css`.',
        );

        $this->assertStringNotContainsString(
            '--rayos-size', $this->consumerCorpus(),
            'las hojas de `public/css` han entrado en el corpus de consumidores: con eso, TODA clase '.
            'declarada se demuestra viva a sí misma y la guarda de huérfanas vigila la nada.',
        );
    }

    /**
     * **El corpus NO cuenta los comentarios**, con su simétrico.
     *
     * Aquí muerde de verdad: el bloque de `site.css` que documenta el friso retirado **cita las
     * clases que se fueron**, y el propio componente del hueco cita `.menu__blob` en un comentario.
     */
    public function test_the_consumer_corpus_does_not_count_comments(): void
    {
        foreach ([['{{-- grain--zzz-sonda --}}', 'php'], ['/* grain--zzz-sonda */', 'js'],
            ['// grain--zzz-sonda', 'js'], ['<!-- grain--zzz-sonda -->', 'html']] as [$source, $extension]) {
            $this->assertStringNotContainsString(
                'grain--zzz-sonda', $this->stripComments($source, $extension),
                'el limpiador deja pasar una clase escrita dentro de un comentario',
            );
        }

        $this->assertStringContainsString(
            'grain--fade',
            $this->stripComments('<div class="grain grain--fade">{{-- grain--zzz-sonda --}}</div>', 'php'),
            'el limpiador de comentarios se está llevando por delante marcado real',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Lo que se vigila
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * **Ninguna clase de fachada se declara en CSS sin que alguna pantalla la pinte.**
     *
     * ⚠️ Desde `#655` (F5 · T2b) una pantalla puede vivir en la INSTANCIA: el CSS sigue aquí (en la T2 solo
     * se mudan las vistas) y su consumidor no. Esas clases las declara el producto en
     * `InstanceViews::MATERIAL_CONSUMIDO_POR_LA_INSTANCIA`, y aquí se exige de ellas lo contrario: que
     * NINGUNA pantalla del producto las pinte, o la entrada sobra y tapa al siguiente.
     */
    public function test_no_facade_class_is_declared_without_a_consumer(): void
    {
        $corpus = $this->consumerCorpus();
        $orphans = [];
        $fuera = $this->instanceCssClasses();

        foreach ($this->facadeClassesInSelectors() as $class) {
            if (isset(self::ALLOWED_ORPHANS[$class])) {
                continue;
            }

            if (in_array($class, $fuera, true)) {
                $this->assertStringNotContainsString(
                    $class, $corpus,
                    "`.{$class}` está declarada como consumida por la instancia y una pantalla del producto la pinta: sobra la entrada.",
                );

                continue;
            }

            if (! str_contains($corpus, $class)) {
                $orphans[] = $class;
            }
        }

        sort($orphans);

        $this->assertSame([], $orphans, implode("\n", [
            'Estas piezas de fachada se declaran en `public/css/` y NO las pinta ninguna pantalla:',
            '  .'.implode("\n  .", $orphans),
            '',
            'O se retiran de la hoja, o entran en `ALLOWED_ORPHANS` con el motivo escrito — o, si su pantalla',
            'vive en la instancia, en `InstanceViews::MATERIAL_CONSUMIDO_POR_LA_INSTANCIA` con la vista que la pinta.',
            'La regla del carril es que una pieza nace en el MISMO cambio que su consumidor: material',
            'sin pantalla no falla ni avisa, y es lo que dejó los 19 dibujos de `#257` esperando años.',
        ]));
    }

    /**
     * Las clases de fachada que hoy pinta una vista de la instancia (las ranuras del kit de esa misma lista
     * no son CSS y las vigila `ZonesSectionTest`).
     *
     * @return list<string>
     */
    private function instanceCssClasses(): array
    {
        return array_values(array_filter(
            array_keys(InstanceViews::MATERIAL_CONSUMIDO_POR_LA_INSTANCIA),
            fn (string $pieza): bool => ! str_starts_with($pieza, 'slot-'),
        ));
    }

    /**
     * **Las excepciones declaradas siguen teniendo sujeto.**
     *
     * Una excepción cuyo selector ya no existe no es inofensiva: **tapa al siguiente que se llame
     * igual.** Se asevera SIEMPRE, también con la lista vacía: un test sin aserciones sale «risky»
     * y deja de ser una guarda.
     */
    public function test_every_declared_exception_still_has_a_subject(): void
    {
        $declared = $this->facadeClassesInSelectors();
        $stale = array_values(array_diff(
            [...array_keys(self::ALLOWED_ORPHANS), ...$this->instanceCssClasses()],
            $declared,
        ));

        $this->assertSame(
            [], $stale,
            '`ALLOWED_ORPHANS` o `MATERIAL_CONSUMIDO_POR_LA_INSTANCIA` toleran clases que ya no se declaran en ninguna hoja: '.
            implode(', ', $stale).'. Una excepción sin sujeto tapa al siguiente que se llame igual.',
        );
    }

    // ─────────────────────────────────────────────────────────────────────────────────
    //  Instrumento
    // ─────────────────────────────────────────────────────────────────────────────────

    /**
     * Clases de las familias de fachada que aparecen en algún SELECTOR de las hojas del producto.
     *
     * Se miran los selectores y no el fichero entero: una clase citada dentro de un `content:` o de
     * una `url()` no es una regla. `siteRules()` desciende en los `@media`, donde vive parte de esto.
     *
     * @return list<string>
     */
    private function facadeClassesInSelectors(): array
    {
        $pattern = '/\.('.implode('|', array_map(
            fn (string $f) => preg_quote($f, '/').'[\w-]*',
            self::FAMILIES,
        )).')/';

        $found = [];

        foreach ($this->siteRules() as $rule) {
            if (preg_match_all($pattern, $rule['selector'], $matches)) {
                foreach ($matches[1] as $class) {
                    $found[$class] = true;
                }
            }
        }

        $out = array_keys($found);
        sort($out);

        return $out;
    }
}
