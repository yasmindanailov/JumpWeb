<?php

namespace App\Domain\Booking\Contracts;

use App\Domain\Booking\Exceptions\ReservationException;
use App\Domain\Identity\Models\User;
use App\Domain\Payments\Contracts\PaymentInitiationException;

/**
 * La SECUENCIA de la compra: admitir → crear el pedido → abrir el cobro (cierre de Fase 3,
 * `docs/specs/checkout-orquestado.md`).
 *
 * **Lo que este contrato aporta no son llamadas: es el ORDEN**, que es la regla y que ninguna guarda
 * de arquitectura sabe ver. Un controlador que llama a los tres servicios correctos en el orden
 * equivocado pasa `ApiBoundariesTest` con nota. Antes de existir esto, la secuencia estaba escrita a
 * mano en **cinco puntos de cuatro clases de entrega** —el sidebar (crear y reintentar), «Mis
 * pedidos», y los dos endpoints de la API—, y cada copia era una oportunidad de que una sola se
 * dejara un paso.
 *
 * Los cuatro puntos del orden, cada uno con su porqué:
 *  1. la admisión va **antes** de crear y **consume** ficha del limitador — un chequeo que no cuenta
 *     no limita nada, y es lo que cierra el hallazgo E del origen (agotar el aforo del día sin pagar);
 *  2. el pedido nace **siempre** con su ventana de retención (`AFORO-10`);
 *  3. el cobro se abre **después** de que el pedido exista, porque el `Payment` ata
 *     `gateway_order ↔ Order` en BD y es la única forma de reconocerlo cuando la pasarela responda
 *     sin sesión;
 *  4. si el cobro no abre, un primer intento **suelta** el pedido y un reintento **no lo toca**. La
 *     asimetría es deliberada: en el reintento la reserva sigue viva con su hold recién extendido.
 *
 * **Qué NO absorbe**: la presentación. El veredicto denegado lo traduce cada superficie a lo suyo
 * (un `addError` en el sidebar, un flash en «Mis pedidos», un 409/429 en la API), y las dos
 * excepciones se propagan por el mismo motivo — `ReservationException` lleva los doce códigos de
 * negocio del contrato público, y el 502 de «la pasarela no abrió» es una disculpa, no una regla.
 *
 * Implementación: `Booking\Services\CheckoutOrchestrator` (bind en `BookingServiceProvider`).
 */
interface ReservationCheckout
{
    /**
     * Compra desde el sidebar público.
     *
     * Las tres constantes viven aquí, y no en el initiator que las escribe, porque este es el
     * símbolo que consume la capa de ENTREGA: si siguieran en `Payments\Services\PaymentInitiator`,
     * o la entrega tendría que seguir nombrándolo —y volvemos a tener la ida repartida— o el
     * orquestador tendría que alcanzar `Payments\Services`, que el grafo de módulos prohíbe.
     *
     * ⚠️ Los literales NO se cambian: viajan a `audit_logs` y son lo que la operadora lee para saber
     * por dónde entró un cobro fallido (`PAY-05`).
     */
    public const SOURCE_CHECKOUT = 'checkout';

    /** Reintento desde el propio sidebar, tras ver «pago denegado» sin salir de la compra. */
    public const SOURCE_RETRY_SIDEBAR = 'retry_sidebar';

    /** Reintento desde «Mis pedidos» (web) o desde su equivalente de la API. */
    public const SOURCE_RETRY_ACCOUNT = 'retry_account';

    /**
     * Admite, crea la reserva y abre su primer cobro.
     *
     * @param  array<int, array{ticket_type_id:int, date:string, time:string, qty:int}>  $cart
     * @param  self::SOURCE_*  $source
     *
     * @throws ReservationException si la cesta no es vendible (vacía, agotada, fuera de horario…).
     *                              Se propaga a propósito: la API la traduce a su código de negocio
     *                              y la web la pinta inline. El pedido no queda a medias — la
     *                              creación es una transacción entera.
     * @throws PaymentInitiationException si la pasarela no abre el cobro. **El pedido ya se ha
     *                                    soltado** cuando esto sale: la compensación es del dominio,
     *                                    y quien captura solo decide qué ve el cliente.
     */
    public function start(User $user, array $cart, string $source): CheckoutOutcome;

    /**
     * Admite y reabre el cobro de un pedido que sigue vivo.
     *
     * @param  self::SOURCE_*  $source
     *
     * @throws PaymentInitiationException si la pasarela no abre el cobro. **El pedido NO se toca**:
     *                                    sigue vivo con su hold recién extendido, así que el cliente
     *                                    puede volver a intentarlo.
     */
    public function retry(User $user, string $orderCode, string $source): RetryOutcome;
}
