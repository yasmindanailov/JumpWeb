<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * **Una RESERVA como tarjeta**, para las dos pantallas de «Mis reservas»
 * (`docs/specs/mis-reservas-por-reserva.md` §4.2).
 *
 * Es `OrderItemResource` **más el contexto mínimo de su pedido**: de qué pedido viene, en qué estado
 * está y si se le puede reintentar el cobro. Nada más.
 *
 * ⚠️⚠️ **El LEDGER no viaja aquí, y es la decisión que hace barato este endpoint.** El desglose
 * financiero (subtotal, señal, a cobrar en puerta, devuelto, total final) es del PEDIDO, así que
 * meterlo en la tarjeta lo repetiría tantas veces como reservas tenga ese pedido — el caso real
 * `DEMO-LEDGER` tiene tres. Se pide **al desplegar «Ver pedido»** con `GET /orders/{code}`, que ya
 * existe, ya está acotado por `user_id` y ya devuelve el ledger entero compuesto por
 * `OrderResource`.
 * ▶ Es el mismo patrón —y por el mismo motivo— que las respuestas del pack con
 * `GET /orders/{code}/event-data`: lo que solo se mira a veces no viaja en cada página.
 * ▶ Y tiene un efecto que vale más que el ahorro: **no nace una segunda superficie de dinero**. El
 * cliente reutiliza `account/orders.js::financialsOf()` tal cual sobre la respuesta de siempre.
 *
 * ⚠️ **Aquí NO hay un `is_terminated`, y se descartó a propósito.** Sería o bien un eco del ámbito
 * que el cliente acaba de pedir —un campo que no puede decir nada nuevo— o bien una **segunda
 * definición** del predicado que reparte las dos pantallas, calculada en PHP mientras la de verdad
 * vive en SQL (`CustomerReservationsReader::terminated()`). Lo segundo es exactamente la divergencia
 * que §3.4 de la spec existe para impedir. La atenuación es propiedad de la PANTALLA —el historial
 * atenúa lo que pinta— y el distintivo por tarjeta —«cancelada» / «disfrutada»— ya se compone con
 * `cancelled` y `status`, que sí viajan y sí son de la reserva.
 *
 * @property-read OrderItem $resource
 */
class ReservationCardResource extends JsonResource
{
    /** El recurso va en la raíz cuando se pide suelto, como sus hermanos (spec §4.3). */
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $item = $this->resource;
        $order = $item->order;

        // Sin el pedido no hay ni desglose de la reserva ni referencia que enseñar. El único llamante
        // es `MeReservationsController`, que lo trae eager-loaded desde `pageFor()`; si esto salta es
        // que alguien ha añadido un segundo llamante sin cargarlo.
        assert($order !== null, self::class.' necesita el pedido de la reserva cargado');

        return [
            // ⚠️ **Anidada y no aplanada sobre la tarjeta**, y lo decidió el CONTRATO: el esquema
            // `OrderItem` declara `additionalProperties: false`, así que un `allOf` que le añadiera
            // `order` al lado sería inválido —esa rama rechazaría el campo extra— y aplanarlo a mano
            // obligaría a copiar sus veinte propiedades en un esquema nuevo. Anidando, el contrato
            // la referencia con un `$ref` y no hay segunda copia que mantener.
            'reservation' => (new OrderItemResource($item))->within($order)->toArray($request),
            'order' => [
                'code' => $order->code,
                // `displayStatus()` y no la columna: un pedido cuyo hold ya venció es `expired` DE
                // HECHO aunque `orders:expire` no haya pasado — y en staging llegó a llevar 24 h
                // muerto (`DECISIONES #115`). Mismo criterio que `OrderResource`.
                'status' => $order->displayStatus(),
                'created_label' => DisplayTime::format($order->created_at),
                // Lo decide el dominio, no el cliente: es la misma respuesta que gobierna el botón de
                // reintentar, y recomponerla aquí sería la quinta superficie que puede divergir.
                'can_be_retried' => $order->canBeRetried(),
            ],
        ];
    }
}
