<?php

namespace App\Domain\Platform\Services;

/**
 * **La dirección del parque, ESCRITA** — un hecho, un sitio (F5 · T2b, `DECISIONES #650`).
 *
 * El panel guarda la dirección en dos líneas (`address.line1`, `address.line2`) porque así se
 * teclea. Quien la ENSEÑA necesita una sola, y componerla tiene una regla con su porqué:
 *
 * ⚠️⚠️ **Se unen con COMA y no con espacio.** «Ctra. de Prueba, 1 30000 Ciudad» se lee como si el
 * `30000` fuera parte del número de portal; con la coma, «Ctra. de Prueba, 1, 30000 Ciudad» se lee
 * como lo que es. Es la regla que la página de contacto ya aplicaba, y la que el JSON-LD de
 * schema.org aplicaba por su cuenta.
 *
 * ❗❗❗ **Por qué existe esta clase: la regla estaba escrita DOS VECES** (medido el 19-09) — en línea
 * dentro de `pages/contact.blade.php` y en `Content\Services\StructuredData::postalAddress()`, con
 * filtros distintos (`filled()` contra `array_filter()`, que no tratan igual un `"0"`). Y la copia
 * del JSON-LD llegó a producir un **verde falso**: un caso que aseveraba la dirección sobre la
 * página entera pasaba porque el `streetAddress` del JSON-LD decía lo correcto aunque el bloque
 * visible dijera otra cosa (está contado en `ContactPageTest`).
 *
 * ▶ Y la tercera copia estaba a punto de escribirse en cada INSTANCIA: `GET /api/v1/site` publicaba
 * `line1` y `line2` sueltas, así que toda landing que pintara su bloque de contacto habría tenido
 * que re-derivar la coma — y la primera que la olvidara publicaría una dirección que se lee mal,
 * sin que nada fallara. Por eso la API publica ahora también la línea ya escrita.
 */
final class VenueAddress
{
    /**
     * Las dos líneas del panel, en una sola. `null` si no hay ninguna.
     *
     * ⚠️ Se recorta cada línea y se descartan las vacías **antes** de unir: con una línea sola no
     * puede salir una coma colgando («Ctra. de Prueba, 1, »), que es lo que haría un `implode` a
     * secas sobre un array con un vacío dentro.
     */
    public static function written(?string $line1, ?string $line2): ?string
    {
        $partes = [];

        foreach ([$line1, $line2] as $linea) {
            $limpia = trim((string) $linea);

            if ($limpia !== '') {
                $partes[] = $limpia;
            }
        }

        return $partes === [] ? null : implode(', ', $partes);
    }
}
