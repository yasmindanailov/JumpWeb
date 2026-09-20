<?php

namespace App\Domain\Content\Models;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Platform\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * Sección editorial de la página /servicios + item del selector «Servicios» del nav (#256,
 * `docs/PLAN-SERVICIOS-DATA-DRIVEN.md`, modelo A). Es EDITORIAL (texto/imagen/orden/toggles); lo
 * COMERCIAL lo lee en vivo de los PRODUCTOS vinculados + su `Zone` (precio, tramos, mín/máx,
 * aforo) → cero drift. Sin productos = sección de solo-contacto («Pedir información»).
 *
 * Su mera existencia reclasifica los packs vinculados a la superficie «Servicios»: la sección
 * Cumpleaños los excluye con `TicketType::whereDoesntHave('landingServices')` (fuente única).
 * ▶ Desde `#588` un servicio vende VARIOS productos (una excursión son dos: 2 y 3 horas).
 */
class LandingService extends Model
{
    use HasTranslations;

    protected $guarded = [];

    /**
     * **URL pública de la foto del servicio, o `null` si no tiene** (F5 · T2b, `#660`).
     *
     * ⚠️ Tercera copia de la MISMA regla, y la última de la landing: la foto de una atracción
     * (`#657`), la de una zona (`#645`) y ésta son rutas relativas a `public/` escritas en el panel, y
     * las tres se resolvían con un `asset()` suelto dentro de una vista. Con la landing en otro repo,
     * quien la escriba tiene que poder preguntar en vez de adivinar — y el producto ofrece al lado la
     * respuesta equivocada: `TicketType::imageUrl()` antepone `uploads/` porque aquello sí es una subida.
     *
     * ⚠️ `null` con la ruta vacía es parte de la regla: la vista pinta la foto solo si hay, y un
     * `asset('')` daría la raíz del sitio con un roto dentro.
     */
    public function imageUrl(): ?string
    {
        $ruta = trim((string) ($this->image ?? ''));

        return $ruta === '' ? null : asset($ruta);
    }

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
     * Los packs que se venden desde esta sección (`#588`). Opcional: sin ninguno = solo-contacto.
     * ⚠️ Un producto está en UN servicio como mucho (índice único en la tabla de enlace): en dos se
     * duplicaría su tabla, y su existencia es la que lo saca de Cumpleaños.
     *
     * @return BelongsToMany<TicketType, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(TicketType::class, 'landing_service_products')
            ->withTimestamps()
            ->orderBy('ticket_types.position');
    }

    /**
     * Los productos vinculados que la web puede VENDER de verdad. Coherencia #226 (la landing solo
     * anuncia lo que la cesta puede vender): delega en `TicketType::isSellablePackForLanding()`.
     *
     * @return Collection<int, TicketType>
     */
    public function purchasableProducts(): Collection
    {
        return $this->products->filter(fn (TicketType $product): bool => $product->isSellablePackForLanding())->values();
    }

    /** ¿Tiene algo que vender? Si no, la sección degrada a «Pedir información». */
    public function isPurchasable(): bool
    {
        return $this->purchasableProducts()->isNotEmpty();
    }

    /** @param  Builder<LandingService>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param  Builder<LandingService>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position');
    }
}
