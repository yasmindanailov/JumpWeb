<?php

namespace App\Domain\Booking\Contracts;

/**
 * La política de ADMISIÓN de reservas (Fase 3 · paso 2, `docs/specs/api-v1.md` §4.6.1).
 *
 * Responde a una sola pregunta —**¿se admite esta intención?**— y reúne las tres reglas que hasta
 * ahora vivían dentro de `Livewire\Tickets\Purchase`, es decir, en una clase de interfaz:
 *  1. **pausa de reservas** del panel (#218): con ella activa, ninguna superficie pública crea
 *     pedidos ni reabre cobros;
 *  2. **tope de pedidos pendientes vivos** por titular (aforo retenido sin pagar);
 *  3. **frecuencia** de creación de reservas por titular.
 *
 * Las tres cierran el hallazgo E de la auditoría del origen: sin ellas, un usuario autenticado
 * agota el aforo del día iterando la confirmación sin pagar. `OrderCreator` **no las contiene**, así
 * que un endpoint «delgado sobre `OrderCreator`» las reabriría — por eso esta extracción va ANTES
 * de exponer la compra por API (spec §9, paso 2 antes que el 4).
 *
 * Recibe `int $userId` y no el modelo `User`, igual que {@see CustomerReservations} y por el mismo
 * motivo: Booking no necesita importar un modelo de Identity para contar pedidos por `user_id`.
 *
 * Implementación actual: `App\Domain\Booking\Services\ReservationAdmissionPolicy` (bind en
 * `BookingServiceProvider`).
 */
interface ReservationAdmission
{
    /**
     * ¿Podría este titular crear una reserva AHORA? Consulta **pura**: no consume nada.
     *
     * Es para avisar temprano —el sidebar la usa al pasar del carrito al paso de pago— sin gastar
     * una ficha del limitador por una pantalla que no crea nada. La ficha se gasta en
     * {@see admitReservation()}, que es donde nace el pedido que retiene aforo.
     */
    public function mayReserve(int $userId): AdmissionDecision;

    /**
     * Admite (o no) la creación de una reserva y, si admite, **CONSUME** un intento del limitador.
     *
     * Se llama justo antes de crear el pedido. Que consuma es la mitad de su trabajo: un chequeo
     * que no cuenta no limita nada.
     */
    public function admitReservation(int $userId): AdmissionDecision;

    /**
     * Admite (o no) reintentar el pago del pedido `$orderCode` de este titular y, si admite,
     * **extiende su retención de aforo** y consume un intento del limitador.
     *
     * Dos diferencias deliberadas con {@see admitReservation()}:
     *  - **no aplica el tope de pendientes**: un reintento no crea aforo nuevo, reusa la plaza que
     *    ese mismo pedido ya retiene y que ya cuenta en ese tope. Aplicarlo dejaba sin poder pagar
     *    justo a quien más pedidos pendientes tenía;
     *  - **extiende el hold con un UPDATE atómico condicionado** (`PAY-04`): comprobar «pendiente y
     *    no vencida» y extender en sentencias separadas resucita un hold ya cruzado sin recontar
     *    aforo, que es el hallazgo L2 del origen.
     */
    public function admitPaymentRetry(int $userId, string $orderCode): RetryAdmission;
}
