<?php

namespace App\Http\Controllers\Payments;

use App\Domain\Booking\Contracts\ReservationAdmission;
use App\Domain\Booking\Contracts\RetryAdmission;
use App\Domain\Booking\Models\Order;
use App\Domain\Payments\Exceptions\PaymentInitiationException;
use App\Domain\Payments\Services\PaymentInitiator;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Reintento de pago Redsys desde la página «Mis pedidos» (audit edge cases 2026-05-28).
 *
 * Cierra el hueco descubierto en validación: el email `OrderPaymentDeclined` enviaba al cliente al
 * área privada (`/mi-cuenta/pedidos`), pero la vista no ofrecía un botón para reintentar el pago.
 * El cliente quedaba «informado pero sin acción».
 *
 * Desde Fase 3 · paso 2 **no decide nada por su cuenta**: la política de admisión —pausa de
 * reservas, frecuencia por titular y la extensión atómica del hold que exige `PAY-04`— vive en
 * `Booking\Contracts\ReservationAdmission`, y abrir el cobro en `Payments\PaymentInitiator`. Este
 * controlador solo traduce el veredicto a la respuesta HTTP que espera «Mis pedidos»: un redirect
 * con su `status` de flash.
 *
 * Que las reglas vivieran aquí tenía un coste medido: este endpoint aplicaba una política
 * DISTINTA de la del sidebar sin que nadie lo hubiera decidido (no limitaba la frecuencia por
 * titular, solo por IP con `throttle:6,1`). Ahora las dos superficies preguntan a la misma.
 *
 * NO usa Livewire — el controller HTTP es más simple (no necesita state ni reactividad) y la vista
 * intermedia es estática. CSRF activo por defecto (POST estándar autenticado).
 */
class RetryPaymentController extends Controller
{
    public function __invoke(
        Request $request,
        string $code,
        ReservationAdmission $admission,
        PaymentInitiator $initiator,
    ): View|RedirectResponse {
        $user = $request->user();
        assert($user !== null); // auth middleware lo garantiza

        $verdict = $admission->admitPaymentRetry((int) $user->getAuthIdentifier(), $code);

        if ($verdict->denied()) {
            return redirect()
                ->route('account.orders')
                ->with('status', $this->flashFor($verdict->reason));
        }

        /** @var Order $order Garantizado por `allowed`; el hold ya está extendido. */
        $order = $verdict->order;

        try {
            $ticket = $initiator->reopen($order, $user->locale, PaymentInitiator::SOURCE_RETRY_ACCOUNT);
        } catch (PaymentInitiationException) {
            // El diagnóstico (log + `audit_logs`) ya lo dejó el initiator: aquí solo se decide qué
            // ve el cliente. El pedido NO se toca: sigue vivo y se puede volver a intentar.
            return redirect()
                ->route('account.orders')
                ->with('status', 'order-retry-failed');
        }

        // Vista intermedia con auto-POST a la pasarela. La tarjeta NO toca este server.
        return view('payments.retry-redirect', [
            'redsysFormData' => $ticket->formData,
            'orderCode' => $order->code,
        ]);
    }

    /**
     * Veredicto del dominio → clave de flash de «Mis pedidos». Cada motivo tiene el suyo porque
     * describen situaciones distintas para el cliente: la pausa invita a llamar, el límite de
     * frecuencia a esperar un minuto **con la reserva intacta**, y solo `NOT_RETRYABLE` significa
     * de verdad que la plaza se soltó. Reutilizar ahí el mensaje de «ha caducado» le habría dicho
     * a quien pulsó dos veces seguidas que había perdido su reserva.
     */
    private function flashFor(?string $reason): string
    {
        return match ($reason) {
            RetryAdmission::RESERVATIONS_PAUSED => 'order-retry-paused',
            RetryAdmission::RATE_LIMITED => 'order-retry-throttled',
            default => 'order-retry-unavailable',
        };
    }
}
