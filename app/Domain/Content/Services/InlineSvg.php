<?php

namespace App\Domain\Content\Services;

/**
 * **Servir el logotipo de la instalación EN LÍNEA, y hacerlo seguro** (`DECISIONES #254`).
 *
 * El logotipo se servía por `<img>`, y así **no se puede animar por dentro**: el relevo que pidió
 * el owner —la silueta entra de un salto y se queda como letra— necesita alcanzar una pieza
 * concreta del dibujo, y eso solo existe si el SVG forma parte del documento.
 *
 * ⚠️⚠️ **Y ahí está el riesgo que este servicio existe para cerrar.** Un SVG dentro de un `<img>`
 * es inerte: el navegador no ejecuta sus scripts ni carga nada externo. **En línea, sí.** El
 * fichero lo pone el operador al instalar el paquete del cliente —igual que `client.css`—, así que
 * no es contenido de un desconocido; pero «lo puso alguien de confianza» no es una defensa, es una
 * suposición. Lo que se sirve pasa por una lista blanca:
 *
 * · fuera `<script>`, `<foreignObject>`, `<use href="…">` externo y `<image>` remota;
 * · fuera **todo atributo `on*`** (los manejadores en línea);
 * · fuera `javascript:` en cualquier atributo;
 * · y fuera la cabecera XML y el `<!DOCTYPE>`, que dentro de un HTML no pintan nada.
 *
 * ▶ **Si el fichero trae algo de eso, no se sanea a medias: se devuelve VACÍO.** Un logotipo que no
 * aparece se ve; un script que se cuela, no. La plantilla ya tiene su suelo —el nombre del sitio en
 * texto— así que quedarse sin dibujo degrada, no rompe.
 *
 * ⚠️ **El resultado se cachea en memoria por petición**: el armazón se pinta una vez por vista, pero
 * un `@include` de más no puede convertirse en dos lecturas de 64 KB de disco.
 */
class InlineSvg
{
    /** Etiquetas que NO pueden entrar en un SVG servido en línea. */
    private const FORBIDDEN_TAGS = ['script', 'foreignobject', 'iframe', 'embed', 'object', 'animate', 'set', 'handler'];

    /** @var array<string, string> */
    private static array $cache = [];

    /**
     * El contenido del SVG listo para incrustar, o cadena vacía si no es servible.
     *
     * ⚠️ **Devuelve cadena vacía también cuando el fichero no existe**, que es el caso normal: este
     * producto no lleva la marca de nadie (`DECISIONES #1`) y sin paquete instalado no hay logotipo.
     */
    public static function brand(string $path): string
    {
        if (array_key_exists($path, self::$cache)) {
            return self::$cache[$path];
        }

        return self::$cache[$path] = self::read($path);
    }

    /** Vacía la caché de proceso. Para los tests, que escriben ficheros distintos en el mismo path. */
    public static function forget(): void
    {
        self::$cache = [];
    }

    private static function read(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return '';
        }

        $svg = (string) file_get_contents($path);

        if ($svg === '' || ! self::isSafe($svg)) {
            return '';
        }

        // La cabecera XML y el DOCTYPE no pintan nada dentro de un documento HTML, y un `<?xml`
        // suelto en mitad del marcado es basura que algunos parsers arrastran.
        $svg = (string) preg_replace('/<\?xml.*?\?>/s', '', $svg);
        $svg = (string) preg_replace('/<!DOCTYPE.*?>/is', '', $svg);

        return self::mute(trim($svg));
    }

    /**
     * **Le quita al `<svg>` su propio nombre accesible.**
     *
     * ⚠️⚠️ **Medido al inlinarlo**: el fichero del cliente trae `role="img" aria-label="Play Jump
     * Park"` en su raíz. Dentro del envoltorio del armazón —que ya declara el nombre del sitio—
     * eso son **DOS nombres anidados para un solo enlace**, y quien navega por voz oye el del
     * dibujo, no el del sitio. Por `<img>` no pasaba: el contenido de un `<img>` no llega al árbol
     * de accesibilidad.
     * ▶ El dibujo pasa a ser decorativo (`aria-hidden`) y el nombre lo pone el envoltorio, que es
     * quien sabe cómo se llama esta instalación. Es la misma decisión que la variante sobre tinta
     * ya tomó con sus dos `<img>`.
     */
    private static function mute(string $svg): string
    {
        return (string) preg_replace(
            '/<svg\b([^>]*)>/i',
            '<svg'.'$1'.' aria-hidden="true" focusable="false">',
            (string) preg_replace('/\s(?:role|aria-label|aria-labelledby)\s*=\s*"[^"]*"/i', '', $svg, -1, $n),
            1,
        );
    }

    /**
     * ¿Es servible en línea?
     *
     * ⚠️ **Se decide sobre el TEXTO y no sobre un árbol parseado, y es a propósito**: lo que se va a
     * incrustar es el texto. Un parser que normaliza puede aceptar algo que luego el navegador lee
     * de otra forma —es la familia de fallos de los saneadores— y aquí no hace falta esa
     * sofisticación: si el fichero trae cualquiera de estas cosas, no se sirve.
     */
    private static function isSafe(string $svg): bool
    {
        if (stripos($svg, '<svg') === false) {
            return false;
        }

        foreach (self::FORBIDDEN_TAGS as $tag) {
            if (preg_match('/<\s*'.$tag.'\b/i', $svg) === 1) {
                return false;
            }
        }

        // Manejadores en línea: `onload=`, `onclick=`… ⚠️ Se exige el `=` para no confundirlos con
        // un atributo legítimo que empiece por «on» (`only-`, y en SVG nada más, pero la lista de
        // atributos de SVG crece y esto no depende de conocerla entera).
        if (preg_match('/\son[a-z]+\s*=/i', $svg) === 1) {
            return false;
        }

        if (preg_match('/javascript\s*:/i', $svg) === 1) {
            return false;
        }

        // Referencias EXTERNAS: `<use href="http…">`, `<image href="//…">`. Las internas (`#fig`)
        // son justamente lo que hace falta, así que solo se rechaza lo que sale del documento.
        if (preg_match('/(?:xlink:)?href\s*=\s*["\']\s*(?:[a-z][a-z0-9+.-]*:)?\/\//i', $svg) === 1) {
            return false;
        }

        return true;
    }
}
