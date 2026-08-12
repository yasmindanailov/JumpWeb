<?php

namespace App\Support;

/**
 * Normalización ÚNICA de teléfonos del proyecto (#264-audit). Convierte a SOLO dígitos: quita
 * espacios, guiones, paréntesis, puntos y el prefijo `+`. Ej.: `+34 600-11.22 33` → `34600112233`.
 * Vacío/`null` → `null`.
 *
 * Antes había dos convenciones divergentes (el alta de panel conservaba el `+`, la puerta lo quitaba),
 * lo que podía dar falsos negativos al cruzar subsistemas. Este helper unifica la convención
 * (solo-dígitos) que ya usaban la puerta (`ValidarRegistro`) y su `whereRaw` SQL espejo; la usa también
 * la deduplicación del alta manual (`CustomerRegistrar`). NOTA: «con prefijo de país» vs «sin él»
 * (p. ej. `34600…` vs `600…`) siguen siendo distintos a propósito — no asumimos país.
 */
class PhoneNormalizer
{
    public static function digits(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return $digits === '' ? null : $digits;
    }
}
