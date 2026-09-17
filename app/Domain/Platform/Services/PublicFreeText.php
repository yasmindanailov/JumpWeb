<?php

namespace App\Domain\Platform\Services;

/**
 * **Texto libre que se PUBLICA bajo el dominio del parque**
 * (`specs/celebracion-e-invitacion.md` §4.5·12, §7.2·R9; `SEC-07`).
 *
 * El anfitrión escribe el nombre del homenajeado y una línea de «te invita», y eso se sirve en una
 * página pública de este dominio a gente que no conoce la web. La revisión adversarial lo dijo con un
 * ejemplo que basta para entender el riesgo: **una invitación podía decir «paga el regalo en este
 * enlace»**, con la credibilidad del parque detrás.
 *
 * ⚠️⚠️ **Rechaza, no limpia.** Quitar la URL de una frase y publicar el resto deja al anfitrión sin
 * saber qué se publicó —y a quien lo lea, una frase mutilada—. Se le dice que ahí no caben enlaces.
 *
 * ⚠️ **No es un escapado de HTML y no lo sustituye**: eso lo hace `{{ }}` al pintar. Esto decide qué
 * se ADMITE, que es una pregunta distinta y anterior.
 *
 * ⚠️ `AppServiceProvider::safeExternalUrl()` **no vale aquí**: aquel valida una URL que SÍ debe
 * publicarse (el mapa, las redes) exigiendo `https?://`; éste busca lo contrario —cualquier cosa que
 * un lector reconozca como enlace o como correo— dentro de una frase. Un `www.` sin esquema y un
 * `algo.com` pelado los pulsa la gente igual, y ninguno pasa aquel filtro.
 */
final class PublicFreeText
{
    /**
     * Lo que un lector reconoce como enlace o como dirección de correo, con o sin esquema.
     *
     * @var list<string>
     */
    private const FORBIDDEN = [
        // Esquema explícito, el caso obvio: http, https, ftp, javascript, data, tel, mailto…
        '#[a-z][a-z0-9+.\-]*://#i',
        '#\b(?:javascript|data|mailto|tel)\s*:#i',
        // Una dirección de correo.
        '#[^\s@]+@[^\s@]+\.[a-z]{2,}#i',
        // Un dominio pelado («playjump.es», «www.algo.com»): sin esquema, pero pulsable a la vista y
        // tecleable. El TLD se exige de 2 o más letras para no confundir «Ana S.A» ni un número.
        '#\bwww\.[a-z0-9\-]+#i',
        '#\b[a-z0-9](?:[a-z0-9\-]*[a-z0-9])?\.(?:com|net|org|es|eu|io|me|info|link|xyz|app|shop|store|bio|page|site|online|club|live)\b#i',
    ];

    /** ¿Este texto trae algo que un lector pulsaría? */
    public static function hasLink(string $value): bool
    {
        foreach (self::FORBIDDEN as $pattern) {
            if (preg_match($pattern, $value) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * El texto listo para publicar, o `null` si no se admite.
     *
     * Recorta los extremos y colapsa los espacios —incluidos los saltos de línea, que en una línea de
     * una tarjeta no significan nada— y corta a `$max`. Devolver `null` es lo que permite a quien
     * llama decirle al anfitrión POR QUÉ, en vez de publicar una frase a medias.
     */
    public static function clean(?string $value, int $max): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        if ($text === '' || self::hasLink($text)) {
            return null;
        }

        return mb_substr($text, 0, $max);
    }
}
