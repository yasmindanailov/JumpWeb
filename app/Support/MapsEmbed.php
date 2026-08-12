<?php

namespace App\Support;

/**
 * Normaliza lo que el operador pega en el ajuste «Mapa embebido» (#206). Google ofrece el
 * mapa como un `<iframe …>` COMPLETO, así que es natural pegar todo el bloque (o el `src`
 * con sus atributos detrás). Si se guardara tal cual, el `src` del iframe de la landing
 * quedaría contaminado y Google mostraría «Este contenido está bloqueado».
 *
 * `clean()` extrae la **URL de inserción limpia** de cualquiera de las tres formas:
 *  - el `<iframe … src="URL" …>` completo,
 *  - la `URL" width="600" …>` (src con atributos pegados detrás),
 *  - la URL sola.
 *
 * Devuelve la URL solo si es una inserción de Google Maps (`https://www.google.com/maps/embed…`),
 * o `null`. Se usa al GUARDAR (almacenar limpio) y al RENDERIZAR (defensa: sanea también un
 * valor ya guardado contaminado, sin necesidad de volver a guardarlo).
 */
class MapsEmbed
{
    public static function clean(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        // Si pegaron el <iframe> completo, quedarse con el contenido de src="…".
        if (preg_match('#\bsrc\s*=\s*["\']([^"\']+)["\']#i', $value, $m)) {
            $value = $m[1];
        }

        // Recortar cualquier cosa tras la URL (comilla de cierre, espacios, atributos, < o >).
        $value = preg_split('#["\s<>]#', $value, 2)[0] ?? '';
        $value = trim($value);

        // Inserción de Google Maps CON parámetros: el `https://www.google.com/maps/embed` pelado
        // (sin `?…`) carga un mapa roto. Exigir el `?` descarta esas URLs incompletas/erróneas
        // y a la vez admite ambas formas válidas (`embed?pb=…` y `embed/v1/place?key=…`).
        return (str_starts_with($value, 'https://www.google.com/maps/embed') && str_contains($value, '?'))
            ? $value
            : null;
    }
}
