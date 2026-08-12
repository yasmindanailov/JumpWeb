<?php

namespace App\Support;

use App\Domain\Booking\Contracts\ComplementPlacement;
use App\Domain\Booking\Contracts\PublishableCatalog;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;

/**
 * Read-model de BOOKING: qué del catálogo es publicable fuera del módulo.
 *
 * Implementa `PublishableCatalog`. La regla de comprabilidad (#226) es la consulta EN LOTE
 * que vivía en `App\Support\LandingComplementResolver`; la variante unitaria de
 * `Attraction::complementIsPurchasable()` ahora pasa por aquí con un solo par, de modo que
 * las dos formas de preguntar comparten UNA sola definición y no pueden divergir.
 *
 * Vive en `app/Support` hasta el paso 6, cuando Booking mude a `app/Domain/Booking/Services`.
 */
class PublishableCatalogReader implements PublishableCatalog
{
    public function isComplementPurchasable(ComplementPlacement $placement): bool
    {
        return $this->purchasableComplements([$placement]) !== [];
    }

    /**
     * UNA sola query: pares (zona, complemento) en los que el complemento es comprable = addon
     * vendible+activo, CON precio, enganchado como complemento DE PAGO (`is_included=false`) a
     * una ENTRADA vendible+activa de una zona ACTIVA. El precio y el `is_included` cierran la
     * coherencia #226: la landing solo anuncia precio/CTA de lo que la cesta puede COBRAR (un
     * addon sin precio mostraría «0,00 €»; uno solo incluido es gratis, no se vende aparte).
     *
     * @param  list<ComplementPlacement>  $placements
     * @return list<ComplementPlacement>
     */
    public function purchasableComplements(array $placements): array
    {
        if ($placements === []) {
            return [];
        }

        $addonIds = array_values(array_unique(array_map(
            fn (ComplementPlacement $p): int => $p->complementId, $placements
        )));
        $zoneIds = array_values(array_unique(array_map(
            fn (ComplementPlacement $p): int => $p->zoneId, $placements
        )));

        $pairs = DB::table('product_addons as pa')
            ->join('ticket_types as addon', 'pa.addon_id', '=', 'addon.id')
            ->join('ticket_types as entry', 'pa.product_id', '=', 'entry.id')
            ->join('zones as z', 'entry.zone_id', '=', 'z.id')
            ->whereIn('pa.addon_id', $addonIds)
            ->whereIn('entry.zone_id', $zoneIds)
            ->where('pa.is_included', false)
            ->where('addon.type', TicketType::TYPE_ADDON)
            ->where('addon.is_sellable', true)
            ->where('addon.is_active', true)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('prices')
                ->whereColumn('prices.priceable_id', 'addon.id')
                ->where('prices.priceable_type', (new TicketType)->getMorphClass()))
            ->where('entry.type', TicketType::TYPE_ENTRY)
            ->where('entry.is_sellable', true)
            ->where('entry.is_active', true)
            ->where('z.is_active', true)
            ->distinct()
            ->get(['entry.zone_id as zone_id', 'pa.addon_id as addon_id']);

        // El producto cartesiano de la query (todas las zonas × todos los addons pedidos) puede
        // contener pares que NADIE preguntó: devolvemos solo los que estaban en la entrada.
        $purchasable = [];
        foreach ($pairs as $pair) {
            $purchasable[$pair->zone_id.':'.$pair->addon_id] = true;
        }

        return array_values(array_filter(
            $placements,
            fn (ComplementPlacement $p): bool => isset($purchasable[$p->zoneId.':'.$p->complementId]),
        ));
    }
}
