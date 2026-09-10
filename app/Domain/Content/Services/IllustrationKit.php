<?php

namespace App\Domain\Content\Services;

/**
 * **EL KIT DE ILUSTRACIÓN DE LA INSTALACIÓN** (`specs/hueco-ilustracion.md`).
 *
 * El tercer hueco por instalación —tras el logotipo (`#254`) y el icono— y el primero para
 * ILUSTRACIÓN y no para marca. La instalación entrega **un** sprite de `<symbol>` y el producto lo
 * pinta con `<use>`, poniendo él el color, el tamaño y el tratamiento.
 *
 * ⚠️⚠️ **Aquí se valida DOS cosas que parecen una y no lo son**, y confundirlas fue el defecto que
 * la revisión adversarial encontró en el primer diseño:
 *
 *   1. **SEGURIDAD** — `<script>`, `on*=`, `javascript:`, referencias externas. Medido en Chrome con
 *      control: por `<use>` externo el SVG del cliente **es inerte** (ni su script corre ni su
 *      baliza sale; el mismo fichero EN LÍNEA disparó las dos). O sea que esto es defensa en
 *      profundidad, no la barrera que sostiene el mecanismo.
 *   2. **FIDELIDAD WHITE-LABEL** — los atributos de presentación. Y ESTO SÍ es la barrera:
 *      medido, un `<style>` dentro del fichero del cliente **gana** al `fill` que pone el producto
 *      en el `<use>`, y un `stroke-width` propio del `<symbol>` **gana** al del `<use>`. Las dos
 *      fugas son SILENCIOSAS: el dibujo aparece, con el color del cliente, y nada falla.
 *
 * ▶ **Todo o nada, como `InlineSvg`.** Si el fichero trae algo de esto no se sanea a medias: se
 * rechaza entero y la instalación se ve como una sin paquete. Sanear por dentro cambiaría el dibujo
 * de un cliente sin que él lo sepa, que es peor que decírselo.
 *
 * ⚠️ **Se decide sobre el TEXTO, no sobre un árbol parseado**, por el mismo motivo que `InlineSvg`:
 * lo que se va a servir es el texto, y un parser que normaliza puede aceptar algo que el navegador
 * lea de otra forma.
 */
class IllustrationKit
{
    /** Dónde vive el sprite dentro de `public/`. */
    public const PATH = 'img/client-kit.svg';

