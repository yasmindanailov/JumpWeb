<?php

namespace App\Domain\Content\Services;

/**
 * **Los iconos del sistema de diseño nuevo: Lucide, en línea** (`DECISIONES #683`, `#686`).
 *
 * El diseño los pinta con su componente `Icon` (`Icon.jsx`), que baja cada SVG de
 * `lucide-static@0.544.0` en jsDelivr y lo inyecta en el documento. Aquí sale **el mismo fichero** —el
 * paquete vive versionado en `resources/icons/lucide/`, traído por `scripts/traer-lucide.py` con la
 * integridad de npm comprobada— y se le hacen **las mismas sustituciones**:
 *
 * · `width="24"` → `width="100%"` y `height="24"` → `height="100%"`, para que mande el tamaño del contenedor;
 * · con `fill`, `fill="none"` → `fill="currentColor"`: Lucide es de trazo, y una estrella sin relleno se
 *   lee como estrella VACÍA, o sea como una valoración de cero.
 *
 * ⚠️⚠️ **Solo la PRIMERA aparición de cada cadena**, como `String.prototype.replace` de JavaScript cuando
 * se le pasa una cadena. Un `str_replace` las cambiaría todas y un `<rect width="24">` dentro del dibujo
 * saldría distinto del diseño.
 *
 * ▶ A diferencia de {@see InlineSvg}, no hay lista blanca: el SVG no lo trae el operador, es un paquete
 * fijado por versión e integridad. Lo que sí se valida es el NOMBRE, que acaba siendo una ruta de disco.
 * Un nombre que no existe devuelve cadena vacía, como el `catch` del `Icon` del diseño: el hueco se ve,
 * la página no se rompe.
 */
class Lucide
{
    /** La versión que dibuja el diseño; la misma que `resources/icons/lucide/MANIFIESTO.json`. */
    public const VERSION = '0.544.0';

    /** Kebab-case de Lucide: `ticket`, `shield-check`, `arrow-up-0-1`. Nada que pueda salir de la carpeta. */
    private const NOMBRE = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** @var array<string, string> */
    private static array $cache = [];

    /** El SVG listo para incrustar, o cadena vacía si el nombre no es válido o no existe. */
    public static function svg(string $name, bool $fill = false): string
    {
        return self::$cache[$name.($fill ? '|relleno' : '')] ??= self::read($name, $fill);
    }

    /** Vacía la caché de proceso. Para los tests. */
    public static function forget(): void
    {
        self::$cache = [];
    }

    public static function path(string $name): string
    {
        return resource_path('icons/lucide/icons/'.$name.'.svg');
    }

    private static function read(string $name, bool $fill): string
    {
        if (preg_match(self::NOMBRE, $name) !== 1 || ! is_file(self::path($name))) {
            return '';
        }

        return self::transform((string) file_get_contents(self::path($name)), $fill);
    }

    /** Las sustituciones de `Icon.jsx`, en su orden y solo en la primera aparición. */
    public static function transform(string $svg, bool $fill = false): string
    {
        $svg = self::first('width="24"', 'width="100%"', $svg);
        $svg = self::first('height="24"', 'height="100%"', $svg);

        return $fill ? self::first('fill="none"', 'fill="currentColor"', $svg) : $svg;
    }

    private static function first(string $search, string $replace, string $subject): string
    {
        $pos = strpos($subject, $search);

        return $pos === false ? $subject : substr_replace($subject, $replace, $pos, strlen($search));
    }
}
