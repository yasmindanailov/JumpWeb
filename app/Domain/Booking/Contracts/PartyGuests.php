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

    /**
     * **Los niños que han dicho que VIENEN, de varias reservas y en UN SOLO VIAJE** (T6·4, §4.8).
     *
     * ⚠️⚠️ **Por lotes porque la PUERTA tiene presupuesto** (§7.2·R16): `GateProfileTest` no sube de 28
     * consultas, y una lectura por reserva —o peor, por niño— lo reventaría el día que un titular
     * tenga dos fiestas. Quien llama pasa todas las reservas de hoy y recibe el mapa entero.
     *
     * ⚠️ Devuelve **lo que Booking sabe y nada más**: el nombre, la clave con la que cruzarlo con la
     * ficha del formulario, si el padre dijo que viene un adulto y si la respuesta sigue por repasar.
     * **El estado de puerta lo compone Identity**, que es quien sabe de firmas (§4.5·10) — la misma
     * frontera y la misma razón que {@see committedReplyIdsIn}.
     *
     * ⚠️ `key` es la clave de ADOPCIÓN cuando la hay: una respuesta adoptada ya es una ficha del
     * anfitrión, y es por esa clave por la que las dos mitades se emparejan.
     *
     * @param  list<int>  $reservationIds
     * @return array<int, list<array{reply_id: int, name: string, key: string, companion: string|null, pending: bool}>>
     */
    public function partyGuestsIn(array $reservationIds): array;

    /**
     * **La plaza de QUIEN CUMPLE**: 1 si la reserva la tiene sellada, 0 si no (F3a de `specs/fiesta-sistema-nuevo.md`
     * §4.8, `[DECIDIDO owner]` `#747`).
     *
     * ⚠️ Quien cumple ocupa una plaza que ya tiene dueño, como un «sí»: sin ella en la cuenta, una reserva de 10 con 9
     * «sí» dejaría firmar a un décimo invitado y el suelo dejaría bajar a 9, y el recorte se llevaría a un confirmado.
     * Si el anfitrión además asignó a su hijo como menor a cargo, cuenta dos veces: el suelo sale alto, el lado seguro.
     */
    public function honoreeSeatsIn(int $reservationId): int;
}
