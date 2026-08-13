<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PublicConfigResource;

/**
 * Fase 4 · paso 4.0b — `GET /api/v1/config`: los ajustes de instalación que un cliente necesita para
 * pintar el cajón bien a la primera.
 *
 * **Público**, como el catálogo y la disponibilidad: el cajón se abre sin cuenta, y estos cuatro
 * valores hacen falta antes de que haya identidad. Al ser ruta pública, el `throttle:api` del grupo
 * sí la cuenta (en una ruta con `auth:` el 401 se lanza antes de llegar al limitador).
 *
 * Sin parámetros y sin cuerpo: describe la instalación, no una consulta.
 *
 * El controlador no decide nada — ni un `if`. Los cuatro valores salen de sus lectores defensivos
 * (`CatalogSettings`, `Turnstile`, `OrderCreator` y `RegistrationLink`), que ya existían y ya saben
 * qué hacer cuando el ajuste falta o es inválido.
 */
class ConfigController extends Controller
{
    public function __invoke(): PublicConfigResource
    {
        return new PublicConfigResource;
    }
}
