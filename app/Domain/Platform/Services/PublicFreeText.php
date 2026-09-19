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
        $text = self::normalize($value);

        if ($text === '' || self::hasLink($text)) {
            return null;
        }

        return mb_substr($text, 0, $max);
    }

    /**
     * ¿Este texto se RECHAZA —trae algo pulsable—, frente a estar simplemente vacío? (T6·1.)
     *
     * ⚠️⚠️ **`clean()` devuelve `null` por DOS motivos distintos y quien escribe necesita
     * distinguirlos**: un campo vacío es «no lo toques» y no hay nada que decirle al anfitrión; uno
     * con un enlace es un texto suyo que NO se ha publicado, y callarlo lo dejaría creyendo que sí.
     * La pantalla de la API podía no distinguirlos porque no dice nada; la del anfitrión sí lo dice.
     *
     * ▶ No repite la regla: pregunta por el MISMO predicado y con la MISMA normalización que
     * {@see clean()}. Un `hasLink()` sobre el valor crudo respondería distinto en cuanto alguien
     * escribiera un enlace partido por un salto de línea.
     */
    public static function rejects(?string $value): bool
    {
        $text = self::normalize($value);

        return $text !== '' && self::hasLink($text);
    }

    /** Los extremos recortados y los espacios colapsados: la forma en la que se decide todo aquí. */
    private static function normalize(?string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }
}
