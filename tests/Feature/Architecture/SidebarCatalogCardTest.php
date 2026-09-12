<?php

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * **LA TARJETA GRANDE DEL CATÁLOGO** — la T4·3, con trinquete (`DECISIONES #552`).
 *
 * `[DECIDIDO owner]`, de las dos formas que el canvas dibujó para el paso 1 (`Decisiones SPA PJP` 02):
 * **tarjeta grande, SIN puerta**. Cada sección es una tarjeta con una franja arriba —icono grande,
 * nombre en rótulo y una frase que dice a qué vienes—, y las dos mitades del catálogo se separan
 * **cambiando de superficie**: una franja en tinta y la otra en papel marcado.
 *
 * ▶ **Lo que se descartó, y con su número**: una PANTALLA de categorías («¿a qué venís?») cobraría un
 * toque a todo el mundo para repartir **cinco productos en dos montones**, y detrás no hay ninguna
 * lista larga que evitar. El propio catálogo ya razona así con su buscador, que solo aparece por
 * encima de un umbral.
 *
 * Lo que este fichero impide:
 *
 *  1. que la tarjeta deje de ser una tarjeta (borde, radio y el recorte que hace de su franja un borde);
 *  2. que las dos superficies dejen de estar declaradas, o que el RECUENTO no siga a la suya —sobre
 *     tinta, una pastilla de Nube con texto Humo es una mancha clara ilegible—;
 *  3. **que vuelva el plegado**, que se retiró con su sujeto;
 *  4. **que la unidad del pack vuelva dentro del `nowrap` del precio**, que es el defecto MEDIDO de
 *     esta tanda;
 *  5. que la frase se quede sin texto en alguno de los tres idiomas — `t()` devuelve `''` en silencio
 *     (`#333`), así que una frase que falte no se ve: se ve un hueco.
 */
class SidebarCatalogCardTest extends TestCase
{
    private const HOJA = 'public/css/site.css';

    private const PLANTILLA = 'resources/js/sidebar/steps/CatalogStep.vue';

    private const MANIFIESTO = 'tests/Fixtures/sidebar-dom-manifest.json';

    /** El caso del manifiesto que congela el árbol del paso 1. */
    private const CASO = 'test_the_catalog_step_emits_the_same_tree_in_both_engines#1';

    public function test_the_scan_sees_its_subject(): void
    {
        $arbol = $this->arbolDelCatalogo();

        $this->assertGreaterThan(
            20,
            count($arbol),
            'el árbol congelado del catálogo trae muy pocos nodos: ¿ha cambiado el nombre del caso?',
        );

        $this->assertContains(
            'catalog-acc__sec',
            array_column($arbol, 'clase'),
            'el localizador del árbol ya no encuentra la sección: todo lo de abajo miraría el vacío.',
        );
    }

    public function test_each_section_is_a_card(): void
    {
        $regla = $this->regla('.catalog-acc__sec');

        foreach (['border:', 'border-radius:', 'overflow: hidden'] as $declaracion) {
            $this->assertStringContainsString(
                $declaracion,
                $regla,
                "La sección del catálogo ha dejado de ser una TARJETA (le falta `{$declaracion}`).\n".
                '⚠️ El `overflow` no es cosmética: es lo que hace que la franja termine en el borde '.
                'redondeado en vez de asomar por sus esquinas.',
            );
        }
    }

    public function test_the_two_band_surfaces_are_declared_and_the_count_follows_its_own(): void
    {
        foreach (['ink', 'paper'] as $superficie) {
            $this->assertNotSame(
                '',
                $this->regla(".catalog-acc__sec--{$superficie} .catalog-acc__head"),
                "Falta la franja `--{$superficie}`. Las dos mitades del catálogo se separan CAMBIANDO ".
                'de superficie: con una sola declarada, la bifurcación desaparece.',
            );

            $this->assertNotSame(
                '',
                $this->regla(".catalog-acc__sec--{$superficie} .catalog-acc__count"),
                "El recuento no sigue a la franja `--{$superficie}`.\n".
                '⚠️ Sobre TINTA, la pastilla de Nube con texto Humo es una mancha clara ilegible: el '.
                'recuento tiene que declarar su par en cada superficie, no heredar uno solo.',
            );
        }
    }

