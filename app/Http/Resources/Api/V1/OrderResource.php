<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 3 · paso 1 — un pedido visto por su dueño.
 *
 * Existe por el hallazgo 4 de la revisión del spec (§8): **un cliente que pierde el estado no podía
 * recuperar un `pending` a medio pagar** mientras su retención de aforo sigue viva. De ahí que la
 * lista incluya TODOS los estados y que estos tres campos sean el corazón del recurso:
 * `status`, `expires_at` y `online_amount_cents` — con ellos el cliente sabe que hay algo que
 * rescatar, hasta cuándo y por cuánto.
 *
 * **Dinero: todo en céntimos enteros y con su moneda al lado**, como en base de datos y como ya hace
 * el export RGPD. Ningún importe se formatea aquí: dar el número formateado obligaría a la API a
 * decidir el locale del dinero, que es decisión de quien lo muestra.
 *
 * **El reembolso NO es un estado**, es una dimensión aparte (`refunded_at` + `refund_amount_cents`);
 * `Order::STATUS_REFUNDED` sobrevive solo por datos antiguos. Por eso viaja en su propio bloque y no
 * dentro de `status`: un pedido puede estar `paid` y parcialmente reembolsado a la vez.
 *
 * `can_be_retried` lo decide el dominio, no el cliente. Es la misma respuesta que usa el botón de
 * «Mis pedidos» en la web, y quien la sirva de verdad será `POST orders/{code}/payment` en el paso 4.
 *
 * @property-read Order $resource
 */
class OrderResource extends JsonResource
{
    /** El recurso va en la raíz cuando se pide suelto (spec §4.3). */
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $order = $this->resource;
        $summary = $order->financialSummary();

        return [
            'code' => $order->code,
            // `displayStatus()` y no la columna: un pedido cuyo hold ya venció es `expired` DE HECHO
            // aunque el barrido periódico todavía no haya pasado por él. El cliente vería si no un
            // «pendiente» que ya no puede pagar.
            'status' => $order->displayStatus(),
            'currency' => $order->currency,
            'total_cents' => (int) $order->total,
            // Importe que se cobra ONLINE: la señal si el producto la usa, el total si no. NO es
            // «lo que falta por pagar» —un pedido ya pagado sigue informando el mismo importe—, y
            // el nombre lo dice porque el primer intento lo llamó `online_due` y el test de
            // contrato destapó la mentira. Su valor es que es LA MISMA fuente que consumirán
            // `Payment.amount` y el `DS_MERCHANT_AMOUNT` del reintento (paso 4): el cliente puede
            // enseñar de antemano cuánto se le va a cobrar, sin recalcularlo.
            'online_amount_cents' => $order->onlineDueCents(),
            // Lo que queda por pagar EN PUERTA (resto de la señal, extras de ediciones). No es deuda
            // online y no debe sumarse a la anterior.
            'pending_at_gate_cents' => $summary->pendingAtGate(),
            'refund' => [
                'refunded_at' => $order->refunded_at?->toIso8601String(),
                'amount_cents' => (int) ($order->refund_amount_cents ?? 0),
                'fully_refunded' => $order->isFullyRefunded(),
            ],
            'can_be_retried' => $order->canBeRetried(),
            'created_at' => $order->created_at?->toIso8601String(),
            'paid_at' => $order->paid_at?->toIso8601String(),
            // Cuándo se libera la retención de aforo de un pedido pendiente. `null` en un pedido
            // firme (`AFORO-10`: el default de `createPendingOrder` es un pedido que no caduca).
            'expires_at' => $order->expires_at?->toIso8601String(),
            'items' => $order->items->whereNull('parent_item_id')->values()
                // El pedido baja a cada línea: `ReservationFinancials` lo necesita y navegarlo desde
                // la línea sería una consulta por línea (Fase 4 · paso 4.0b).
                ->map(fn ($item) => (new OrderItemResource($item))->within($order)->resolve($request))
                ->all(),
            // ⚠️ **No es el `any()` de `items[].needs_guest_form`, y por eso tiene otro nombre.**
            // `Order::needsGuestForm()` descarta primero las líneas CANCELADAS: un cliente que
            // agregara el campo de las líneas contaría una cancelada y prometería un formulario que
            // nadie va a pedir. Se publica compuesto por el servidor (Fase 4 · paso 4.0b).
            'guest_form_pending' => $order->needsGuestForm(),
        ];
    }
}
