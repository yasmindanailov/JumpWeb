<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\PendingWork;
use App\Domain\Booking\Contracts\ReservationPlacesTaken;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;

/**
 * **QUÉ LE QUEDA POR HACER a una reserva antes de su visita** (T7·2a,
 * `specs/celebracion-e-invitacion.md` §4.9; `DECISIONES #714`).
 *
 * Existe para el **aviso de la víspera**, que solo se manda **si queda algo** — pero devuelve hechos
 * y no frases a propósito ({@see PendingWork}): el panel y la app hacen la misma pregunta.
 *
 * ## Por qué no mira a Identity
 *
 * Las plazas de menor sin resolver necesitan las firmas, que son de **Identity**, y Booking no puede
 * mirar allí (`ModuleBoundariesTest`). Se pregunta por el contrato {@see ReservationPlacesTaken}, que
 * **ya existía desde `#444`** y que implementa `Identity\Services\GuardianPlaces` —el único sitio
 * donde las dos mitades coexisten—. No hizo falta contrato nuevo: se buscó antes de escribirlo.
 *
 * ## Lo que NO cuenta, y por qué
 *
 * ⚠️ **Una reserva cancelada o ya celebrada no tiene nada pendiente**, aunque sus cifras digan que
 * sí: pedirle a alguien que rellene las fichas de una fiesta que ya pasó es el peor correo posible.
 * Se corta aquí y no en quien avisa, porque quien avisa serán tres sitios.
 *
 * ⚠️ **Una devolución pendiente no es trabajo del cliente**: el saldo solo cuenta cuando el libro
 * dice `pay_at_park`. El resto de clases o no le piden nada o no afirman ningún saldo.
 */
final class PendingBeforeVisit
{
    public function __construct(
        private ReservationPlacesTaken $places,
        private PartyInvitations $invitations,
    ) {}

    public function forReservation(OrderItem $reservation): PendingWork
    {
        $type = $reservation->ticketType;

        // Cancelada o celebrada: nada que pedir. Es la misma guarda que el post-form pone en sus dos
        // puertas, y por la misma razón — lo que ya pasó no se rellena.
        if ($type === null || $reservation->isCancelled() || $reservation->isFinishedInPractice()) {
            return new PendingWork(0, 0, 0, 0, 0);
        }

        // ⚠️ `guestFormProgress()` ya descuenta las fichas con una edad SIN PRODUCTO (`#284` D6): la
        // cifra del aviso es exactamente la que el cliente ve en su pantalla. Recontar aquí habría
        // dado un «8 de 8» donde la pantalla dice «7 de 8», que es cómo se pierde la confianza.
        $progress = $reservation->guestFormProgress();

        return new PendingWork(
            guestsDone: (int) $progress['done'],
            guestsTotal: (int) $progress['total'],
            repliesToReview: $this->repliesToReview($reservation, $type),
            minorsUnresolved: $this->minorsUnresolved($reservation, $type),
            balanceAtParkCents: $this->balanceAtPark($reservation),
        );
    }

    /**
     * Las respuestas por repasar, **solo si el producto ofrece invitación**.
     *
     * ⚠️ Se pregunta al PRODUCTO y no se materializa la invitación ({@see PartyInvitations::summaryFor}
     * lee respuestas, no crea nada): esto corre en un comando programado, y una lectura que escribiera
     * crearía filas para todas las reservas del día siguiente.
     */
    private function repliesToReview(OrderItem $reservation, TicketType $type): int
    {
        return $type->offersGuestInvitation()
            ? (int) $this->invitations->summaryFor($reservation)['pending']
            : 0;
    }

    /**
     * Plazas de menor sin resolver = cantidad − las que ya tienen dueño.
     *
     * ⚠️⚠️ **Solo si el producto pide justificante.** Con `guardian_authorization = none` no hay
     * menores que resolver **por diseño**, y la resta daría la cantidad entera: el aviso le diría a
     * un cliente que le faltan 20 justificantes que nadie le ha pedido nunca.
     */
    private function minorsUnresolved(OrderItem $reservation, TicketType $type): int
    {
        if ($type->guardianMode() === TicketType::GUARDIAN_NONE) {
            return 0;
        }

        return max(0, (int) $reservation->quantity - $this->places->takenIn((int) $reservation->getKey()));
    }

    /**
     * Lo que queda por pagar en el parque, en céntimos.
     *
     * ⚠️ El libro de **esta reserva**, no el del pedido: un pedido con dos fiestas tiene dos avisos y
     * cada uno dice lo suyo. `forReservation()` compone el pedido entero para evaluar la consistencia
     * —es lectura pura, sin consultas— y acota las líneas a las de la reserva.
     */
    private function balanceAtPark(OrderItem $reservation): int
    {
        $order = $reservation->order;
        if ($order === null) {
            return 0;
        }

        $balance = OrderBook::forReservation($order, $reservation)->balance;

        return $balance->kind === Balance::KIND_PAY_AT_PARK ? max(0, $balance->cents) : 0;
    }
}
