<?php

namespace App\Domain\Content\Services;

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

    /**
     * **Las coordenadas del sitio, sacadas de la misma inserción** (`docs/specs/seo.md` §4, S4): el `geo` del JSON-LD
     * de negocio local, que Google recomienda con al menos 5 decimales.
     *
     * La inserción que da Google al «Compartir → Insertar un mapa» de una ficha lleva su centro en el parámetro `pb`:
     * `!2d<longitud>!3d<latitud>` (medido en la de PlayJump: `!2d-1.7144879!3d37.6527252`, el parque). Sin las dos, con
     * menos de 5 decimales o fuera de rango, `null`: mejor sin `geo` que con uno que Google no puede usar. La forma
     * `embed/v1/place?key=…` no las lleva: tampoco hay `geo`.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public static function coordinates(?string $value): ?array
    {
        $url = self::clean($value);
        if ($url === null
            || ! preg_match('/!2d(-?\d{1,3}\.\d{5,})/', $url, $lng)
            || ! preg_match('/!3d(-?\d{1,2}\.\d{5,})/', $url, $lat)) {
            return null;
        }

        $latitude = (float) $lat[1];
        $longitude = (float) $lng[1];

        return abs($latitude) <= 90 && abs($longitude) <= 180
            ? ['latitude' => $latitude, 'longitude' => $longitude]
            : null;
    }
}
