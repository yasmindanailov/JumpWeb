<?php

namespace App\Domain\Content\Models;

use App\Domain\Platform\Concerns\HasTranslations;
use App\Models\TicketType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sección editorial de la página /servicios + item del selector «Servicios» del nav (#256,
 * `docs/PLAN-SERVICIOS-DATA-DRIVEN.md`, modelo A). Es EDITORIAL (texto/imagen/orden/toggles); lo
 * COMERCIAL lo lee en vivo del `ticketType` vinculado + su `Zone` (precio, complementos, mín/máx,
 * aforo) → cero drift. `ticket_type_id` NULL = sección de solo-contacto («Pedir información»).
 *
 * Su mera existencia reclasifica el pack vinculado a la superficie «Servicios»: la sección
 * Cumpleaños lo excluye con `TicketType::whereDoesntHave('landingService')` (fuente única).
 */
class LandingService extends Model
{
    use HasTranslations;

    protected $guarded = [];

    protected $casts = [
        'accent_word' => 'array',
        'title' => 'array',
        'body' => 'array',
        'zone_label' => 'array',
        'specs' => 'array',
        'price_table' => 'array',
        'nav_subtitle' => 'array',
        'is_active' => 'boolean',
        'show_in_nav' => 'boolean',
    ];

    /**
     * Pack comprable de esta sección (#228 patrón espejo de `Attraction::ticketType`). Opcional:
     * `null` = sección de solo-contacto. Si está y es comprable (`isPurchasable`), la card muestra
     * precio + complementos + CTA «Reservar»; si no, CTA «Pedir información».
     *
     * @return BelongsTo<TicketType, $this>
     */
    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    /**
     * ¿El pack vinculado es realmente COMPRABLE? Coherencia #226 (la landing solo anuncia lo que la
     * cesta puede vender): delega en la fuente única `TicketType::isSellablePackForLanding()` (pack
     * vendible + activo, con precio y en zona operativa). Si no, la card degrada a solo-contacto.
     */
    public function isPurchasable(): bool
    {
        return $this->ticketType?->isSellablePackForLanding() ?? false;
    }

    /** @param  Builder<LandingService>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<LandingService>  $query */
    public function scopeInNav(Builder $query): void
    {
        $query->where('show_in_nav', true);
    }

    /** @param  Builder<LandingService>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position');
    }
}
