<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Contracts\AddonChoiceGroup;
use App\Domain\Booking\Contracts\CartQuote;
use App\Domain\Booking\Contracts\CartQuoteAddon;
use App\Domain\Booking\Contracts\CartQuoteLine;
use App\Domain\Booking\Contracts\ResolvedAddon;
use App\Domain\Booking\Contracts\ResolvedAddons;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 4 · paso 4.0b·5 — los complementos resueltos de una línea, con su dinero.
 *
 * **`selection` es lo que hay que guardar en la cesta**, y por eso viaja aunque el cliente ya vea
 * los complementos uno a uno: traducir lo pintado en lo que se guarda exige saber qué miembro de
 * cada grupo cuenta, inyectar los obligatorios que nadie marcó y podar los dependientes huérfanos.
 * Publicarla evita que cada cliente escriba su propia versión de esa regla.
 *
 * @property-read ResolvedAddons $resource
 */
class ResolvedAddonsResource extends JsonResource
{
    /** El recurso va en la raíz (spec §4.3). */
    public static $wrap = null;

    /**
     * El PRESUPUESTO de la línea, cuando la petición trajo día y hora.
     *
     * Se INYECTA desde el controlador en vez de calcularse aquí: el dinero tiene un solo dueño
     * (`CartPricing`) y un serializador que lo pidiera por su cuenta sería el sitio más fácil desde
     * el que empezar a componerlo a mano.
     *
     * ⚠️ **Se guarda el presupuesto ENTERO y no solo su línea** (Fase 4 · paso 4.3·2), y esa es la
     * diferencia que hace que `total_cents` no sea una suma: el agregado lo pone el dominio. El
     * controlador ya construía este `CartQuote` completo y **tiraba sus totales**, así que el cliente
     * no tenía de dónde sacar el importe del pie del paso 3 y habría acabado sumando `subtotal_cents`
     * con `addons_total_cents` — que salen de dos recorridos DISTINTOS y pueden divergir.
     */
    private ?CartQuote $quote = null;

    public function withQuote(?CartQuote $quote): self
    {
        $this->quote = $quote;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'groups' => array_map(static fn (AddonChoiceGroup $group): array => [
                'key' => $group->key,
                'label' => $group->label,
                'options' => array_map(self::addon(...), $group->options),
            ], $this->resource->groups),
            'singles' => array_map(self::addon(...), $this->resource->singles),
            'addons_total_cents' => $this->resource->totalCents,
            // La selección resuelta, ya en la forma de los `addons` de una línea de cesta: se guarda
            // tal cual y se manda tal cual a `orders/quote` y a `POST orders`.
            'selection' => array_map(static fn (array $addon): array => [
                'product_id' => $addon['ticket_type_id'],
                'quantity' => $addon['qty'],
            ], $this->resource->selection),
            // El pie de la línea. `null` cuando la petición no llevó día y hora: sin ellos no hay
            // precio del producto base, y devolver un importe calculado sobre una fecha inventada
            // sería peor que no devolver ninguno.
            'line' => $this->line() === null ? null : [
                'subtotal_cents' => $this->line()->subtotalCents,
                'unit_price_cents' => $this->line()->unitPriceCents,
                // Lo que vale la línea ENTERA: principal más complementos cobrados. Lo publica el
                // presupuesto, no una suma de este serializador — `PAY-12` pide una sola fuente de
                // CÁLCULO, y sumar aquí sería la segunda teniendo la primera ya hecha.
                'total_cents' => $this->quote->totalCents,
                'has_deposit' => $this->line()->hasDeposit,
                'deposit_cents' => $this->line()->depositCents,
                'gate_remainder_cents' => $this->line()->gateRemainderCents,
                'addons' => array_map(static fn (CartQuoteAddon $addon): array => [
                    'product_id' => $addon->productId,
                    'product_name' => $addon->name,
                    'quantity' => $addon->quantity,
                    'free_quantity' => $addon->freeQuantity,
                    'subtotal_cents' => $addon->subtotalCents,
                ], $this->line()->addons),
            ],
        ];
    }

    /** La línea del presupuesto, o `null` si no hubo día y hora (y por tanto no hay pie). */
    private function line(): ?CartQuoteLine
    {
        return $this->quote?->lines[0] ?? null;
    }

    /** @return array<string, mixed> */
    private static function addon(ResolvedAddon $addon): array
    {
        return [
            'product_id' => $addon->productId,
            'product_name' => $addon->name,
            'price_cents' => $addon->priceCents,
            // La línea de precio ya compuesta y traducida. Viaja hecha porque mezcla textos de
            // `lang/` con importes formateados y hoy la SPA no tiene canal de i18n propio (§4.5);
            // es el mismo criterio que `booking/status` y que las etiquetas del post-form.
            'note' => $addon->note,
            'is_included' => $addon->isIncluded,
            'is_mandatory' => $addon->isMandatory,
            'per_guest' => $addon->perGuest,
            'allow_extra' => $addon->allowExtra,
            'badge' => $addon->badge,
            'features' => $addon->features,
            'gifts' => $addon->gifts,
            // ⚠️ `selected` NO se deduce de `quantity`: en un grupo lo que selecciona es ser el
            // elegido, y un dependiente huérfano queda fuera aunque el cliente lo marcara.
            'selected' => $addon->selected,
            'available' => $addon->available,
            'requires_name' => $addon->requiresName,
            'quantity' => $addon->quantity,
            'free_quantity' => $addon->freeQuantity,
            'charged_cents' => $addon->chargedCents,
            'min_quantity' => $addon->minQuantity,
            'max_quantity' => $addon->maxQuantity,
            // Qué se puede hacer con esta fila. Son decisiones DERIVADAS de reglas de dominio
            // —dependencias, por-invitado, grupo, incluido sin extras, tope—: publicarlas es lo que
            // evita que el cliente las recomponga, que es la tercera copia de la misma cadena.
            'can_toggle' => $addon->canToggle,
            'can_increase' => $addon->canIncrease,
            'can_decrease' => $addon->canDecrease,
        ];
    }
}
