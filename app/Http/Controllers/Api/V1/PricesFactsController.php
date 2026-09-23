<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\GroupRateTables;
use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocale;
use App\Http\Resources\Api\V1\PricesFactsResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * `GET /api/v1/prices?lang=` — los precios por tarifa (F5 · T5 del menú).
 *
 * ⚠️ **Solo lo que se vende hoy**: productos activos, de zonas activas, y tarifas activas. Un producto
 * apagado en el panel no tiene precio público — y publicarlo sería anunciar algo que el embudo rechaza.
 *
 * ⚠️ Se cargan `prices.rateType` y `priceTiers` de golpe: sin eso, una instalación con veinte productos
 * hace una consulta por producto y por tarifa para pintar una tabla que cabe en una pantalla.
 */
class PricesFactsController extends Controller
{
    public function __invoke(Request $request, GroupRateTables $escaleras): PricesFactsResource
    {
        $datos = $request->validate(['lang' => ['required', 'string', Rule::in(SetLocale::SUPPORTED)]]);

        app()->setLocale($datos['lang']);

        $productos = TicketType::query()
            ->with(['prices.rateType', 'priceTiers', 'zone'])
            ->where('is_active', true)
            ->whereHas('zone', fn ($q) => $q->where('is_active', true))
            ->orderBy('position')
            ->get();

        return new PricesFactsResource(
            $productos,
            RateType::query()->where('is_active', true)->orderBy('id')->get()->collect(),
            $escaleras,
        );
    }
}
