<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Promotion;
use Illuminate\Support\Collection;

/**
 * **El tablón de promociones de HOY** (`docs/specs/promociones.md` §4.3): las que una landing puede anunciar ahora, en
 * el orden en que se apilan. Una sola lectura para la API y para las páginas de la instancia (que la reciben por
 * `PageFacts`, con el mismo JSON).
 *
 * ⚠️ Vigente = activa y hoy DEL PARQUE dentro de sus fechas ({@see Promotion::scopeCurrent()}). ⚠️ Y su objetivo, vivo:
 * la oferta de una zona apagada o de un producto retirado anunciaría algo que no se vende, así que no sale —sin
 * borrarla: si el producto vuelve, vuelve con él—.
 */
final class PromotionBoard
{
    /** @return Collection<int, Promotion> */
    public function current(): Collection
    {
        return Promotion::query()
            ->current()
            ->with(['zone', 'product'])
            ->stacked()
            ->get()
            ->filter(fn (Promotion $p): bool => match ($p->target()) {
                Promotion::TARGET_ZONE => (bool) $p->zone?->is_active,
                Promotion::TARGET_PRODUCT => (bool) $p->product?->is_active,
                default => true,
            })
            ->values();
    }
}
