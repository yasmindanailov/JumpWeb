<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Booking\Contracts\AdmissionDecision;
use App\Domain\Booking\Contracts\ReservationAdmission;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Services\OrderCreator;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Exceptions\PaymentInitiationException;
use App\Domain\Payments\Services\PaymentInitiator;
use App\Domain\Payments\Services\PaymentSettings;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiErrorResponse;
use App\Http\Api\CartPayload;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderPaymentResource;
use App\Http\Resources\Api\V1\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Fase 3 · paso 4c — CREAR la reserva y abrir su cobro (`docs/specs/api-v1.md` §4.4 y §4.5).
 *
 * A diferencia de 4a y 4b, aquí **no hay nada que extraer**: la admisión
 * (`Booking\Contracts\ReservationAdmission`), la creación (`OrderCreator`) y la ida del pago
 * (`Payments\PaymentInitiator`) ya existen y están verificadas. Lo que aporta este controlador es
 * la SECUENCIA, y ahí está todo el riesgo del paso.
 *
 * **El orden es la regla, y cada paso está donde está por un motivo:**
 *
 *  1. `admitReservation()` **antes de crear y consumiendo** ficha del limitador. Es lo que cierra el
 *     hallazgo E del origen —un usuario autenticado agotando el aforo del día sin pagar— y por eso
 *     no vale la variante que solo consulta: un chequeo que no cuenta no limita nada.
 *  2. `createPendingOrder()` **con la ventana de retención SIEMPRE** (`AFORO-10`). El tercer
 *     parámetro no es opcional en la práctica: su default es un pedido FIRME que no caduca, así que
 *     omitirlo dejaría aforo retenido para siempre si el cliente abandona.
 *  3. `open()` **después de que el pedido exista**, porque el `Payment` ata `gateway_order ↔ Order`
 *     en base de datos y esa es la única forma de reconocer el pedido cuando la pasarela responda
 *     sin sesión.
 *  4. Si el cobro no llega a abrirse, **el pedido se suelta en el acto**
 *     (`releaseAfterFailedPaymentStart()`): retendría una plaza que nadie va a pagar. Es decisión
 *     del LLAMANTE y no del initiator porque en un reintento la respuesta correcta es la contraria
 *     —no tocar el pedido, que sigue vivo—.
 *
 * Nada de esto lo puede ver `ApiBoundariesTest`: un controlador que llama a los servicios correctos
 * en el orden equivocado pasa esa guarda igual (spec §10, punto 6). Lo que lo vigila son los tests
 * de este endpoint, uno por cada punto de la lista.
 *
 * **Por qué la secuencia no se extrae a un servicio de dominio**: cruzaría Booking → Payments con
 * una flecha de ORQUESTACIÓN, y la baseline de `ModuleBoundariesTest` solo encoge — añadirle una
 * entrada es la señal de que algo está mal hecho. La capa de entrega es el *composition root*
 * declarado desde Fase 2 · paso 3, y es donde la composición entre módulos está sancionada. El
 * arreglo de fondo tiene nombre y ya está en el backlog de la fase: la abstracción `PaymentProvider`
 * (`00-REFACTOR`, Fase 3), que convertiría la ida del pago en un contrato como el del reembolso.
 * Meterla dentro de este paso habría mezclado dos trabajos en un diff.
 */
class OrdersController extends Controller
{
    /**
     * Crea la reserva y devuelve el pedido junto con el formulario firmado de la pasarela.
     *
     * **201**, porque crea un recurso: el pedido existe y retiene aforo desde este momento, se
     * complete el pago o no.
     */
    public function store(
        Request $request,
        ReservationAdmission $admission,
        OrderCreator $creator,
        PaymentInitiator $initiator,
    ): OrderPaymentResource|JsonResponse {
        $validated = $request->validate(CartPayload::rules());

        /** @var User $user */
        $user = $request->user();

        // (1) Admisión, ANTES de crear nada y consumiendo ficha.
        $decision = $admission->admitReservation((int) $user->getAuthIdentifier());

        if ($decision->denied()) {
            return $this->admissionDenial($decision);
        }

        // (2) El pedido nace con su ventana de retención ya fijada (`AFORO-10`). Un
        // `ReservationException` —cesta vacía, franja agotada, fuera de horario— sale por el sobre
        // de error con su código de negocio: lo traduce `ApiExceptionRenderer` con
        // `ReservationErrorMap`, que es exhaustivo por test.
        $order = $creator->createPendingOrder(
            $user,
            CartPayload::toCart($validated['items']),
            now()->addMinutes(PaymentSettings::holdMinutes()),
        );

        // (3) Ida del pago sobre el pedido ya persistido.
        try {
            $ticket = $initiator->open($order, $user->locale, PaymentInitiator::SOURCE_CHECKOUT);
        } catch (PaymentInitiationException) {
            // (4) El diagnóstico ya está en el log y en `audit_logs` (lo deja el initiator). Aquí
            // solo se decide el destino del pedido: soltarlo, porque retendría una plaza que nadie
            // va a pagar.
            $order->releaseAfterFailedPaymentStart();

            return ApiErrorResponse::make(ApiErrorCode::PaymentUnavailable, 502);
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
        return match ($decision->reason) {
            AdmissionDecision::RESERVATIONS_PAUSED => ApiErrorResponse::make(ApiErrorCode::ReservationsPaused, 409),
            AdmissionDecision::TOO_MANY_PENDING => ApiErrorResponse::make(
                ApiErrorCode::TooManyPendingOrders,
                409,
                params: ['max' => (int) ($decision->context['max'] ?? 0)],
            ),
            default => ApiErrorResponse::make(ApiErrorCode::TooManyRequests, 429),
        };
    }
}