    /**
     * ❗❗❗ **LA PUERTA PLIEGA, Y LO ANUNCIA** (`#553`, `[DECIDIDO owner]`).
     *
     * ⚠️⚠️ **Este caso CAMBIÓ DE PREMISA y se reescribió, no se relajó.** En `#552` decía lo contrario
     * —«el plegado no vuelve»— y tenía razón entonces: lo que había era un mecanismo que *siempre
     * estaba en el mismo estado*, heredado de un paso del refactor viejo, y el día que un motor se
     * olvidó de emitir su clase **el catálogo entero salió a altura 0 y no se podía comprar nada**.
     * Hoy pliega de verdad y lo decide el cliente, así que lo que hay que vigilar es lo contrario: que
     * pliegue **y que el plegado se anuncie**. Es el precedente del `SlotOfferTest` de `#324`.
     *
     * ⚠️ **`aria-expanded` no es accesibilidad de adorno**: el estado de una puerta no puede vivir solo
     * en una clase, porque una clase no la lee nadie más que el CSS. Y el diff de árbol **descarta los
     * `:*` como andamiaje**, así que un `aria-expanded` dinámico le resulta invisible — es el agujero
     * que este proyecto ya pagó dos veces (`#58(f)` y `#P6`) y por eso se asevera aquí.
     */
    public function test_the_door_folds_and_announces_it(): void
    {
        $cuerpo = $this->regla('.catalog-acc__body');

        $this->assertStringContainsString(
            'grid-template-rows: 0fr',
            $cuerpo,
            'La puerta de categoría ha dejado de plegar: sin esto las dos secciones nacen abiertas y '.
            'la primera vista vuelve a ser la lista larga (1.033 px de contenido en una ventana de 650).',
        );

        $this->assertStringContainsString(
            'grid-template-rows: 1fr',
            $this->regla('.catalog-acc__sec.is-open .catalog-acc__body'),
            '`.is-open` ha dejado de abrir el cuerpo: el catálogo saldría a altura 0 y no se podría '.
            'comprar nada — que es exactamente lo que ya pasó una vez.',
        );

        // ⚠️ El relleno VERTICAL se colapsa con la rejilla: sin eso una puerta cerrada deja 24 px de
        // blanco debajo y se lee como una tarjeta rota. Medido al construirla.
        $this->assertMatchesRegularExpression(
            '/padding:\s*0 /',
            $cuerpo,
            'El relleno del cuerpo ya no se colapsa con la puerta: cerrada dejaría una franja de blanco.',
        );

        $plantilla = (string) file_get_contents(base_path(self::PLANTILLA));

        $this->assertMatchesRegularExpression(
            '/<button[^>]*class="catalog-acc__head"/s',
            $plantilla,
            'La cabecera del catálogo ha dejado de ser un `<button>`. Ahora PLIEGA: un `<div>` que '.
            'despliega no lo alcanza el teclado ni lo anuncia ningún lector de pantalla.',
        );

        // ⚠️⚠️ **Se desnudan los comentarios y se busca el ATRIBUTO, no la palabra — y esto lo dijo el
        // ARNÉS.** La primera versión aseveraba la subcadena `aria-expanded` sobre el fichero entero, y
        // la mutación que lo retiraba del marcado **SOBREVIVÍA**: la palabra sigue ahí, en el comentario
        // que explica por qué hace falta. ▶ *La prosa absolvía al código* — es la trampa de la subcadena
        // de `#253`, por la puerta de atrás: allí acusaba a un comentario y aquí lo salvaba.
        $desnuda = (string) preg_replace('#<!--.*?-->|/\*.*?\*/#s', '', $plantilla);

        foreach (['aria-expanded', 'aria-controls'] as $atributo) {
            $this->assertMatchesRegularExpression(
                '/:?'.preg_quote($atributo, '/').'=/',
                $desnuda,
                "La puerta ha dejado de emitir `{$atributo}`: su estado viviría solo en una clase, y una ".
                'clase no la lee nadie más que el CSS.',
            );
        }
    }

