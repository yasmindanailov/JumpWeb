<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PaymentStatusResource;
use Illuminate\Http\Request;

/**
 * Fase 3 · paso 4d — EN QUÉ HA QUEDADO el pago de un pedido (`docs/specs/api-v1.md` §4.5).
 *
 * Es el endpoint que un cliente CONSULTA EN BUCLE mientras espera el desenlace, y existe porque el
 * desenlace no siempre vuelve por donde se fue: con un terminal que no incluye los datos firmados en
 * la redirección, la única confirmación es la notificación server-to-server, que llega cuando llega.
 * La web resuelve eso con un `wire:poll` sobre su propia sesión; un cliente de API no tiene sesión
 * de la web ni nada que mirar. Este es su equivalente.
 *
 * **Dos ejes, y hacían falta los dos.** `order_status` dice qué ha sido de la RESERVA y
 * `payment_status` qué ha sido del último INTENTO de cobro. Con uno solo, un pedido rechazado y uno
 * que nadie ha intentado pagar son idénticos: ambos `pending`. Ese matiz vivía únicamente en la
 * sesión de la web (`purchase.failed_code`), así que la API habría dicho «pendiente» durante toda la
 * ventana de retención y luego «caducado», **nunca «reintenta»** — teniendo el reintento disponible
 * desde el paso 4c.
 *
 * **Deliberadamente pequeño.** No devuelve el pedido entero: se pregunta cada pocos segundos, y
 * arrastrar líneas y complementos en cada vuelta sería malgastar la superficie de más frecuencia de
 * toda la API. Para el pedido completo está `GET orders/{code}`.
 *
 * ⚠️ **No transiciona nada, y eso es `PAY-01`.** El ÚNICO autorizado a pasar una `Order` a `paid` es
 * `RedsysReturnHandler`, que verifica la firma de la pasarela. Consultar el estado no puede
 * cambiarlo: si este endpoint «confirmara» un pago por el hecho de que alguien pregunta, sería una
 * segunda vía a `paid` sin firma — exactamente lo que el invariante prohíbe.
 */
class OrderPaymentStatusController extends Controller
{
    public function __invoke(Request $request, string $code): PaymentStatusResource
    {
        /** @var User $user */
        $user = $request->user();

        // `payments` eager-loaded: los dos ejes y `can_be_retried` se derivan de esa relación, y
        // este endpoint se consulta en bucle. Sin esto serían tres consultas por vuelta.
        $order = Order::query()
            ->with('payments')
            ->where('user_id', $user->getAuthIdentifier())
            ->where('code', $code)
            ->first();

        // Un código ajeno responde igual que uno inexistente: decir «existe pero no es tuyo»
        // convertiría un endpoint de sondeo en un oráculo de códigos de pedido.
        abort_if($order === null, 404);

        return new PaymentStatusResource($order);
    }
}
