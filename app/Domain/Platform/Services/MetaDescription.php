<?php

namespace App\Domain\Platform\Services;

use Illuminate\Support\Str;

/**
 * **Cómo se escribe un RESUMEN de contenido** — la `<meta name="description">` de una página, y el
 * `summary` que el menú de hechos publica al lado de las normas y de un texto legal (F5 · T2b,
 * `DECISIONES #653`).
 *
 * ❗❗ **Por qué existe: la regla estaba escrita CUATRO veces, cada una a su manera** (medido el 20-09):
 * en `pages/rules.blade.php` (seis nombres, ` · `, 155), en `pages/attractions.blade.php` (ocho
 * nombres, ` · `, 155), en `pages/text.blade.php` (el primer párrafo, sin etiquetas, 155) y en
 * `pages/events.blade.php` (la descripción del primer pack, **sin tope**). Cuatro vistas que se van a
 * la instancia, y con ellas la regla: cada landing la habría re-derivado, y la primera que olvidara el
 * tope publicaría un párrafo entero como descripción, que el buscador corta por donde le parece.
 *
 * ⚠️ **155 es la longitud del fragmento de un buscador**, no un número de esta casa: es lo que cabe en
 * un resultado antes de que lo corte el propio buscador. Por eso el resumen se corta AQUÍ, con puntos
 * suspensivos, y no allí, a mitad de palabra y sin avisar.
 *
 * ⚠️ **Y se corta en PALABRA ENTERA.** Las cuatro copias cortaban a mitad de palabra —medido el 20-09
 * en la web: «de que e...», «Para cu...», «la res...»—, que es lo que hace un `Str::limit` a secas. Con
 * un solo hogar la regla se arregla una vez: `preserveWords`, y el corte retrocede a la última palabra.
 *
 * ⚠️ El respaldo cuando no hay contenido (el título de la página) NO vive aquí: es una elección de la
 * página, y la página es de la instancia. Aquí se devuelve `null`, y lo que no existe no viaja
 * (`instancia-y-landing-fuera.md` §4.1.bis).
 */
final class MetaDescription
{
    /** Lo que cabe en el fragmento de un resultado de búsqueda. */
    public const MAX = 155;

    /** Con qué se separan los nombres cuando el resumen es una lista. */
    public const SEPARATOR = ' · ';

    /**
     * Un resumen hecho de NOMBRES: «Saltos libres · Tirolina · Parkour». `null` sin ninguno.
     *
     * ⚠️ Los vacíos se descartan ANTES de unir: un nombre en blanco dejaría dos separadores seguidos
     * (« ·  · »), que es exactamente lo que da `implode` a secas sobre una lista con un vacío dentro.
     *
     * @param  iterable<mixed>  $names
     */
    public static function fromNames(iterable $names): ?string
    {
        $limpios = [];

        foreach ($names as $name) {
            $limpio = self::collapsed(is_scalar($name) || $name instanceof \Stringable ? (string) $name : '');

            if ($limpio !== '') {
                $limpios[] = $limpio;
            }
        }

        return $limpios === [] ? null : Str::limit(implode(self::SEPARATOR, $limpios), self::MAX, preserveWords: true);
    }

    /**
     * Un resumen hecho de un TEXTO (un párrafo, una descripción): sin etiquetas, con los espacios
     * colapsados y acotado. `null` si no queda nada.
     *
     * ⚠️ Las etiquetas se quitan porque un párrafo legal se guarda con HTML dentro, y un `<strong>`
     * dentro del atributo `content` de una `<meta>` es una descripción rota.
     */
    public static function fromText(?string $text): ?string
    {
        $limpio = self::collapsed(strip_tags((string) $text));

        return $limpio === '' ? null : Str::limit($limpio, self::MAX, preserveWords: true);
    }

    /** Los espacios (saltos de línea incluidos) colapsados a uno, y recortado por los dos lados. */
    private static function collapsed(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
