<?php

namespace App\Http\Resources\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Payments\Contracts\PaymentTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fase 3 · paso 4c — un pedido **y el formulario firmado** para pagarlo.
 *
 * Es la respuesta de crear una reserva y la de reintentar su cobro: las dos devuelven lo mismo y a
 * propósito. En el reintento eso incluye el pedido con su `expires_at` **ya extendido**, que es
 * justo el dato que el cliente necesita para saber hasta cuándo tiene la plaza.
 *
 * **El pedido va anidado bajo `order` en vez de en la raíz** para no inventar una segunda forma de
 * pedido: es el MISMO recurso que sirven `me/orders` y `GET orders/{code}`, y un cliente que ya sabe
 * leerlo no tiene que aprender otra variante con campos de pago mezclados dentro.
 *
 * **`payment.fields` es un mapa opaco a propósito.** Son los campos del formulario tal y como los
 * exige la pasarela, y el cliente los reenvía SIN TOCARLOS: van firmados, y cambiar uno solo
 * invalida la firma (SIS0042). Que el contrato no los enumere es lo que permite cambiar de
 * proveedor de pago sin romper a nadie —el cliente hace un POST con lo que le den— y es la razón de
 * que `provider` viaje al lado.
 *
 * @property-read Order $resource
 */
class OrderPaymentResource extends JsonResource
{
    /** El recurso va en la raíz (spec §4.3). */
    public static $wrap = null;

    private ?PaymentTicket $ticket = null;

    private int $status = 200;

    public function withPaymentTicket(PaymentTicket $ticket): self
    {
        $this->ticket = $ticket;

        return $this;
    }

    public function withStatus(int $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function toResponse($request): JsonResponse
    {
        return parent::toResponse($request)->setStatusCode($this->status);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'order' => (new OrderResource($this->resource))->toArray($request),
            'payment' => [
                // Qué pasarela ha abierto el cobro. Hoy solo hay una; el campo existe para que el
                // día que haya dos, el cliente no tenga que adivinarlo por la forma de `fields`.
                'provider' => $this->ticket?->payment->provider ?? '',
                'method' => 'POST',
                'url' => $this->ticket?->gatewayUrl() ?? '',
                'fields' => $this->ticket?->gatewayFields() ?? [],
            ],
        ];
    }
}
