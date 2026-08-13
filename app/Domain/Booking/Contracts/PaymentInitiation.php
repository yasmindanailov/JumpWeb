<?php

namespace App\Domain\Booking\Contracts;

use App\Domain\Booking\Models\Order;
use App\Domain\Payments\Contracts\PaymentInitiationException;
use App\Domain\Payments\Contracts\PaymentTicket;

/**
 * La IDA del pago, vista desde Booking: abrir un cobro para un pedido y devolver el formulario ya
 * firmado (cierre de Fase 3, `docs/specs/checkout-orquestado.md` §4.2).
 *
 * ⚠️ **Es un PUERTO REQUERIDO, no ofrecido, y por eso rompe la lectura habitual de esta carpeta.**
 * El resto de `Booking\Contracts` es «lo que Booking OFRECE a los demás» (el catálogo, la oferta de
 * horas, el precio de la cesta). Este es al revés: es **lo que Booking NECESITA de una pasarela**, y
 * lo implementa Payments (`Payments\Services\PaymentInitiator`, atado en `PaymentsServiceProvider`,
 * no en el de Booking).
 *
 * **Por qué vive aquí y no en `Payments\Contracts`, que sería lo simétrico a {@see RefundGateway}**:
 * su firma habla de `Order`, un modelo de Booking. Un contrato en `Payments/Contracts/` que nombrase
 * `Order` sería una flecha Payments→`Booking\Models` y exigiría una entrada nueva en la baseline de
 * `ModuleBoundariesTest`, que **solo encoge**. La regla que sale de ahí y que conviene recordar:
 * **el puerto vive en el módulo cuyos tipos habla**. `RefundGateway` habla de `Payment` y por eso sí
 * puede vivir en Payments; este habla de `Order` y vive en Booking.
 *
 * **Qué NO decide**: si el cliente puede reservar o reintentar. Eso es {@see ReservationAdmission} y
 * va ANTES; el orden entre ambos es la regla, y quien la guarda es
 * `Booking\Services\CheckoutOrchestrator`.
 *
 * Es la mitad de la abstracción `PaymentProvider` del backlog que el owner aprobó hacer ahora
 * (2026-08-13): la que quita la duplicación MEDIDA. La selección de driver (Stripe u otros) viaja a
 * Fase 6 con la app, su primer lector real.
 */
interface PaymentInitiation
{
    /**
     * PRIMER cobro de un pedido recién creado.
     *
     * @param  ?string  $preferredLocale  idioma del titular; `null` cae al locale de la petición
     * @param  string  $source  una de las constantes `ReservationCheckout::SOURCE_*` — solo viaja a
     *                          `audit_logs`, para que la operadora sepa por dónde entró el cobro
     *
     * @throws PaymentInitiationException si la pasarela no llega a abrir el cobro
     */
    public function open(Order $order, ?string $preferredLocale, string $source): PaymentTicket;

    /**
     * REINTENTO sobre el MISMO pedido, que sigue reteniendo su plaza.
     *
     * Crea un intento nuevo y marca `SUPERSEDED` los pendientes anteriores (`PAY-04`): reusar el
     * fallido daría 0913, y un intento viejo autorizado tarde emitiría tickets duplicados.
     *
     * @param  string  $source  una de las constantes `ReservationCheckout::SOURCE_*`
     *
     * @throws PaymentInitiationException si la pasarela no llega a abrir el cobro
     */
    public function reopen(Order $order, ?string $preferredLocale, string $source): PaymentTicket;
}
