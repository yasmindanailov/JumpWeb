<?php

namespace App\Domain\Booking\Contracts;

/**
 * **Un pedido, visto por el subsistema del JUSTIFICANTE de un menor invitado**
 * (`docs/specs/waiver-por-reserva.md` §4.6, §4.7; `DECISIONES #328`).
 *
 * Existe por la misma regla que {@see CheckoutLines} y {@see CustomerReservations}: consultar datos
 * de Booking desde otro módulo exige contrato. Identity necesita saber si el pedido está pagado,
 * cuánta gente se compró, si la visita ya pasó y cuándo caduca el enlace — y **no puede importar
 * `Order` ni `OrderItem`**.
 *
 * ⚠️ **No recibe el titular, a diferencia de `CheckoutLines`, y es deliberado**: quien abre este
 * enlace es un adulto **sin cuenta**, así que no hay titular contra el que acotar. Lo que autoriza el
 * acceso es la firma HMAC de la URL, y eso lo decide la capa de entrega
 * (`Http\Concerns\AuthorizesGuardianAuthorization`) ANTES de llegar aquí. Por eso este contrato no
 * devuelve NADA que no pueda ver quien tenga el enlace: ni importes, ni productos, ni nombres.
 */
interface AuthorizableOrders
{
    /** `null` si no existe ningún pedido con ese identificador. */
    public function find(int $orderId): ?AuthorizableOrder;
}
