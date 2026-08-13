<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Payments\Services\RedsysResponseCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 3 · paso 4d — el desenlace del pago de un pedido, en su forma mínima.
 *
 * **`declined_reason` es un CÓDIGO y `declined_message` un texto**, y la distinción es la misma que
 * hace el sobre de error (§4.3): el código es lo que un cliente PROGRAMA —«tarjeta caducada» invita
 * a probar otra, «denegada por tu banco» invita a llamar al banco— y el mensaje es lo que muestra
 * cuando no sabe hacer nada mejor. Devolver solo el texto habría obligado a todo cliente a
 * compararlo con cadenas traducidas.
 *
 * Los dos son `null` salvo que el ÚLTIMO intento sea el rechazado: enseñar el motivo de un rechazo
 * anterior mientras hay otro cobro en curso le diría al cliente que su tarjeta ha fallado cuando en
 * realidad está esperando respuesta.
 *
 * @property-read Order $resource
 */
class PaymentStatusResource extends JsonResource
{
    /** El recurso va en la raíz (spec §4.3). */
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $order = $this->resource;
        $declined = $order->declinedResponseCode();

        return [
            'order_code' => $order->code,
            // `displayStatus()` y no la columna: un pedido cuyo hold ya venció está `expired` DE
            // HECHO aunque el barrido periódico no haya pasado, y quien sondea necesita saberlo ya.
            'order_status' => $order->displayStatus(),
            'payment_status' => $order->paymentStatus(),
            // Lo decide el dominio, no el cliente: es la misma respuesta que gobierna el botón de
            // «Mis pedidos» en la web y la que `POST orders/{code}/payment` va a honrar.
            'can_be_retried' => $order->canBeRetried(),
            'declined_reason' => $declined === null ? null : RedsysResponseCode::reasonKey($declined),
            'declined_message' => $declined === null ? null : RedsysResponseCode::reasonText($declined),
            // Hasta cuándo se retiene la plaza. Es lo que le dice a quien sondea cuánto margen tiene
            // para reintentar antes de tener que rehacer la reserva.
            'expires_at' => $order->expires_at?->toIso8601String(),
        ];
    }
}
