<?php

/*
 * NOTA SOBRE LA LICENCIA — librería oficial vendorizada
 *
 * Este fichero contiene la versión simplificada de la librería oficial de firma de Redsys
 * (HMAC_SHA512_V2 + AES-128-CBC), publicada por Redsys Servicios de Procesamiento, S.L.
 * (CIF B85955367). Se incluye aquí *vendorizada* (decisión #104, ver `docs/DECISIONES.md`),
 * con cambios mínimos y deliberados respecto al original:
 *   - Espacio de nombres `App\Support\Redsys\Vendor` (en lugar de global) para encajar con
 *     PSR-4 y evitar colisiones.
 *   - `Utils` se incluye por el namespace, no por `include`.
 *
 * Origen y versión: librería "Firma HMAC SHA-512 V2 PHP" (v2.0), descargada del área de
 * descargas oficial: https://pagosonline.redsys.es/desarrolladores-inicio/integrate-con-nosotros/area-de-descargas-y-documentacion/
 *
 * Aviso legal completo: https://www.redsys.es (Condiciones de uso del software).
 * El uso está limitado a la integración con la pasarela Redsys.
 */

namespace App\Support\Redsys\Vendor;

class Signature
{
    /** Cifrado AES-128-CBC con IV cero (derivación de la clave por operación). */
    public static function encrypt_AES(string $data, string $key): string
    {
        $fixedKey = str_pad(substr($key, 0, 16), 16, '0');

        return base64_encode(openssl_encrypt(
            $data,
            'aes-128-cbc',
            $fixedKey,
            OPENSSL_RAW_DATA,
            "\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0"
        ));
    }

    /** HMAC-SHA512 (raw, sin encoding). */
    public static function mac512(string $data, string $key): string
    {
        return hash_hmac('sha512', $data, $key, true);
    }

    /** Firma completa: deriva clave por pedido y aplica HMAC-SHA512 al payload Base64URL. */
    public static function createMerchantSignature(string $key, string $data, string $diversifyingFactor): string
    {
        $key = self::encrypt_AES($diversifyingFactor, $key);
        $res = self::mac512($data, $key);

        return Utils::base64_url_encode_safe($res);
    }

    /** Comparación timing-safe; lanza si no coinciden (se usa en la recepción). */
    public static function checkSignatures(string $sig1, string $sig2): void
    {
        if (! hash_equals($sig1, $sig2)) {
            throw new \RuntimeException('Integrity failure. The received signature does not match the calculated one.');
        }
    }
}