    /**
     * **Las DOS caras de la misma pieza.** Cerrada es una puerta alta en columna; abierta, la franja.
     * Si una de las dos disposiciones desaparece, la tarjeta se queda con una sola cara — y entonces o
     * no hay bifurcación o no hay productos.
     */
    public function test_the_head_has_its_two_layouts(): void
    {
        $cerrada = $this->regla('.catalog-acc__head');
        $abierta = $this->regla('.catalog-acc__sec.is-open .catalog-acc__head');

        $this->assertStringContainsString(
            'flex-direction: column',
            $cerrada,
            'La puerta CERRADA ha dejado de ser una tarjeta alta en columna: es lo que hace que la '.
            'bifurcación se vea al abrir el cajón.',
        );

        $this->assertStringContainsString(
            'min-height: 152px',
            $cerrada,
            'La puerta cerrada ha perdido su alto: sin él las dos categorías se leen como dos filas más.',
        );

        $this->assertStringContainsString(
            'flex-direction: row',
            $abierta,
            'La sección ABIERTA ha dejado de aplanarse en franja: seguiría ocupando 152 px por encima '.
            'de sus productos.',
        );
    }

    /**
     * ❗❗❗ **LA UNIDAD DEL PACK NO PUEDE VIVIR DENTRO DEL PRECIO** — el defecto MEDIDO de esta tanda.
     *
     * `.catalog__price` es `white-space: nowrap`, así que con la unidad dentro «desde 14,95 € por niño»
     * era **una sola línea irrompible**: medido en navegador a 390 px, la columna del precio de un pack
     * se llevaba **159 px de 324** y dejaba su descripción en **55**, recortada a mitad de palabra —
     * las entradas, sin sufijo, tenían 115—. Con la unidad en su propia línea las siete filas miden lo
     * mismo: **115 de texto y 99 de precio**, y cero recortes.
     *
     * ▶ *Una unidad pegada a su cifra dentro de un `nowrap` no es un detalle tipográfico: es una
     * columna que no se puede maquetar.*
     *
     * ⚠️ Se comprueba sobre el ÁRBOL CONGELADO y no sobre el texto de la plantilla: lo que importa no
     * es cómo esté escrito el marcado, es quién acaba siendo hijo de quién.
     */
    public function test_the_pack_unit_is_a_sibling_of_the_price_and_not_its_child(): void
    {
        $arbol = $this->arbolDelCatalogo();

        $precios = array_values(array_filter($arbol, fn (array $n): bool => $n['clase'] === 'catalog__price'));
        $unidades = array_values(array_filter($arbol, fn (array $n): bool => $n['clase'] === 'catalog__per'));

        $this->assertNotEmpty($precios, 'el árbol congelado ya no trae ningún `.catalog__price`');
        $this->assertNotEmpty(
            $unidades,
            'el árbol congelado ya no trae ningún `.catalog__per`: sin sujeto, este caso no vigila nada. '.
            '¿Ha dejado el catálogo de vender packs en el fixture?',
        );

        foreach ($unidades as $unidad) {
            $precio = $this->anteriorMenosProfundo($arbol, $unidad, 'catalog__price');

            $this->assertNotNull($precio, '`.catalog__per` aparece sin ningún `.catalog__price` delante');

            $this->assertLessThanOrEqual(
                $precio['sangria'],
                $unidad['sangria'],
                "`.catalog__per` ha vuelto a ser HIJO de `.catalog__price`.\n".
                "⚠️ Aquél es `white-space: nowrap`, así que la unidad vuelve a la línea de la cifra y la\n".
                "  columna del precio de un pack se come la descripción: medido, 159 px de 324 y el texto\n".
                '  en 55, cortado a mitad de palabra.',
            );
        }
    }

