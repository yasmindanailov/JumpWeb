<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Contracts\CartQuote;
use App\Domain\Booking\Contracts\CartQuoteAddon;
use App\Domain\Booking\Contracts\CartQuoteLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 3 · paso 4a — una cesta tarificada.
 *
 * Serializa el DTO `Booking\Contracts\CartQuote` sin recalcular nada. Tres decisiones que el
 * contrato deja explícitas:
 *
 *  - **`total_cents` y `online_amount_cents` son cosas distintas** y ninguna se deriva de la otra:
 *    el primero es lo que vale la reserva y el segundo lo que la pasarela cobra ahora. Con
 *    productos de señal (#225) difieren, y el nombre es `online_amount_cents` —no «pendiente»—
 *    por la misma razón que en `me/orders`: es el importe que se cobra online, y no baja a cero al
 *    pagarse (lección 9 del spec §10.bis).
 *  - **`subtotal_cents` de una línea es solo el principal.** Lo que suman sus complementos va en
 *    cada uno de ellos. Sumarlos en el principal impediría distinguir «3 entradas» de «3 entradas
 *    con comida», que es justo lo que un carrito tiene que enseñar.
 *  - **No se devuelven las respuestas del evento** (`event_data`) aunque se acepten en la petición.
 *    Son datos personales de un menor —nombre, a veces alergias (`RGPD` §3)— y este endpoint es
 *    PÚBLICO: devolverlas metería PII en una respuesta que el cliente ya tiene y que nadie más
 *    necesita. Las etiquetas para pintarlas están en `GET catalog/products/{id}`.
 *
 * @property-read CartQuote $resource
 */
class QuoteResource extends JsonResource
{
    /**
     * **`$wrap = null`** por el contrato de §4.3: «recurso en la raíz; listas bajo `data` + `meta`».
     * El presupuesto es un recurso —tiene dos importes propios y unas líneas dentro—, no una lista,
     * así que no pasa por `ApiCollection`. Sin esto, Laravel envolvería todo en `data` y el contrato
     * lo rechaza; lo cazó el test del endpoint en su primera ejecución.
     */
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'lines' => array_map(fn (CartQuoteLine $line): array => [
                // Posición de la línea en la cesta ENVIADA. Es lo que permite al cliente saber cuál
                // de las suyas cayó: una línea cuyo producto ya no se vende no se tarifica, y su
                // hueco en la secuencia es la única señal de que existió.
                'index' => $line->index,
                'product_id' => $line->productId,
                'product_name' => $line->name,
                'is_pack' => $line->isPack,
                'date' => $line->date,
                'time' => $line->time,
                'quantity' => $line->quantity,
                'unit_price_cents' => $line->unitPriceCents,
                'subtotal_cents' => $line->subtotalCents,
                'has_deposit' => $line->hasDeposit,
                'deposit_cents' => $line->depositCents,
                'gate_remainder_cents' => $line->gateRemainderCents,
                'addons' => array_map(fn (CartQuoteAddon $addon): array => [
                    'product_id' => $addon->productId,
                    'product_name' => $addon->name,
                    'quantity' => $addon->quantity,
                    'free_quantity' => $addon->freeQuantity,
                    'subtotal_cents' => $addon->subtotalCents,
                ], $line->addons),
            ], $this->resource->lines),
            'total_cents' => $this->resource->totalCents,
            'online_amount_cents' => $this->resource->onlineAmountCents,
        ];
    }
}
