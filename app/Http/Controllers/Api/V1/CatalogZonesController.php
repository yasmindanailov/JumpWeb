<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Contracts\ProductCatalog;
use App\Http\Api\ApiCollection;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CatalogZoneResource;

/**
 * Fase 3 · paso 1b — las zonas operativas del catálogo.
 *
 * Endpoint PÚBLICO: el catálogo es lo primero que ve alguien que aún no tiene cuenta, y exigir
 * identidad para mirar el escaparate cerraría el flujo de compra de invitado que la web ya
 * soporta. Al ser público sí lo cuenta el limitador —en una ruta con `auth:` el 401 se lanza antes
 * de `throttle` (spec §10, punto 2)—, así que el suelo de `config/api.php` protege de verdad.
 *
 * Qué zona «opera» lo decide el dominio (`zones.is_active`), no este controlador.
 */
class CatalogZonesController extends Controller
{
    public function index(ProductCatalog $catalog): ApiCollection
    {
        return new ApiCollection($catalog->zones(), CatalogZoneResource::class);
    }
}