    /**
     * ⚠️⚠️ **`Lang::has()` con el TERCER parámetro a `false`, y esto lo dijo el ARNÉS.**
     *
     * La primera versión preguntaba con `trans($clave, [], $locale)` y comparaba con la clave — y
     * **la mutación «falta la frase en un idioma» SOBREVIVÍA**: Laravel cae al idioma de RESPALDO, así
     * que al borrar la frase del español devolvía la inglesa, que no es la clave, y el caso pasaba en
     * verde. Es exactamente la trampa que `#506` ya pagó en el carril de los correos.
     *
     * ▶ *Un localizador de traducciones que no desactiva el respaldo no comprueba un idioma: comprueba
     * que la cadena exista en ALGUNO.*
     */
    public function test_the_band_phrase_has_text_in_the_three_locales(): void
    {
        foreach (['es', 'en', 'fr'] as $locale) {
            foreach (['entries', 'services'] as $seccion) {
                $clave = "tickets.section_{$seccion}_sub";

                $this->assertTrue(
                    Lang::has($clave, $locale, false),
                    "Falta `{$clave}` en `{$locale}`. ⚠️ El cajón resuelve sus textos con `t()`, que ".
                    'devuelve una cadena VACÍA en silencio (`#333`): una frase que falte no falla, deja un hueco.',
                );

                $this->assertNotSame(
                    '',
                    trim((string) trans($clave, [], $locale)),
                    "`{$clave}` está vacía en `{$locale}`",
                );
            }
        }
    }

    /**
     * El nodo `catalog__price` más cercano ANTES de `$nodo` que no esté más adentro que él.
     *
     * @param  list<array{sangria:int, clase:string, i:int}>  $arbol
     * @param  array{sangria:int, clase:string, i:int}  $nodo
     * @return array{sangria:int, clase:string, i:int}|null
     */
    private function anteriorMenosProfundo(array $arbol, array $nodo, string $clase): ?array
    {
        $encontrado = null;

        foreach ($arbol as $candidato) {
            if ($candidato['i'] >= $nodo['i']) {
                break;
            }
            if ($candidato['clase'] === $clase) {
                $encontrado = $candidato;
            }
        }

        return $encontrado;
    }

    /**
     * El árbol congelado del paso 1, como lista de `{sangría, clase}` en orden de documento.
     *
     * ⚠️ Un nodo puede llevar VARIAS clases (`catalog-acc__sec.catalog-acc__sec--ink`): se emite una
     * entrada por clase, todas con la misma sangría, o buscar por nombre exacto perdería la mitad.
     *
     * @return list<array{sangria:int, clase:string, i:int}>
     */
    private function arbolDelCatalogo(): array
    {
        $manifiesto = json_decode((string) file_get_contents(base_path(self::MANIFIESTO)), true);
        $arbol = $manifiesto[self::CASO] ?? '';

        $out = [];
        $i = 0;

        foreach (explode("\n", (string) $arbol) as $linea) {
            $sangria = strlen($linea) - strlen(ltrim($linea));

            if (! preg_match('/class=([^\s>]+)/', $linea, $m)) {
                $i++;

                continue;
            }

            foreach (explode('.', $m[1]) as $clase) {
                if ($clase !== '') {
                    $out[] = ['sangria' => $sangria, 'clase' => $clase, 'i' => $i];
                }
            }

            $i++;
        }

        return $out;
    }

    /** El cuerpo de una regla de la hoja, con los comentarios blanqueados (la trampa de `#193`). */
    private function regla(string $selector): string
    {
        $css = (string) file_get_contents(base_path(self::HOJA));
        $ciego = (string) preg_replace_callback('#/\*.*?\*/#s', fn (array $m): string => str_repeat(' ', strlen($m[0])), $css);

        preg_match_all('/([^{}]*)\{([^{}]*)\}/', $ciego, $matches, PREG_SET_ORDER);

        $cuerpo = '';

        foreach ($matches as $regla) {
            $suyo = trim((string) preg_replace('/\s+/', ' ', $regla[1]));

            if ($suyo === $selector) {
                $cuerpo .= ' '.trim($regla[2]);
            }
        }

        return trim($cuerpo);
    }
}
