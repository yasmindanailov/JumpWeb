<?php

namespace App\Http\Controllers\Payments;

use App\Domain\Booking\Contracts\ReservationCheckout;
use App\Domain\Booking\Contracts\RetryAdmission;
use App\Domain\Booking\Models\Order;
use App\Domain\Payments\Contracts\PaymentInitiationException;
use App\Domain\Payments\Contracts\PaymentTicket;
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
 * **No decide nada por su cuenta, ni siquiera el orden.** La política de admisión —pausa de
 * reservas, frecuencia por titular y la extensión atómica del hold que exige `PAY-04`—, la apertura
 * del cobro y la secuencia entre ambas viven en `Booking\Contracts\ReservationCheckout` desde el
 * cierre de Fase 3. Este controlador solo traduce el resultado a la respuesta HTTP que espera «Mis
 * pedidos»: un redirect con su `status` de flash.
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
    public function __invoke(Request $request, string $code, ReservationCheckout $checkout): View|RedirectResponse
    {
        $user = $request->user();
        assert($user !== null); // auth middleware lo garantiza

        try {
            $outcome = $checkout->retry($user, $code, ReservationCheckout::SOURCE_RETRY_ACCOUNT);
        } catch (PaymentInitiationException) {
            // El diagnóstico (log + `audit_logs`) ya lo dejó el initiator y el pedido NO se ha
            // tocado —sigue vivo y se puede volver a intentar—: aquí solo se decide qué ve el
            // cliente.
            return redirect()
                ->route('account.orders')
                ->with('status', 'order-retry-failed');
        }

        if ($outcome->denied()) {
            /** @var RetryAdmission $verdict Garantizado por `denied()`. */
            $verdict = $outcome->denial;

            return redirect()
                ->route('account.orders')
                ->with('status', $this->flashFor($verdict->reason));
        }

        /** @var Order $order Garantizado por `allow`; el hold ya está extendido. */
        $order = $outcome->order;
        /** @var PaymentTicket $ticket Garantizado por `allow`. */
        $ticket = $outcome->ticket;

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
