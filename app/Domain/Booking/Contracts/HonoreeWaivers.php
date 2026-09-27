<?php

namespace App\Domain\Booking\Contracts;

/**
 * **¿A quien cumple le falta su descargo?** (F7c de `specs/fiesta-sistema-nuevo.md` §4.13, `[DECIDIDO owner]` `#752`).
 *
 * ⚠️⚠️ **Existe por la frontera de siempre**: la reserva que sella a quien cumple es de Booking y lo que lo cubre —su menor a
 * cargo asignado o su justificante— es de Identity, que Booking no puede mirar (`ModuleBoundariesTest`). Booking declara
 * la pregunta, Identity la implementa (`GuardianPlaces`) y el binding vive en el composition root, como
 * {@see ReservationPlacesTaken} y {@see SignedInvitationReplies}.
 *
 * ▶ **Para qué lo necesita Booking**: el aviso de la víspera (`PendingBeforeVisit`). Su plaza cuenta como ocupada —es
 * suya—, así que las «plazas de menor sin resolver» nunca lo nombraban, y la lista sí dice «Falta · Firmar su descargo».
 * La víspera lo recuerda con la MISMA respuesta que lee la lista.
 */
interface HonoreeWaivers
{
    /**
     * ¿La reserva sella a quien cumple y no lo cubre una prueba con el descargo VIGENTE? `false` si no lo sella o si el
     * descargo no se gestiona dentro (sin modo interno no hay firma que pedir).
     */
    public function honoreeWaiverMissing(int $reservationId): bool;
}
