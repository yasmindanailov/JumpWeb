<?php

namespace App\Domain\Booking\Contracts;

/**
 * Fase 6 · subsistema A — las reservas de un titular en una VENTANA de días, para la ficha de puerta
 * (`docs/specs/identidad-qr-puerta.md` §4.6, §9.2 A·3). Es el contrato por el que Identity
 * (`GateProfile`) le pregunta a Booking sin verlo (`ModuleBoundariesTest`: Identity → `Booking\Contracts`).
 *
 * Solo reservas de pedidos PAGADOS, principales (no complementos) y no canceladas, con franja dentro
 * de `[$fromDate, $toDate]` (ambos `Y-m-d`, inclusive), en orden de fecha y hora.
 */
interface GateReservations
{
    /**
     * @return list<GateReservation>
     */
    public function forHolder(int $userId, string $fromDate, string $toDate): array;
}
