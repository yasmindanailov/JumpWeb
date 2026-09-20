<?php

namespace App\Domain\Content\Models;

use App\Domain\Booking\Contracts\ComplementPlacement;
use App\Domain\Booking\Contracts\PublishableCatalog;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Platform\Concerns\HasTranslations;
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
     * **URL pública de la foto de la atracción, o `null` si no tiene** (F5 · T2b, `#657`).
     *
     * ⚠️⚠️ **La regla estaba escrita en las VISTAS, y las vistas se van** (`paquete-de-instancia.md`
     * §4.7): `asset($ride->image)` aparecía en `/atracciones` y dos veces en el mosaico de la portada,
     * sin un sitio donde consultarla. El día que una landing es de otro repo, quien la escriba tiene
     * que adivinar cómo se resuelve esa ruta — y el propio producto ofrece la respuesta equivocada al
     * lado: `TicketType::imageUrl()` antepone `uploads/` porque **aquello sí es una subida**.
     *
     * Aquí no: la foto de una atracción es una **ruta relativa a `public/`** escrita en el panel
     * (`images/attractions/jump_saltos_libres.webp`), igual que la de la zona — misma herencia, misma
     * resolución, y por eso este método es letra por letra {@see Zone::imageUrl()}.
     *
     * ⚠️ Devolver `null` con la ruta vacía es parte de la regla, no un detalle: la vista pinta la foto
     * solo si hay, y «sin foto no se reserva hueco» es decisión del canvas. Un `asset('')` daría la
     * raíz del sitio y pintaría un cuadrado roto.
     */
    public function imageUrl(): ?string
    {
        $ruta = trim((string) ($this->image ?? ''));

        return $ruta === '' ? null : asset($ruta);
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
     * `App\Domain\Content\Services\LandingComplementResolver` (batch, sin N+1).
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
