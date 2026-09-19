<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SiteFactsResource;

/**
 * `GET /api/v1/site` — el primer recurso del MENÚ DE HECHOS (F5 · T1).
 *
 * No depende de quién mira ni de la sesión: es la identidad de la instalación. Por eso se cachea en público
 * con `ETag` (`PERF-02`), igual que `/sidebar/boot`, y por eso no lleva idioma en la URL — ninguno de sus
 * campos se traduce: un NIF, un teléfono y una dirección son los mismos en las tres lenguas.
 */
class SiteFactsController extends Controller
{
    public function __invoke(): SiteFactsResource
    {
        return new SiteFactsResource;
    }
}
