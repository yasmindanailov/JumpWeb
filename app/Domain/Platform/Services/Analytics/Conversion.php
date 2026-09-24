<?php

namespace App\Domain\Platform\Services\Analytics;

/**
 * **Una compra lista para comunicar a un anunciante** (`docs/specs/analitica.md` §4.3, T3b·2): lo que Meta
 * Conversions API y TikTok Events API reciben, y NADA más. Es un valor: quien la compone (el job de Booking,
 * que sí ve el pedido y a su titular) ya ha hasheado el correo y el teléfono; aquí no entra un dato en claro.
 *
 * ⚠️ `eventId` es el CÓDIGO del pedido: el píxel del navegador manda el mismo id (`cajon/pixels.js`) y la
 * plataforma cuenta la compra UNA vez aunque lleguen las dos. `value` es lo PAGADO en línea, en unidades.
 */
final class Conversion
{
    /**
     * @param  ?string  $emailHash  SHA-256 del correo normalizado (minúsculas, sin espacios), o `null`
     * @param  ?string  $phoneHash  SHA-256 del teléfono en dígitos con prefijo de país, o `null`
     * @param  array<string, string>  $browserIds  `fbp`/`fbc`/`ttp`, tal como el sello del pedido las guardó
     * @param  ?string  $clickId  el `ttclid` del clic de TikTok, si lo hubo
     */
    public function __construct(
        public readonly string $eventId,
        public readonly int $eventTime,
        public readonly float $value,
        public readonly string $currency,
        public readonly ?string $emailHash,
        public readonly ?string $phoneHash,
        public readonly array $browserIds = [],
        public readonly ?string $clickId = null,
        public readonly ?string $sourceUrl = null,
    ) {}

    /** SHA-256 de un dato ya normalizado; `null` si no hay dato. Es lo único que sale de esta casa. */
    public static function hash(?string $normalized): ?string
    {
        return $normalized === null || $normalized === '' ? null : hash('sha256', $normalized);
    }

    /** El correo como las plataformas lo esperan antes de hashear: minúsculas y sin espacios. */
    public static function normalizeEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' || ! str_contains($email, '@') ? null : $email;
    }

    /**
     * El teléfono como las plataformas lo esperan antes de hashear: solo dígitos CON prefijo de país. Un número
     * de nueve cifras que empieza por 6, 7, 8 o 9 es español y gana el `34`; con más cifras se toma como ya
     * prefijado (la convención de la casa guarda solo dígitos, sin `+`).
     */
    public static function normalizePhone(?string $digits): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $digits) ?? '';

        // El prefijo internacional «00» no es parte del número: `0033…` es `33…`.
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 9 && preg_match('/^[6789]/', $digits) === 1) {
            return '34'.$digits;
        }

        return strlen($digits) >= 10 ? $digits : null;
    }
}
