<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Services\PromotionBoard;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Http\Resources\Api\V1\PromotionsFactsResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * `GET /api/v1/promotions?lang=` — las PROMOCIONES vigentes hoy (`docs/specs/promociones.md` §4.3, `#770`).
 *
 * ⚠️ El idioma es parte de la PETICIÓN, como en `/rules`: la respuesta se cachea en público y tiene que ser función de
 * su URL. Lo que vale hoy lo decide el dominio ({@see PromotionBoard}); aquí solo se traduce HTTP.
 */
class PromotionsFactsController extends Controller
{
    public function __invoke(Request $request, PromotionBoard $tablon): PromotionsFactsResource
    {
        $datos = $request->validate(['lang' => ['required', 'string', Rule::in(SetLocale::SUPPORTED)]]);

        app()->setLocale($datos['lang']);

        return new PromotionsFactsResource($tablon->current());
    }
}
