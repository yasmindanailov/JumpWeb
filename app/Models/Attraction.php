<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attraction extends Model
{
    use HasTranslations;

    protected $guarded = [];

    protected $casts = [
        'name' => 'array',
        'description' => 'array',
        'age' => 'array',
        'badge' => 'array',
        'is_active' => 'boolean',
        'is_special' => 'boolean',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Complemento (addon) vendible vinculado a esta atracción (#228). Opcional:
     * `null` = atracción solo informativa. Si está presente y es comprable en la
     * zona (ver `complementIsPurchasable`), la landing muestra precio + CTA.
     *
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /**
     * ¿El complemento vinculado es realmente COMPRABLE en la zona de esta atracción?
     * Coherencia #226 (la landing solo anuncia lo que la cesta puede vender): el addon
     * debe ser activo+vendible Y estar enganchado (pivote `product_addons`) a ≥1 entrada
     * (`TYPE_ENTRY`) vendible de esta zona, con la zona operativa. Si no, la card degrada
     * a informativa (sin precio/CTA).
     *
     * Comprobación de UNA atracción (panel/aviso). Para la LISTA de la landing se usa
     * `App\Support\LandingComplementResolver` (batch, sin N+1).
     */
    public function complementIsPurchasable(): bool
    {
        $addon = $this->ticketType;

        // Debe ser un complemento vendible Y con precio (sin precio mostraría «0,00 €»).
        if (! $addon || ! $addon->isAddon() || ! $addon->is_active || ! $addon->is_sellable
            || ! $addon->prices()->exists()) {
            return false;
        }

        // Enganchado como complemento DE PAGO (`is_included=false`) a ≥1 entrada vendible de la zona
        // operativa: un addon solo incluido es gratis y no se vende aparte (coherencia #226).
        return $addon->addonOfProducts()
            ->where('product_addons.is_included', false)
            ->where('ticket_types.type', TicketType::TYPE_ENTRY)
            ->where('ticket_types.zone_id', $this->zone_id)
            ->where('ticket_types.is_sellable', true)
            ->where('ticket_types.is_active', true)
            ->whereHas('zone', fn ($z) => $z->where('is_active', true))
            ->exists();
    }

    /** Precio de referencia (céntimos) del complemento vinculado, para la card de la landing. */
    public function complementPriceCents(): int
    {
        return (int) ($this->ticketType?->displayPriceCents() ?? 0);
    }
}
