<?php

namespace App\Http\Api;

use Illuminate\Http\Request;

/**
 * Fase 3 · paso 0 — **fuente única del prefijo de la API** (spec §4.1: la versión va en la URL).
 *
 * Lo consultan tres sitios que deben coincidir SIEMPRE, y que sin esto coincidirían por copia:
 * el registro de rutas (`bootstrap/app.php` → `withRouting(apiPrefix:)`), el sobre de error
 * (`ApiExceptionRenderer`, que solo debe cambiar los errores de la API y jamás los de la web) y el
 * kill-switch de mantenimiento (`EnsureSiteAvailable`, que renderiza JSON aquí y HTML fuera).
 *
 * `v2` (si algún día existe) NO reutiliza esta clase: nace con la suya, porque el sentido de poner
 * la versión en la URL es que las dos superficies puedan divergir.
 */
final class ApiSurface
{
    /** Prefijo de ruta, sin barra inicial — la forma que espera `Request::is()`. */
    public const PREFIX = 'api/v1';

    /** ¿Esta petición va dirigida a la API v1? Se responde por PATH, no por `Accept`. */
    public static function handles(Request $request): bool
    {
        return $request->is(self::PREFIX, self::PREFIX.'/*');
    }
}
