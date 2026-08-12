<?php

namespace App\Support;

use App\Models\Attraction;
use App\Models\TicketType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Comprabilidad (solo lectura) de los COMPLEMENTOS vinculados a atracciones, para la LANDING (#228).
 *
 * Una atracción puede vincularse a un complemento (addon). En la landing solo mostramos precio + CTA
 * si ese complemento es REALMENTE comprable en la zona de la atracción (coherencia #226): addon
 * vendible+activo, enganchado (pivote `product_addons`) a ≥1 entrada (`TYPE_ENTRY`) vendible de una
 * zona operativa. Si no, la card degrada a informativa.
 *
 * Este resolver hace el cálculo en BATCH (UNA query para todas las atracciones de la página),
 * evitando el N+1 de `Attraction::complementIsPurchasable()` (que comprueba una sola). El precio se
 * lee de `TicketType::displayPriceCents()` (tarifa normal/mínima), igual que el resto de la landing.
 */
class LandingComplementResolver
{
    /** @var array<string,true> conjunto de claves "zoneId:addonId" comprables */
    private array $purchasable;

    /** @param  iterable<Attraction>  $attractions */
    public function __construct(iterable $attractions)
    {
        $this->purchasable = $this->compute(collect($attractions));
    }

    /** ¿El complemento de esta atracción es comprable en su zona? */
    public function isPurchasable(Attraction $attraction): bool
    {
        if (! $attraction->ticket_type_id || ! $attraction->zone_id) {
            return false;
        }

        return isset($this->purchasable[$attraction->zone_id.':'.$attraction->ticket_type_id]);
    }

    /** Precio de referencia (céntimos) del complemento vinculado (0 si no hay). */
    public function priceCents(Attraction $attraction): int
    {
        return (int) ($attraction->ticketType?->displayPriceCents() ?? 0);
    }

    /**
     * @param  Collection<int,Attraction>  $attractions
     * @return array<string,true>
     */
    private function compute(Collection $attractions): array
    {
        $linked = $attractions->filter(
            fn (Attraction $a): bool => (bool) $a->ticket_type_id && (bool) $a->zone_id
        );

        if ($linked->isEmpty()) {
            return [];
        }

        $addonIds = $linked->pluck('ticket_type_id')->unique()->values()->all();
        $zoneIds = $linked->pluck('zone_id')->unique()->values()->all();

        // UNA sola query: pares (zona, addon) en los que el complemento es comprable = addon
        // vendible+activo, CON precio, enganchado como complemento DE PAGO (`is_included=false`) a
        // una ENTRADA vendible+activa de una zona ACTIVA. El precio y el `is_included` cierran la
        // coherencia #226: la landing solo anuncia precio/CTA de lo que la cesta puede COBRAR (un
        // addon sin precio mostraría «0,00 €»; uno solo incluido es gratis, no se vende aparte).
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

        $set = [];
        foreach ($pairs as $pair) {
            $set[$pair->zone_id.':'.$pair->addon_id] = true;
        }

        return $set;
    }
}
