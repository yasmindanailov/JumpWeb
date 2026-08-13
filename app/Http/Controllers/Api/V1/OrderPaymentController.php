<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Contracts\ReservationCheckout;
use App\Domain\Booking\Contracts\RetryAdmission;
use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Contracts\PaymentInitiationException;
use App\Domain\Payments\Contracts\PaymentTicket;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderPaymentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fase 3 · paso 4c — REINTENTAR el cobro de un pedido que sigue vivo.
 *
 * Es la tercera superficie que hace esto, y por eso el paso 2 extrajo antes las reglas: el sidebar y
 * «Mis pedidos» ya preguntan a los mismos dos servicios. Aquí no se decide nada nuevo —solo qué ve
 * un cliente de API— y la diferencia con `POST orders` es toda de dominio:
 *
 *  - **no se aplica el tope de pedidos pendientes**: un reintento no crea aforo nuevo, reusa la
 *    plaza que ese mismo pedido ya retiene y que ya cuenta en ese tope;
 *  - **la retención se EXTIENDE con un UPDATE atómico condicionado** (`PAY-04`). Comprobar «sigue
 *    pendiente y no vencida» y extender en sentencias separadas resucita un hold ya cruzado sin
 *    recontar aforo (hallazgo L2 del origen), y de paso ese `WHERE` con el titular dentro es la
 *    defensa anti-IDOR: no hay forma de extender el hold de un pedido ajeno ni acertando su código;
 *  - **si el cobro no se abre, el pedido NO se toca.** Al revés que en la creación: aquí la reserva
 *    sigue viva con su hold recién extendido, así que el cliente puede volver a intentarlo.
 *
 * Se crea un `Payment` NUEVO en vez de reusar el fallido porque la pasarela exige `gateway_order`
 * único por comercio y terminal de por vida; los intentos pendientes anteriores pasan a
 * `SUPERSEDED`, que es lo que impide que uno autorizado tarde emita tickets duplicados (`PAY-02`).
 * Todo eso vive en `PaymentInitiator::reopen()`, no aquí.
 *
 * Desde el cierre de Fase 3, tampoco vive aquí el ORDEN entre admitir y reabrir —que es lo que
 * protege `PAY-04`, porque marcar `SUPERSEDED` antes de validar y extender el hold reabriría un
 * cobro sobre una plaza que ya pudo cederse—: lo aplica `Booking\Contracts\ReservationCheckout`.
 * Este controlador es traducción HTTP y nada más.
 */
class OrderPaymentController extends Controller
{
    public function store(Request $request, string $code, ReservationCheckout $checkout): OrderPaymentResource|JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $outcome = $checkout->retry($user, $code, ReservationCheckout::SOURCE_RETRY_ACCOUNT);
        } catch (PaymentInitiationException) {
            // El diagnóstico ya está registrado y el pedido NO se ha tocado —sigue vivo y con el
            // hold recién extendido—, así que se puede volver a intentar. Es la diferencia
            // deliberada con `POST orders`, donde un primer cobro fallido sí suelta el pedido; el
            // dominio la aplica y aquí solo se traduce a lo que ve el cliente.
            return ApiErrorResponse::make(ApiErrorCode::PaymentUnavailable, 502);
        }

        if ($outcome->denied()) {
            /** @var RetryAdmission $verdict Garantizado por `denied()`. */
            $verdict = $outcome->denial;

            return $this->denial($verdict);
        }

        /** @var Order $order Garantizado por `allow`; su hold ya viene extendido. */
        $order = $outcome->order;
        /** @var PaymentTicket $ticket Garantizado por `allow`. */
        $ticket = $outcome->ticket;

        return (new OrderPaymentResource($order->fresh(['items.ticketType', 'items.slot', 'items.children.ticketType', 'adjustments', 'payments.refunds'])))
            ->withPaymentTicket($ticket);
    }

    /**
     * Veredicto denegado → respuesta HTTP. Los tres motivos son situaciones distintas para el
     * cliente y por eso no comparten código: la pausa no se arregla reintentando, el límite de
     * frecuencia sí **y la reserva sigue intacta**, y solo `NOT_RETRYABLE` significa de verdad que
     * ya no hay nada que pagar. Reutilizar ahí el mensaje de «caducado» le diría a quien pulsó dos
     * veces seguidas que ha perdido su reserva — es el error que el paso 2 encontró en la web.
     */
    private function denial(RetryAdmission $verdict): JsonResponse
    {
        return match ($verdict->reason) {
            RetryAdmission::RESERVATIONS_PAUSED => ApiErrorResponse::make(ApiErrorCode::ReservationsPaused, 409),
            RetryAdmission::RATE_LIMITED => ApiErrorResponse::make(ApiErrorCode::TooManyRequests, 429),
            default => ApiErrorResponse::make(ApiErrorCode::OrderNotRetryable, 409),
        };
    }
}
