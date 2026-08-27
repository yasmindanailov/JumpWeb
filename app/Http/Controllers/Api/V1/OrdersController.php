<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Contracts\AdmissionDecision;
use App\Domain\Booking\Contracts\ReservationCheckout;
use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DependentAssigner;
use App\Domain\Payments\Contracts\PaymentInitiationException;
use App\Domain\Payments\Contracts\PaymentTicket;
use App\Http\Api\AdmissionCodeMap;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use App\Http\Api\CartPayload;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderPaymentResource;
use App\Http\Resources\Api\V1\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Fase 3 · paso 4c — CREAR la reserva y abrir su cobro (`docs/specs/api-v1.md` §4.4 y §4.5).
 *
 * **Este controlador ya no escribe la secuencia: la pide.** Hasta el cierre de Fase 3, aquí vivía
 * el orden «admitir → crear → abrir cobro» copiado a mano, igual que en las otras tres superficies
 * de entrega. Ahora es una llamada a `Booking\Contracts\ReservationCheckout`, y el orden —que es la
 * regla, y lo único que ninguna guarda de arquitectura sabe ver— vive en un solo sitio del dominio
 * (`docs/specs/checkout-orquestado.md`).
 *
 * Lo que queda aquí es lo que de verdad es de HTTP:
 *  - **201**, porque crea un recurso: el pedido existe y retiene aforo desde este momento, se
 *    complete el pago o no;
 *  - la traducción del veredicto denegado a **409/429** (`admissionDenial()`);
 *  - la traducción de «la pasarela no abrió» a **502**. ⚠️ Ese `catch` es obligatorio y no
 *    decorativo: `ApiExceptionRenderer` solo conoce `ReservationException`, así que si desapareciera,
 *    el contrato —que documenta `payment_unavailable` con 502— degradaría a un 500 en silencio. El
 *    pedido ya viene soltado por el dominio cuando la excepción llega hasta aquí.
 *
 * `ReservationException` (cesta no vendible) sí se deja pasar a propósito: la traduce
 * `ApiExceptionRenderer` con `ReservationErrorMap`, que es exhaustivo por test.
 */
class OrdersController extends Controller
{
    /**
     * Crea la reserva y devuelve el pedido junto con el formulario firmado de la pasarela.
     *
     * **201**, porque crea un recurso: el pedido existe y retiene aforo desde este momento, se
     * complete el pago o no.
     */
    public function store(Request $request, ReservationCheckout $checkout, DependentAssigner $assigner): OrderPaymentResource|JsonResponse
    {
        $validated = $request->validate(CartPayload::rules());

        /** @var User $user */
        $user = $request->user();

        // Fase 6 · menores a cargo, tanda 4 (`specs/menores-a-cargo.md` §9.9.3 D3): la asignación de
        // entradas a menores se COMPRUEBA ANTES del dinero. Un id ajeno, un menor que ese día ya es
        // adulto o uno sin la exención firmada responde 422 POR CAMPO, sin crear el pedido ni consumir
        // la ficha de admisión: el cliente se entera comprando, y nada queda a medias. Es la capa de
        // entrega componiendo dos módulos —Booking no puede mirar a Identity—, y por eso vive aquí.
        $assignments = CartPayload::assignments($validated['items']);
        $rejections = $assigner->check($user, $assignments);
        if ($rejections !== []) {
            throw ValidationException::withMessages($rejections);
        }

        try {
            $outcome = $checkout->start(
                $user,
                CartPayload::toCart($validated['items']),
                ReservationCheckout::SOURCE_CHECKOUT,
            );
        } catch (PaymentInitiationException) {
            // El diagnóstico ya está en el log y en `audit_logs` (lo deja el initiator), y el pedido
            // ya lo soltó el dominio. Aquí solo se decide qué ve el cliente.
            return ApiErrorResponse::make(ApiErrorCode::PaymentUnavailable, 502);
        }

        if ($outcome->denied()) {
            /** @var AdmissionDecision $denial Garantizado por `denied()`. */
            $denial = $outcome->denial;

            return $this->admissionDenial($denial);
        }

        /** @var Order $order Garantizado por `allow`; ya retiene aforo. */
        $order = $outcome->order;
        /** @var PaymentTicket $ticket Garantizado por `allow`. */
        $ticket = $outcome->ticket;

        // Y se ESCRIBE después del `allow` (§4.10): el pedido ya existe, retiene aforo y su cobro está
        // abierto — fuera de la transacción de los locks y sin poder deshacer nada de eso. Identity la
        // escribe bajo el lock del titular y re-valida las mismas reglas; si algo cambió entre las dos
        // fases la línea se queda sin asignar y el pedido sigue en pie. Va ANTES de `fresh()` para que
        // la 201 ya la refleje.
        if (CartPayload::hasAssignments($assignments)) {
            $assigner->assign($user, (int) $order->getKey(), $assignments);
        }

        return (new OrderPaymentResource($order->fresh(['items.ticketType', 'items.slot', 'items.children.ticketType', 'adjustments', 'payments.refunds'])))
            ->withPaymentTicket($ticket)
            ->withStatus(201);
    }

    /**
     * Un pedido del titular. Existe para que un cliente que pierde la respuesta de la creación pueda
     * recuperar SU pedido sin recorrer la paginación de `me/orders`.
     *
     * El scoping va por el guard y por el código a la vez: un código que no es de este titular
     * responde **404**, no 403 — decir «existe pero no es tuyo» convertiría el endpoint en un
     * oráculo de códigos de pedido ajenos.
     */
    public function show(Request $request, string $code): OrderResource
    {
        /** @var User $user */
        $user = $request->user();

        $order = Order::query()
            ->with(['items.ticketType', 'items.slot', 'items.children.ticketType', 'adjustments', 'payments.refunds'])
            ->where('user_id', $user->getAuthIdentifier())
            ->where('code', $code)
            ->first();

        abort_if($order === null, 404);

        return new OrderResource($order);
    }

    /**
     * Veredicto denegado de la política → respuesta HTTP.
     *
     * **409 y no 422**: la petición está bien formada y lo que impide la reserva es el estado del
     * sistema o del titular, no los datos enviados. La excepción es el límite de frecuencia, que sí
     * es un 429 —el cliente debe esperar y reintentar, que es exactamente lo que significa—.
     */
    private function admissionDenial(AdmissionDecision $decision): JsonResponse
    {
        /** @var string $reason Garantizado por `denied()`. */
        $reason = $decision->reason;

        return ApiErrorResponse::make(
            AdmissionCodeMap::codeFor($reason),
            AdmissionCodeMap::statusFor($reason),
            params: $reason === AdmissionDecision::TOO_MANY_PENDING
                ? ['max' => (int) ($decision->context['max'] ?? 0)]
                : [],
        );
    }
}
