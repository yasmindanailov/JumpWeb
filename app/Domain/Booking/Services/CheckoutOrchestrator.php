<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\CheckoutOutcome;
use App\Domain\Booking\Contracts\PaymentInitiation;
use App\Domain\Booking\Contracts\ReservationAdmission;
use App\Domain\Booking\Contracts\ReservationCheckout;
use App\Domain\Booking\Contracts\RetryOutcome;
use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Contracts\PaymentInitiationException;

/**
 * **La secuencia del dinero, en un solo sitio** (cierre de Fase 3,
 * `docs/specs/checkout-orquestado.md`).
 *
 * Aquí no se extrae ninguna regla nueva: la admisión, la creación y la ida del pago ya existían y
 * estaban verificadas. Lo que aporta esta clase es el ORDEN en que se llaman —y por eso es el trozo
 * de más riesgo del refactor—, porque una secuencia no la protege ninguna guarda de arquitectura:
 * un llamante que use los tres servicios correctos en el orden equivocado pasa `ApiBoundariesTest`
 * y `ModuleBoundariesTest` con nota. La red es `CheckoutOrchestratorTest`, un caso por punto del
 * orden, verificado por MUTACIÓN.
 *
 * ⚠️ **No abre transacción, y eso es la regla, no un olvido.** Son dos unidades de trabajo cortas y
 * separadas: `OrderCreator::createPendingOrder()` tiene la suya y `PaymentInitiator` la suya.
 * Envolverlas juntas tendría dos consecuencias graves y silenciosas:
 *  · el `lockForUpdate` de `lockSlots()` quedaría sostenido durante la firma del payload —contención
 *    justo en el punto que `AFORO-01` llama «el más sutil»—;
 *  · el `audit_logs` con `orders.payment_init_failed` que el initiator escribe al fallar haría
 *    **rollback** junto con la compensación, y `PAY-05` existe precisamente para que esas incidencias
 *    sean visibles en el panel y no solo una línea de log.
 *
 * ⚠️ **No captura `ReservationException`.** Sale entera hacia quien llame, porque lleva los doce
 * códigos de negocio del contrato público y cada superficie los traduce a lo suyo. No deja estado a
 * medias —la creación es una transacción entera y hace rollback—; lo único que sobrevive es la ficha
 * del limitador ya consumida, exactamente igual que antes de esta clase.
 */
class CheckoutOrchestrator implements ReservationCheckout
{
    public function __construct(
        private ReservationAdmission $admission,
        private OrderCreator $creator,
        private PaymentInitiation $gateway,
    ) {}

    /**
     * @param  array<int, array{ticket_type_id:int, date:string, time:string, qty:int}>  $cart
     * @param  ReservationCheckout::SOURCE_*  $source
     */
    public function start(User $user, array $cart, string $source): CheckoutOutcome
    {
        // (1) Admisión ANTES de crear nada, y CONSUMIENDO ficha: `admitReservation()` y no la
        // variante `mayReserve()`, que solo consulta. Es el punto donde nace el pedido que retiene
        // aforo, y un chequeo que no cuenta no limita nada.
        $decision = $this->admission->admitReservation((int) $user->getAuthIdentifier());

        if ($decision->denied()) {
            return CheckoutOutcome::deny($decision);
        }

        // (2) El pedido nace con su ventana de retención YA fijada (`AFORO-10`). El tercer argumento
        // no es opcional en la práctica: su default es un pedido FIRME que no caduca, así que
        // omitirlo dejaría aforo retenido para siempre si el cliente abandona. La ventana la POSEE
        // esta clase —no se recibe por parámetro— para que ninguna superficie futura pueda alargarla.
        $order = $this->creator->createPendingOrder($user, $cart, OrderCreator::checkoutHoldUntil());

        try {
            // (3) Ida del pago sobre el pedido YA persistido: el `Payment` ata `gateway_order ↔ Order`
            // en base de datos, y ese vínculo es la única forma robusta de reconocer el pedido cuando
            // la pasarela responda (llega sin sesión válida: POST cross-site con `SameSite=Lax`).
            $ticket = $this->gateway->open($order, $user->locale, $source);
        } catch (PaymentInitiationException $e) {
            // (4) El diagnóstico ya está en el log y en `audit_logs`: lo dejó el initiator antes de
            // lanzar. Aquí solo se decide el DESTINO DEL PEDIDO, que es dominio: soltarlo, porque
            // retendría una plaza que nadie va a pagar. La disculpa que ve el cliente es de quien
            // capture, por eso se re-lanza.
            $order->releaseAfterFailedPaymentStart();

            throw $e;
        }

        return CheckoutOutcome::allow($order, $ticket);
    }

    /**
     * @param  ReservationCheckout::SOURCE_*  $source
     */
    public function retry(User $user, string $orderCode, string $source): RetryOutcome
    {
        // (1) La admisión del reintento va primero, y hace DOS cosas que el orden protege: valida
        // que el pedido siga siendo reintentable de este titular y EXTIENDE su retención con el
        // UPDATE atómico condicionado de `PAY-04`. Invertirlo con el `reopen()` marcaría
        // `SUPERSEDED` los intentos de un pedido cuyo hold aún no se ha validado ni extendido.
        $verdict = $this->admission->admitPaymentRetry((int) $user->getAuthIdentifier(), $orderCode);

        if ($verdict->denied()) {
            return RetryOutcome::deny($verdict);
        }

        /** @var Order $order Garantizado por `allow`; hold ya extendido. */
        $order = $verdict->order;

        // (2) Cobro nuevo sobre el mismo pedido. **Sin `catch`, y es deliberado**: al revés que en
        // `start()`, aquí un fallo de la pasarela NO toca el pedido —sigue vivo con su hold recién
        // extendido, así que el cliente puede volver a intentarlo—. La asimetría entre los dos
        // métodos es una regla de negocio, y su prueba es que añadir aquí una compensación deja un
        // test en rojo.
        $ticket = $this->gateway->reopen($order, $user->locale, $source);

        return RetryOutcome::allow($order, $ticket);
    }
}