    /**
     * **LAS RANURAS DECORATIVAS QUE EL PRODUCTO DECLARA.**
     *
     * ⚠️⚠️ **Esta lista SOLO CRECE, y solo con su consumidor en el mismo cambio.** Nació vacía a
     * propósito (`specs/hueco-ilustracion.md` §15·3): declarar ranuras antes de que exista la
     * pantalla que las pinta es lo que dejó los 19 dibujos de `#257` esperando desde entonces.
     *
     * ⚠️ **Y tiene que ser CERRADA**: con sufijos libres ninguna guarda de paridad puede existir, y
     * una clave que el diseñador retire en su próximo export degradaría en silencio — que es lo que
     * `ProductIcon::CHOICES` ya dejó escrito.
     *
     * @var list<string>
     */
    public const SLOTS = [
        // ⚠️ **Esta lista ha estado VACÍA tres veces, y las tres por la misma regla: una ranura vive
        // exactamente lo que vive su consumidor.** Aquí llegaron a estar `slot-mancha-esquina`,
        // `slot-friso-1..5` y `slot-normas`; todas se fueron con la pantalla que las pintaba —la
        // última en `#300`, al revertir la T1 del idioma visual—.
        //
        // **`slot-zonas` nace con su pantalla en el mismo cambio** (`#302`): la mancha que va detrás
        // del titular de la sección de zonas y sus juegos. Su dibujo es `B1·02` del artboard —la
        // más ANCHA de las seis libres (relación 1,19 medida), que es la forma que pide ir detrás de
        // una palabra, y de las más ligeras (17,3 % de cobertura) para no pelearse con el texto—.
        // ⚠️ `B1·01` y `B1·04` no se podían usar: **ya viajan instaladas** como `--deco-blob-a/b`
        // (verificado byte a byte en `#286`).
        'slot-zonas',

        // ⚠️⚠️ **QUINTA VEZ QUE ESTA LISTA ENCOGE POR LA MISMA REGLA** (`#485`): aquí estaban
        // `slot-normas-registro` y `slot-normas-calcetines`, las dos manchas de la sección de
        // NORMAS de la portada. Esa sección la sustituye la 05 «Antes de venir», que el canvas
        // dibuja **sin ninguna pieza de dibujo**, así que las dos se van con su consumidor.
        // ▶ Dejarlas declaradas habría hecho que `kit:build` siguiera exigiendo dos dibujos que ya
        // no pinta nadie **y que la guarda de paridad se pusiera roja con el producto sano** — que
        // es exactamente lo que `#479` escribió al retirar `slot-tarifas`.
        // ▶ Con esto la portada gasta **UNA** de sus tres colocaciones de dibujo (`#292`).

        // ── 📜 LAS TRES QUE `#309` AÑADIÓ Y EL REDISEÑO SE HA LLEVADO ─────────────────────────
        // `slot-tarifas` (el friso familiar `G3` de la sección de tarifas) salió en `#479`, y
        // `slot-normas-registro` / `slot-normas-calcetines` en `#485`, cada una con la sección que
        // el canvas rehízo sin ninguna pieza de dibujo.
        // ▶ Queda escrito porque la regla que las gobierna es la misma que la de arriba y ya ha
        // decidido CINCO veces: **una ranura vive exactamente lo que vive su consumidor.** La que
        // SIGUE en pie y no la toca ninguno de estos cambios es la que vigila
        // `FacadeDecorationIsPerScreenTest`: ninguna pieza decorativa dentro de un bucle.
    ];

    /**
     * Etiquetas que no pueden entrar. Las de `InlineSvg` **más `<style>`**, que allí no hacía falta
     * (el logotipo es un dibujo suelto) y aquí es la fuga principal: una regla del cliente se aplica
     * al contenido clonado y gana al color del producto.
     */
    private const FORBIDDEN_TAGS = [
        'script', 'style', 'foreignobject', 'iframe', 'embed', 'object', 'animate', 'set', 'handler',
    ];

    /**
     * Atributos de presentación que GANAN al producto y por eso no pueden viajar en el símbolo.
     *
     * ⚠️ **`fill-rule` y `clip-rule` NO están y es a propósito**: no son color, son geometría —una
     * figura con hueco los necesita— y no compiten con nada que ponga el producto.
     * ⚠️ **`stroke-width` tampoco puede viajar vivo**, pero el dato del diseñador sí hace falta: va
     * como `data-stroke`, que es inerte. Lo aplica el producto donde sí manda (`§6.2`).
     */
    private const FORBIDDEN_ATTRS = [
        'fill', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-dasharray',
        'stroke-dashoffset', 'style', 'color', 'opacity', 'fill-opacity', 'stroke-opacity',
    ];

    /**
     * `filemtime` del kit instalado, o `false` si no hay.
     *
     * ⚠️ Hace las DOS cosas en una sola llamada a disco —existencia y cache-busting—, igual que
     * `client.css` en el layout y que el logotipo en `<x-site.brand>`.
     */
    public static function version(): int|false
    {
        return @filemtime(public_path(self::PATH));
    }

