<?php

namespace App\Domain\Identity\Services;

/**
 * Fase 6 · subsistema A — la FORMA del carné QR (`docs/specs/identidad-qr-puerta.md` §4.3, §8.2;
 * `[DECIDIDO owner]` `#208`: 20 caracteres).
 *
 * `JW` + 17 aleatorios + 1 de control, todo en **Crockford Base32** (`0-9 A-Z` sin `I L O U`):
 *  - entra en el modo ALFANUMÉRICO del QR (el más compacto: 20 caracteres caben en versión 2, 25×25,
 *    con corrección H — medido con la librería vendorizada);
 *  - `A-Z` y `0-9` son las teclas que no cambian entre distribuciones: el lector del recinto es un
 *    *keyboard wedge* y con el lector en US y el equipo en ES un guion o un subrayado salen mal;
 *  - sin ambigüedad visual si alguien lo dicta: `I` y `L` se leen como `1`, `O` como `0`
 *    ({@see normalize()}), que es lo que Crockford permite;
 *  - **entropía `32¹⁷ ≈ 2⁸⁵`** (§8.2: `2⁵⁰` era enumerable ante un volcado con GPU; esto no);
 *  - el carácter de CONTROL hace que un escaneo defectuoso falle en el navegador, no contra la base
 *    de datos: suma ponderada por posición **módulo 31** (primo), en el mismo alfabeto. ⚠️ Con
 *    módulo 32 los pesos pares compartían factor con el módulo y una sustitución en una posición par
 *    podía pasar (medido: un test aleatorio cayó 1 de ~8 veces). Con 31 y pesos 1..19 se caza TODA
 *    sustitución simple y TODA transposición adyacente, salvo el par `0↔Z` (valores 0 y 31, que son
 *    congruentes): es la única pareja que este control no distingue, y está escrito aquí para que
 *    nadie lo descubra midiendo.
 *
 * El carné se guarda como sha256 del token (sin sal: tiene que servir para BUSCAR, §4.5) y cifrado
 * para repintarlo. Este objeto no toca la base de datos.
 */
final class CardToken
{
    public const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public const PREFIX = 'JW';

    public const RANDOM_LENGTH = 17;

    public const LENGTH = 20;

    public static function generate(): string
    {
        $body = self::PREFIX;
        for ($i = 0; $i < self::RANDOM_LENGTH; $i++) {
            $body .= self::ALPHABET[random_int(0, 31)];
        }

        return $body.self::checksum($body);
    }

    /**
     * Lo que un humano o un lector pudo teclear, llevado a la forma canónica: mayúsculas, sin espacios
     * ni guiones, y con las tres confusiones que Crockford admite resueltas (`I`/`L` → `1`, `O` → `0`).
     */
    public static function normalize(string $raw): string
    {
        $upper = strtoupper(preg_replace('/[\s\-_]+/', '', trim($raw)) ?? '');

        return strtr($upper, ['I' => '1', 'L' => '1', 'O' => '0']);
    }

    /** ¿Tiene la longitud y el prefijo de un carné? Lo justo para DETECTAR que es un carné (no que sea válido). */
    public static function looksLike(string $normalized): bool
    {
        return strlen($normalized) === self::LENGTH && str_starts_with($normalized, self::PREFIX);
    }

    /** Longitud, prefijo, alfabeto Y carácter de control: lo que se comprueba antes de mirar la base de datos. */
    public static function isWellFormed(string $normalized): bool
    {
        if (! self::looksLike($normalized)) {
            return false;
        }
        if (! preg_match('/^['.self::ALPHABET.']+$/', $normalized)) {
            return false;
        }

        return $normalized[self::LENGTH - 1] === self::checksum(substr($normalized, 0, self::LENGTH - 1));
    }

    /** sha256 en hexadecimal: la columna por la que se busca. */
    public static function hash(string $normalized): string
    {
        return hash('sha256', $normalized);
    }

    public const CHECK_MODULUS = 31;

    /** Suma ponderada por posición (1..19) de los valores del alfabeto, módulo 31, como carácter del alfabeto. */
    public static function checksum(string $body): string
    {
        $sum = 0;
        foreach (str_split($body) as $i => $char) {
            $value = strpos(self::ALPHABET, $char);
            if ($value === false) {
                return '?';
            }
            $sum += ($i + 1) * $value;
        }

        return self::ALPHABET[$sum % self::CHECK_MODULUS];
    }
}
