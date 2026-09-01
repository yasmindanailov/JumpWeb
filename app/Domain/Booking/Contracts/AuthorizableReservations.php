<?php

namespace App\Domain\Booking\Contracts;

/**
 * **Las RESERVAS que pueden llevar justificante de un menor invitado**
 * (`docs/specs/waiver-por-reserva.md` §13; sustituye a `AuthorizableOrders`).
 *
 * Existe por la misma regla que {@see CheckoutLines} y {@see CustomerReservations}: consultar datos de
 * Booking desde otro módulo exige contrato. Identity necesita saber a qué visita va el menor, si está
 * pagada, cuánta gente cabe en ESA línea, si ya pasó y cuándo caduca el enlace — y **no puede importar
 * `Order` ni `OrderItem`**.
 *
 * ⚠️ **No recibe titular, a diferencia de `CheckoutLines`, y es deliberado**: quien abre este enlace es
 * un adulto **sin cuenta**, así que no hay titular contra el que acotar. Lo que autoriza el acceso es
 * la firma HMAC de la URL, y eso lo decide la capa de entrega
 * (`Http\Concerns\AuthorizesGuardianAuthorization`) ANTES de llegar aquí.
 */
interface AuthorizableReservations
{
    /** `null` si esa línea no existe, es un complemento o está cancelada. */
    public function find(int $reservationId): ?AuthorizableReservation;

    /**
     * Las reservas de un pedido que NACIERON marcadas, en el orden de la compra.
     *
     * Es lo que necesitan las superficies que hablan del pedido entero —el correo al pagar, la ficha
     * del panel, la cuenta del cliente—: cuántos enlaces hay que repartir y cuáles.
     *
     * @return list<AuthorizableReservation>
     */
    public function markedForOrder(int $orderId): array;
}