    /**
     * **El grosor que declara un símbolo, o `null`.**
     *
     * ⚠️⚠️ Viene de `data-stroke` y NO de `stroke-width`, y esa distinción es el mecanismo entero:
     * medido en navegador, un `stroke-width` vivo dentro del `<symbol>` **gana al del `<use>`** y
     * deja al producto sin decir nada (21,4 % de tinta, idéntico al control sin normalizar, frente
     * al 46,4 % del normalizado). Un `data-*` no pinta por sí solo, así que el dato del diseñador
     * llega sin imponerse: lo aplica el producto, donde sí manda.
     *
     * ▶ **Y no hay constante que valga**: la rejilla del artboard tiene piezas a 8 y a 12, y una
     * pose de `viewBox` 396 necesita 4,1 donde una constante derivada daría 49,5.
     * ▶ **`null` es una RESPUESTA, no una falta**: sin `data-stroke` manda el grosor del CSS.
     */
    public static function stroke(string $key): ?float
    {
        return self::meta()[$key]['stroke'] ?? null;
    }

    /**
     * **El `viewBox` que declara un símbolo, o `null`.**
     *
     * ⚠️⚠️ **Sin él, el `<svg>` que lo envuelve NO TIENE PROPORCIÓN**, y `height: auto` cae a los
     * 150 px por defecto de un elemento reemplazado: el dibujo sale con la caja deformada. Medido en
     * navegador — la caja daba **638×150** dentro de una tarjeta donde debía ser ~190 de ancho.
     * ▶ Por eso el contrato lo exige en cada `<symbol>` (§4.1·1) y el componente lo copia al
     * envoltorio: es lo que le da relación de aspecto intrínseca.
     */
    public static function viewBox(string $key): ?string
    {
        return self::meta()[$key]['viewBox'] ?? null;
    }

    /**
     * **¿Trae el kit instalado este dibujo?**
     *
     * ⚠️⚠️ **No es lo mismo que «hay kit», y confundirlo dejaba una CAJA VACÍA en la página.** El
     * componente solo miraba `version()`, o sea la existencia del FICHERO; con el kit instalado
     * pero sin ese `<symbol>`, el `<use>` no resuelve nada y lo que llega al documento es un
     * `<svg>` sin `viewBox` — que, por no tener proporción intrínseca, cae a los **150 px** por
     * defecto de un elemento reemplazado. Medido en la portada: las zonas `cap` y `cap2` metían
     * **dos cajas de 190×150** que no pintan nada.
     *
     * ▶ Y **el caso es el NORMAL, no el raro**: la gramática admite un `zone-<slug>` por cada zona
     * viva, pero una instalación dibuja las que quiere. El contrato **no exige** una por zona, y
     * hace bien.
     *
     * ▶ El modo de fallo elegido es la INVISIBILIDAD (`specs/hueco-ilustracion.md` §7), y esto es
     * lo que lo hace cierto también para la clave que falta: sin dibujo no se emite nada.
     */
    public static function has(string $key): bool
    {
        return array_key_exists($key, self::meta());
    }

    /**
     * El mapa `clave → {viewBox, stroke}` del kit instalado.
     *
     * ⚠️ Se cachea POR PROCESO y con el `filemtime` dentro de la clave: el armazón puede pedir
     * varios dibujos en una vista y esto no puede convertirse en N lecturas de disco — pero un test
     * que reescribe el fichero tiene que ver el nuevo.
     *
     * @return array<string, array{viewBox: ?string, stroke: ?float}>
     */
    private static function meta(): array
    {
        $version = self::version();

        if ($version === false) {
            return [];
        }

        $cacheKey = 'k'.$version;

        if (array_key_exists($cacheKey, self::$meta)) {
            return self::$meta[$cacheKey];
        }

        $svg = (string) @file_get_contents(public_path(self::PATH));
        $map = [];

        preg_match_all('#<symbol\b(?<attrs>[^>]*)>#i', $svg, $found, PREG_SET_ORDER);

        foreach ($found as $symbol) {
            $id = self::attr($symbol['attrs'], 'id');

            if ($id === null) {
                continue;
            }

            $stroke = self::attr($symbol['attrs'], 'data-stroke');

            $map[$id] = [
                'viewBox' => self::attr($symbol['attrs'], 'viewBox'),
                'stroke' => ($stroke !== null && is_numeric($stroke) && (float) $stroke > 0)
                    ? (float) $stroke
                    : null,
            ];
        }

        return self::$meta[$cacheKey] = $map;
    }

