<?php

namespace App\Domain\Content\Services;

/**
 * **LAS BANDAS DE ENLACE: lo que una página ofrece cuando se acaba lo que venías a leer**
 * (carril de diseño Fase 3, artboard `Bandas PJP` turno 1 · `doc/bandas.md`).
 *
 * Son la segunda de las dos familias de banda del canvas, y la que NO interrumpe: *«la banda de
 * arriba interrumpe; las bandas de enlace esperan al final de una página a que se te acabe lo que
 * venías a leer»*. Dos piezas:
 *
 *  · la **GORDA** — *«contesta la pregunta que la página deja abierta»*, no lleva «a otra página»
 *    sin más. Como mucho UNA por página, y **opcional**: `/contacto` no lleva porque su acción es
 *    el formulario y una banda grande al lado compite con ella.
 *  · las **FINAS** — las páginas hermanas. DOS como mucho: *«tres es el menú otra vez»*.
 *
 * ❗❗❗ **LOS DESTINOS SE FILTRAN POR `SiteDestinations::pages()` Y NO POR SUS CONSTANTES.** Es la
 * razón de que esta clase no tenga ni un `route()` propio: así hereda gratis las dos reglas que
 * `#521` y `#536` ya pagaron —**una página en mantenimiento no se anuncia** y **el bar sin nombre
 * tampoco**, porque su ruta responde 404—. Una banda es un enlace como el del menú o el del pie, y
 * *«un enlace que no está en el inventario es relleno»*: si el destino no se puede visitar, la
 * banda no se pinta. Sin esto, la gorda de `/cumpleanos` llevaría a un 404 el día que el panel se
 * quede sin nombre de bar, y **nada fallaría**.
 *
 * ⚠️ **Un destino que no se puede ofrecer RETIRA la pieza, no la sustituye.** La pregunta y su
 * destino son lo mismo —«¿y los adultos qué comen?» solo la contesta el bar—, así que poner otro
 * destino contestaría otra cosa. Y en las finas tampoco se rellena el hueco: el canvas escribe que
 * las parejas están puestas *«por lo que la gente hace después, no por simetría»*, así que una
 * tercera elegida por el código sería exactamente el relleno que esta pieza existe para no ser.
 *
 * ⚠️ Solo compone datos de NAVEGACIÓN y los rótulos de su diccionario. Dónde se pinta cada pieza y
 * con qué forma lo decide `components/site/link-bands.blade.php`.
 */
final class LinkBands
{
    /**
     * **La pregunta que cada página deja abierta → la página que la contesta** (artboard `1e`).
     *
     * ⚠️ **`/contacto` no está y es una decisión escrita**: *«su acción es el formulario y una banda
     * grande al lado compite con ella»*. Una página sin fila aquí no lleva gorda.
     *
     * ❗❗❗ **`/bar` TAMPOCO ESTÁ, y el artboard sí le da una** («¿qué hay para saltar?» →
     * `/atracciones`). La retira `#536`, que es `[DECIDIDO owner]`: *«cero relleno de acción en la
     * página — el bar está fuera del modelo de reserva»*, con guarda propia en `BarPageTest`.
     * ⚠️⚠️ **Y el choque es más profundo que un botón de más: desde `#541` la ACCIÓN y el
     * SECUNDARIO son el MISMO cian, y sobre TINTA son indistinguibles** —medido: `--action` y
     * `--secondary` valen los dos `#1AA9DE` dentro de `[data-surface="ink"]`—, así que un botón
     * relleno dentro de la gorda **no puede decir «esto no es comprar»**. En una página cualquiera
     * eso da igual (lo que hace avanzar se rellena, la regla de `#541`); en ésta el owner decidió
     * que nada se vista de compra, y no hay forma de cumplir las dos cosas con un relleno.
     * ▶ Sus FINAS se quedan: son tarjetas-enlace sin relleno, así que no tocan esa decisión.
     *
     * ⚠️ Ninguna página se apunta a sí misma, y lo vigila `LinkBandsTest`: una banda que lleva a
     * donde ya estás no falla, solo no significa nada.
     */
    public const WIDE = [
        'atracciones' => 'normas',
        'precios' => 'cumpleanos',
        'cumpleanos' => 'bar',
        'normas' => 'precios',
        'servicios' => 'contacto',
    ];

