<?php

/*
 * Utilidades Base64/Base64URL para la firma Redsys (HMAC_SHA512_V2).
 * Vendorizado de la librería oficial PHP v2.0 (ver `Signature.php` para la nota de licencia).
 *
 * Diferencias intencionadas respecto al original:
 *   - Namespace propio (PSR-4) en lugar de clase global.
 *   - Las funciones `randomString` / `getCurrentUrl` del original NO se importan: no las
 *     usamos (el número de pedido lo genera nuestro contador atómico en BD, y la URL la
 *     construimos vía Laravel).
 */

namespace App\Support\Redsys\Vendor;

class Utils
{
    public static function base64_url_encode(string $input): string
    {
        return strtr(base64_encode($input), '+/', '-_');
    }

    public static function base64_url_decode(string $input): string|false
    {
        return base64_decode(strtr($input, '-_', '+/'), true);
    }

    /** Variante usada en la firma: además de `+/` → `-_`, elimina los `=` de relleno. */
    public static function base64_url_encode_safe(string $input): string
    {
        return str_replace('=', '', strtr(base64_encode($input), '+/', '-_'));
    }

    /** Repone el padding `=` antes de decodificar (la entrada puede venir sin `=`). */
    public static function base64_url_decode_safe(string $input): string|false
    {
        $padLen = (4 - strlen($input) % 4) % 4;
        $padded = str_pad($input, strlen($input) + $padLen, '=', STR_PAD_RIGHT);

        return base64_decode(strtr($padded, '-_', '+/'), true);
    }
}