    /** Vacía la caché de proceso. Para los tests, que reescriben el mismo path. */
    public static function forget(): void
    {
        self::$meta = [];
    }

    /** @var array<string, array<string, array{viewBox: ?string, stroke: ?float}>> */
    private static array $meta = [];

    /**
     * Devuelve la lista de problemas. **Vacía = el kit es servible.**
     *
     * @param  list<string>  $zoneSlugs  los `slug` de las zonas VIVAS de esta instalación
     * @param  list<string>  $slots  las ranuras decorativas que el PRODUCTO declara
     * @return list<string>
     */
    public static function problems(string $svg, array $zoneSlugs = [], array $slots = []): array
    {
        $problems = [];

        if (trim($svg) === '' || stripos($svg, '<svg') === false) {
            return ['El fichero no contiene ningún `<svg>`.'];
        }

        // ── 1 · Seguridad, sobre el texto entero ──────────────────────────────────────────────
        foreach (self::FORBIDDEN_TAGS as $tag) {
            if (preg_match('/<\s*'.$tag.'\b/i', $svg) === 1) {
                $problems[] = "Contiene `<{$tag}>`, que no puede viajar en el kit."
                    .($tag === 'style'
                        ? ' ⚠️ Una regla del fichero del cliente GANA al `fill` que pone el producto'
                          .' en el `<use>`: el dibujo saldría con su color y nada fallaría.'
                        : '');
            }
        }

        // ⚠️ Se exige el `=` para no confundir un `on…` con un atributo legítimo que empiece por
        // «on». Misma forma que `InlineSvg`, y por el mismo motivo: la lista de atributos de SVG
        // crece y esto no depende de conocerla entera.
        if (preg_match('/\son[a-z]+\s*=/i', $svg) === 1) {
            $problems[] = 'Contiene un manejador en línea (`on…=`).';
        }

        if (preg_match('/javascript\s*:/i', $svg) === 1) {
            $problems[] = 'Contiene `javascript:` en algún atributo.';
        }

        // Referencias que SALEN del documento. Las internas (`#pose-1`) son justamente el mecanismo.
        if (preg_match('/(?:xlink:)?href\s*=\s*["\']\s*(?:[a-z][a-z0-9+.-]*:)?\/\//i', $svg) === 1) {
            $problems[] = 'Contiene una referencia EXTERNA (`href="//…"` o `href="https://…"`).';
        }

        // ── 2 · Los símbolos, uno a uno ───────────────────────────────────────────────────────
        preg_match_all('#<symbol\b(?<attrs>[^>]*)>(?<body>.*?)</symbol\s*>#is', $svg, $symbols, PREG_SET_ORDER);

        if ($symbols === []) {
            $problems[] = 'El fichero no declara ningún `<symbol>`: el kit no puede servir ningún dibujo.';

            return $problems;
        }

        $seen = [];

        foreach ($symbols as $i => $symbol) {
            $attrs = $symbol['attrs'];
            $body = $symbol['body'];

            $id = self::attr($attrs, 'id');
            $label = $id !== null ? "«{$id}»" : 'el símbolo n.º '.($i + 1);

            if ($id === null || $id === '') {
                $problems[] = 'El símbolo n.º '.($i + 1).' no declara `id`: nadie puede pedirlo.';
            } elseif (isset($seen[$id])) {
                $problems[] = "La clave {$label} está declarada dos veces.";
            } else {
                $seen[$id] = true;

                foreach (self::keyProblems($id, $zoneSlugs, $slots) as $p) {
                    $problems[] = $p;
                }
            }

            if (self::attr($attrs, 'viewBox') === null) {
                $problems[] = "El símbolo {$label} no declara `viewBox`: sin él no hay tratamiento"
                    .' contorno ni troquel, porque los dos necesitan la caja del dibujo.';
            }

            if (preg_match('/<title\b[^>]*>\s*\S/i', $body) !== 1) {
                $problems[] = "El símbolo {$label} no declara un `<title>` con texto.";
            }

            // ⚠️⚠️ **En el `<symbol>` Y en TODOS sus descendientes.** Mover un `fill` a un `<path>`
            // interior es exactamente el mismo defecto un nivel más abajo, y la primera versión de
            // esta regla en el diseño original solo miraba la raíz.
            foreach (self::FORBIDDEN_ATTRS as $attr) {
                // ⚠️ El `(?<![-\w])` NO es adorno: sin él `fill` casa dentro de `fill-opacity`,
                // `color` dentro de `stop-color` y `width` dentro de `stroke-width`. Este repo lo
                // ha pagado tres veces (`#252`, `#257`, `#266`).
                $re = '/(?<![-\w])'.preg_quote($attr, '/').'\s*=/i';

                if (preg_match($re, $attrs) === 1 || preg_match($re, $body) === 1) {
                    $problems[] = "El símbolo {$label} trae `{$attr}` propio."
                        .($attr === 'stroke-width'
                            ? ' ⚠️ Gana al del `<use>` en silencio. El grosor pretendido viaja como'
                              .' `data-stroke`, que es inerte y lo aplica el producto.'
                            : ' ⚠️ El atributo del original gana al que hereda del `<use>`: el'
                              .' producto no puede recolorearlo, y no falla nada.');
                }
            }

            $stroke = self::attr($attrs, 'data-stroke');

            if ($stroke !== null && (! is_numeric($stroke) || (float) $stroke <= 0)) {
                $problems[] = "El símbolo {$label} declara `data-stroke=\"{$stroke}\"`, que no es un número positivo.";
            }
        }

        return $problems;
    }

