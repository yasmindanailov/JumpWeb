<?php

namespace App\Domain\Booking\Contracts;

/**
 * La DISPONIBILIDAD ofrecida de un producto (Fase 3 · paso 4b, `docs/specs/api-v1.md` §4.4).
 *
 * Responde a «¿qué días y a qué horas se puede reservar esto, y cuánto cabe?» sobre `SlotOffer`, que
 * es la fuente ÚNICA de oferta web↔panel (`AFORO-02`). Este contrato **no inventa reglas de oferta**:
 * las pide. Lo que sí trae de la capa de UI —donde estaba— es la derivación de la CESTA a ocupantes
 * provisionales, que vivía en `Livewire\Tickets\Purchase::cartOccupants()`/`cartPackOccupants()`.
 *
 * **Por qué la disponibilidad lleva la cesta.** `SlotOffer::offerableTimes()` descuenta los
 * ocupantes que la propia cesta del cliente ya está reteniendo: sin ellos, una segunda línea sobre
 * la misma franja vería las plazas que su primera línea ya ocupa, y el checkout la rechazaría
 * después. Un endpoint de disponibilidad sin cesta rompería `AFORO-02` en la práctica aunque
 * llamara a la fuente correcta — es el hallazgo que corrigió la v2 del spec (§8.3).
 *
 * **Qué NO hace:** no reserva ni retiene nada. Lo que dice es cierto en el instante en que se dice;
 * quien garantiza que la plaza sigue ahí es `OrderCreator` bajo lock (`AFORO-01`), y por eso el
 * checkout vuelve a comprobarlo todo.
 *
 * La cesta llega en la forma canónica de `Booking\Services\Cart::sanitize()` —la misma que consumen
 * `OrderCreator` y la tarificación—; se cita en prosa, y no con `{@see}`, porque una anotación
 * resoluble haría que un contrato importara un servicio.
 *
 * Implementación actual: `App\Domain\Booking\Services\AvailabilityReader` (bind en
 * `BookingServiceProvider`).
 */
interface AvailabilityOffer
{
    /**
     * Días con alguna franja ofrecible para el producto, en orden, con el precio de cada uno.
     *
     * **No lleva cesta a propósito.** Un día se ofrece si tiene franjas vivas; que quepa o no la
     * cantidad que el cliente quiere es cosa de la franja, y decidirlo aquí obligaría a evaluar el
     * cupo de todas las horas de todos los días del horizonte para pintar un calendario.
     *
     * Vacío si el producto no está en el catálogo (no existe, no se vende o su zona no opera): las
     * tres razones dan la misma respuesta, igual que en `ProductCatalog::product()`.
     *
     * @return list<OfferedDate>
     */
    public function dates(int $productId): array;

    /**
     * Horas ofrecibles de ese día, con su cupo ya descontada la cesta.
     *
     * @param  array<mixed>  $cart  cesta en bruto del cliente; sus líneas de esta zona y día restan cupo
     * @return list<OfferedTime>
     */
    public function times(int $productId, string $date, array $cart = []): array;

    /**
     * Cuánto se puede contratar de este producto en una franja concreta, descontada la cesta.
     *
     * Existe además de {@see times()} porque responde de una franja CONCRETA aunque no esté entre
     * las ofrecidas: un pack cuyo cupo libre no llega a su mínimo de invitados no se ofrece, pero si
     * el cliente ya lo tenía elegido hay que poder decirle cuánto queda en vez de fingir que la hora
     * no existe. Devuelve 0 si la franja no existe, está cerrada o no cabe.
     *
     * @param  array<mixed>  $cart
     */
    public function maxQuantity(int $productId, string $date, string $time, array $cart = []): int;
}
