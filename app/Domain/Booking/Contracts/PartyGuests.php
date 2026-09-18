<?php

namespace App\Domain\Booking\Contracts;

/**
 * **Los niños que han CONFIRMADO por la invitación digital**
 * (`specs/celebracion-e-invitacion.md` §4.4 y §4.5·8; `DECISIONES #576`).
 *
 * ⚠️⚠️ **Existe por una frontera, y devuelve IDS en vez de una cifra a propósito.** El suelo de plazas
 * con dueño (V4) es *menores a cargo asignados + justificantes firmados + «sí» pendientes **sin firma
 * atada***, y esas tres cosas no las sabe nadie solo: las respuestas son de **Booking** y las firmas de
 * **Identity**, que no pueden mirarse en ese sentido (`ModuleBoundariesTest`).
 *
 * ▶ La salida es la misma que `#401` encontró para `ReservationPlacesTaken`: **Booking publica lo suyo
 * y quien conoce las dos mitades hace la resta** — `Identity\Services\GuardianPlaces`, el único sitio
 * donde coexisten. Publicar aquí una cifra ya restada obligaría a Booking a preguntar por las firmas,
 * que es justo la flecha prohibida.
 *
 * ⚠️ «Vivas» significa: **«sí»** (un «no» nunca ocupa), **no descartadas** por el anfitrión y **una por
 * niño** —distintas por `child_key`—, porque un nombre repetido se acepta en silencio (V6) y no puede
 * ocupar dos plazas.
 */
interface PartyGuests
{
    /**
     * Los ids de las respuestas «sí» VIVAS de esa reserva, una por niño.
     *
     * Quien llama decide qué hacer con ellas: `GuardianPlaces` descuenta las que ya tienen justificante
     * atado —esa plaza ya la cuenta el justificante— y suma el resto.
     *
     * @return list<int>
     */
    public function committedReplyIdsIn(int $reservationId): array;

    /**
     * ¿Este id es una respuesta «sí» viva **de esa reserva**?
     *
     * ⚠️ Lo pregunta el FIRMADOR antes de aplicar la excepción de plaza (§4.5·7): una firma atada a un
     * «sí» no descuenta plaza porque esa plaza ya tiene dueño. Se comprueba contra la reserva y no solo
     * por id, o un enlace de otra fiesta serviría para saltarse el tope de ésta.
     */
    public function isCommittedReply(int $replyId, int $reservationId): bool;
}