    /**
     * **La gramática es CERRADA, y eso es la mitad del mecanismo.**
     *
     * Con sufijos libres ninguna guarda de paridad puede existir: el diseñador retira una clave en
     * su próximo export y todo lo que la tuviera guardada degrada **en silencio** — que es
     * literalmente lo que `ProductIcon::CHOICES` ya dejó escrito.
     *
     * @param  list<string>  $zoneSlugs
     * @param  list<string>  $slots
     * @return list<string>
     */
    private static function keyProblems(string $id, array $zoneSlugs, array $slots): array
    {
        if (str_starts_with($id, 'zone-')) {
            $slug = substr($id, 5);

            return in_array($slug, $zoneSlugs, true) ? [] : [
                "La clave «{$id}» no corresponde a ninguna zona viva de esta instalación"
                .($zoneSlugs === [] ? '.' : ' (hay: '.implode(', ', $zoneSlugs).').')
                .' Un dibujo que no puede pedir nadie es peso muerto.',
            ];
        }

        if (str_starts_with($id, 'slot-')) {
            if (in_array($id, $slots, true)) {
                return [];
            }

            return [
                "La clave «{$id}» no es una ranura declarada por el producto"
                .($slots === []
                    ? '. ⚠️ Hoy NO hay ninguna declarada, y es a propósito: una ranura nace en el'
                      .' mismo cambio que su consumidor (`specs/hueco-ilustracion.md` §15·3).'
                    : ' (hay: '.implode(', ', $slots).').'),
            ];
        }

        return [
            "La clave «{$id}» está fuera de la gramática: tiene que ser `zone-<slug>` o `slot-<nombre>`.",
        ];
    }

    /** El valor de un atributo dentro de una cadena de atributos, o `null`. */
    private static function attr(string $attrs, string $name): ?string
    {
        // Mismo `(?<![-\w])` que arriba: `id` casa dentro de `data-id` sin él.
        $re = '/(?<![-\w])'.preg_quote($name, '/').'\s*=\s*(["\'])(?<v>.*?)\1/is';

        return preg_match($re, $attrs, $m) === 1 ? $m['v'] : null;
    }
}
