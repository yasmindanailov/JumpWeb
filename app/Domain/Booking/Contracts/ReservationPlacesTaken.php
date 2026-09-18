<?php

namespace App\Domain\Booking\Contracts;

/**
 * **Cuántas plazas de una reserva YA TIENEN DUEÑO** (`specs/invitados-en-post-form.md` §4.4,
 * `DECISIONES #444`): menores a cargo asignados + justificantes de menores invitados firmados **+ los
 * «sí» de la invitación digital que todavía no tienen justificante** (V4 de
 * `specs/celebracion-e-invitacion.md` §4.5·8, `DECISIONES #576`).
 *
 * ▶ **El tercer sumando cambió el tope del justificante suelto**, y queda dicho aquí porque es donde se
 * busca: una reserva con «sí» pendientes ofrece menos plazas libres al firmante que llega por su cuenta.
 * Es deliberado — esas plazas tienen dueño— y el lado por el que se equivoca es el seguro.
 *
 * ⚠️⚠️ **Existe por una frontera, no por gusto.** La cantidad de la reserva la sabe Booking y los
 * menores los sabe Identity, y **Booking no puede mirar a Identity** (`ModuleBoundariesTest`). Por eso
 * `Identity\Services\GuardianPlaces` —el único sitio donde las dos cifras coexisten, creado en
 * `#401` por ese mismo motivo— implementa este contrato, y el binding vive en el composition root.
 *
 * ▶ **Para qué lo necesita Booking**: es uno de los DOS suelos de una bajada de invitados, y el otro
 * es el `min_qty` del pack. Sin él, bajar la cantidad por debajo de lo ya asignado deja la hoja de
 * sala imprimiendo **plazas negativas** — reproducido: una línea de cantidad 1 con 3 menores
 * asignados calcula **−2** (ficha viva en `DEUDA.md`).
 *
 * ⚠️ Devuelve un HECHO, no una estimación: esas plazas tienen nombre. Es la misma distinción que
 * `GuardianPlaces` hace con los ADULTOS, que **no** se restan porque su composición es incognoscible.
 */
interface ReservationPlacesTaken
{
    /** Plazas de esa reserva que ya tienen dueño. `0` si no hay ninguna. */
    public function takenIn(int $reservationId): int;
}