    /**
     * **Las hermanas de cada página**, en el orden en que se ofrecen (artboard `1e`).
     *
     * El canvas las razona una a una y por CONDUCTA, no por simetría: *«de precios se va a
     * cumpleaños porque la pregunta de un grupo es siempre la misma, y de normas se va a contacto
     * porque quien lee normas suele tener un caso raro que preguntar»*.
     *
     * ⚠️ **Nunca más de dos.** El tope no es de gusto: *«tres es el menú otra vez»*, y el menú ya
     * existe en las doce vistas.
     */
    public const THIN = [
        'atracciones' => ['precios', 'bar'],
        'precios' => ['atracciones', 'normas'],
        'cumpleanos' => ['precios', 'normas'],
        'bar' => ['precios', 'cumpleanos'],
        'normas' => ['atracciones', 'contacto'],
        'servicios' => ['precios', 'cumpleanos'],
        'contacto' => ['precios', 'servicios'],
    ];

    /**
     * La banda GORDA de una página, o `null` si no lleva (o si su destino no se puede visitar hoy).
     *
     * ▶ **El rótulo es la RUTA ESCRITA del destino**, no un nombre propio, y sale de la misma
     * función que lo escribe en el menú y en la cabecera de página (`SiteDestinations::writtenPath`,
     * `#525`). ⚠️ **Es una desviación DECIDIDA del artboard**, que ahí escribe «Antes de venir»:
     * ese rótulo nombra una SECCIÓN de la portada y no el destino, y el propio canvas prohíbe lo
     * contrario — *«dos nombres para el mismo sitio son dos sitios para quien lee»*. Con la ruta,
     * las tres superficies que anuncian un destino dicen lo mismo.
     *
     * @return array{s: string, q: string, body: string, cta: string, url: string}|null
     */
    public static function wide(string $route): ?array
    {
        $destino = self::WIDE[$route] ?? null;

        if ($destino === null) {
            return null;
        }

        $pagina = self::offered()[$destino] ?? null;

        if ($pagina === null) {
            return null;
        }

        return [
            's' => $pagina['s'],
            'q' => (string) __('site.bands.ask.'.$route.'.q'),
            'body' => (string) __('site.bands.ask.'.$route.'.body'),
            'cta' => (string) __('site.bands.go.'.$destino.'.cta'),
            'url' => $pagina['url'],
        ];
    }

    /**
     * Las FINAS de una página: como mucho dos, y solo las que hoy se pueden visitar.
     *
     * ⚠️ El nombre dice **qué encuentras allí**, no cómo se llama el destino —«/bar · Qué hay para
     * comer», no «Bar»—: el rótulo de arriba ya dice a dónde vas.
     *
     * @return list<array{s: string, t: string, url: string}>
     */
    public static function thin(string $route): array
    {
        $finas = [];

        foreach (self::THIN[$route] ?? [] as $destino) {
            $pagina = self::offered()[$destino] ?? null;

            if ($pagina === null) {
                continue;
            }

            $finas[] = [
                's' => $pagina['s'],
                't' => (string) __('site.bands.go.'.$destino.'.what'),
                'url' => $pagina['url'],
            ];
        }

        return $finas;
    }

    /**
     * Las páginas que HOY se pueden visitar, indexadas por ruta.
     *
     * ⚠️ **No se memoiza a propósito.** `SiteDestinations::pages()` solo lee `settings`, que están
     * memoizados para toda la petición, así que las dos llamadas de esta pieza cuestan CERO
     * consultas — y lo asevera `LinkBandsTest`. Un `static` aquí sobreviviría entre peticiones
     * dentro del mismo proceso de test y congelaría el mantenimiento de una prueba en la siguiente.
     *
     * @return array<string, array{route: string, t: string, url: string, s: string, current: bool}>
     */
    private static function offered(): array
    {
        $porRuta = [];

        foreach (SiteDestinations::pages() as $pagina) {
            $porRuta[$pagina['route']] = $pagina;
        }

        return $porRuta;
    }
}
