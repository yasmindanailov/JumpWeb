<?php

namespace App\Models;

use App\Domain\Booking\Contracts\ComplementPlacement;
use App\Domain\Booking\Contracts\PublishableCatalog;
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
     *
     * La REGLA es de Booking y vive en su contrato (`PublishableCatalog`, Fase 2 paso 1);
     * antes estaba duplicada aquí y en el resolver de la landing, con dos consultas que
     * podían divergir. Aquí solo queda la guarda de los IDs.
     */
    public function complementIsPurchasable(): bool
    {
        if (! $this->ticket_type_id || ! $this->zone_id) {
            return false;
        }

        return app(PublishableCatalog::class)->isComplementPurchasable(
            new ComplementPlacement((int) $this->ticket_type_id, (int) $this->zone_id),
        );
    }

    /** Precio de referencia (céntimos) del complemento vinculado, para la card de la landing. */
    public function complementPriceCents(): int
    {
        return (int) ($this->ticketType?->displayPriceCents() ?? 0);
    }
}
