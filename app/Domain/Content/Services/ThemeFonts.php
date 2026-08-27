<?php

namespace App\Domain\Content\Services;

/**
 * **Las familias tipográficas de ESTA instalación** (`specs/tema-por-instalacion.md` §4.6).
 *
 * Hasta hoy la lista de fuentes estaba escrita a mano en TRES layouts, así que un cliente podía
 * redefinir `--font-display` y `--font-body` desde su `client.css` y **el fichero no se descargaba
 * nunca**: el token cambiaba y el navegador caía al `system-ui` del stack. El tema de tipografía
 * era, literalmente, un token sin fuente detrás.
 *
 * ▶ **Por qué config y no BD.** `landing-white-label.md` §4.5.5 lo decidió: la tipografía va en el
 * paquete de la instalación, no en el panel — «el tema en BD se queda en COLOR, que es lo único que
 * tiene que cruzar al panel y a los correos». Además así no entra en el payload memoizado del
 * composer global ni en el presupuesto de consultas de la home (`PERF-01`/`PERF-02`).
 *
 * ⚠️⚠️ **EL HOST NO ES CONFIGURABLE, Y ESO ES LA MITAD DEL DISEÑO** (`SEC-04`/`SEC-07`).
 * `SecurityHeaders` permite un ÚNICO origen de fuentes, `fonts.bunny.net`. Si el host se pudiera
 * cambiar, apuntar a otro no daría un error: la CSP lo bloquearía **en silencio** y la instalación
 * se quedaría sin tipografía sin que nada fallara. Lo variable es solo lo que se puede variar sin
 * romper nada — la lista de familias— y encima pasa por allowlist, porque acaba dentro de una URL
 * en el `<head>`, que es un sink de inyección.
 *
 * ▶ **Y Bunny sirve las mismas familias que Google** (comprobado una a una para el segundo cliente:
 * Bungee, Hanken Grotesk, Permanent Marker, JetBrains Mono y Lilita One, HTTP 200), así que un
 * cliente no necesita abrir un origen nuevo para traerse su tipografía.
 *
 * Como {@see ThemeSettings}, es **DEFENSIVO**: ante un valor inválido devuelve el del producto y
 * nunca lanza. Una instalación con la fuente mal escrita se ve como el producto, no rota.
 */
class ThemeFonts
{
    /**
     * El único origen que la CSP permite. **No sale de config a propósito**: cambiarlo sin cambiar
     * `SecurityHeaders` deja la web sin fuentes y sin aviso.
     */
    public const HOST = 'https://fonts.bunny.net';

    /**
     * La lista del PRODUCTO. Es el suelo: lo que se ve si una instalación no declara la suya, o si
     * la declara mal.
     */
    public const DEFAULT_FAMILIES = 'bricolage-grotesque:400,600,700,800|space-grotesk:400,500,600,700|jetbrains-mono:400,500';

    /**
     * Una familia con sus pesos: `slug-en-minusculas:400,600`.
     *
     * El slug es el de Bunny (minúsculas y guiones), **no** el nombre visible: ése vive en
     * `--font-display`/`--font-body` y lo redefine `client.css`. Son dos mecanismos que ya existían
     * y que hasta ahora no se hablaban.
     */
    private const FAMILY = '/^[a-z0-9]+(?:-[a-z0-9]+)*:[1-9]00(?:,[1-9]00)*$/';

    /** Techo defensivo: una lista más larga que esto no es una instalación, es un accidente. */
    private const MAX_FAMILIES = 8;

    /** La URL de la hoja de fuentes, ya saneada. Nunca lanza. */
    public static function stylesheetUrl(): string
    {
        return self::HOST.'/css?family='.self::families();
    }

    /**
     * La lista efectiva de familias.
     *
     * ⚠️ **Si UNA sola parte es inválida se descarta la lista ENTERA**, no solo esa parte. Servir
     * media tipografía es peor que servir la del producto: la página se pinta con dos familias que
     * nadie ha elegido y el operador no tiene forma de notar qué falta.
     */
    public static function families(): string
    {
        $raw = rescue(fn (): mixed => config('theme.fonts'), null, false);
        $raw = is_string($raw) ? trim($raw) : '';

        if ($raw === '') {
            return self::DEFAULT_FAMILIES;
        }

        $parts = explode('|', $raw);

        if (count($parts) > self::MAX_FAMILIES) {
            return self::DEFAULT_FAMILIES;
        }

        foreach ($parts as $part) {
            if (preg_match(self::FAMILY, $part) !== 1) {
                return self::DEFAULT_FAMILIES;
            }
        }

        return implode('|', $parts);
    }
}
