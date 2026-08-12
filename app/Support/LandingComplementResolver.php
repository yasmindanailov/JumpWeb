<?php

namespace App\Support;

use App\Domain\Booking\Contracts\ComplementPlacement;
use App\Domain\Booking\Contracts\PublishableCatalog;
use App\Models\Attraction;
use Illuminate\Support\Collection;

/**
 * Comprabilidad (solo lectura) de los COMPLEMENTOS vinculados a atracciones, para la LANDING (#228).
 *
 * Una atracción puede vincularse a un complemento (addon). En la landing solo mostramos precio + CTA
 * si ese complemento es REALMENTE comprable en la zona de la atracción (coherencia #226). Si no, la
 * card degrada a informativa.
 *
 * Módulo **Content**. La REGLA de comprabilidad es de Booking y se pide por contrato
 * (`PublishableCatalog::purchasableComplements`, Fase 2 paso 1) en BATCH: UNA query para todas las
 * atracciones de la página, evitando el N+1 de `Attraction::complementIsPurchasable()` (que
 * comprueba una sola y hoy pasa por el MISMO contrato). El precio se lee de
 * `TicketType::displayPriceCents()` (tarifa normal/mínima) por relación Eloquent, la costura de BD
 * que el spec deja exenta.
 */
class LandingComplementResolver
{
    /** @var array<string,true> conjunto de claves "zoneId:addonId" comprables */
    private array $purchasable;

    /**
     * @param  iterable<Attraction>  $attractions
     * @param  ?PublishableCatalog  $catalog  inyectable en tests; por defecto, el del contenedor
     *                                        (el llamante real lo construye con `new`).
     */
    public function __construct(iterable $attractions, ?PublishableCatalog $catalog = null)
    {
        $this->purchasable = $this->compute(collect($attractions), $catalog ?? app(PublishableCatalog::class));
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
    private function compute(Collection $attractions, PublishableCatalog $catalog): array
    {
        $placements = $attractions
            ->filter(fn (Attraction $a): bool => (bool) $a->ticket_type_id && (bool) $a->zone_id)
            ->map(fn (Attraction $a): ComplementPlacement => new ComplementPlacement(
                complementId: (int) $a->ticket_type_id,
                zoneId: (int) $a->zone_id,
            ))
            ->values()
            ->all();

        $set = [];
        foreach ($catalog->purchasableComplements($placements) as $placement) {
            $set[$placement->zoneId.':'.$placement->complementId] = true;
        }

        return $set;
    }
}
