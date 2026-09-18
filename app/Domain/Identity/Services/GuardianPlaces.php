<?php

namespace App\Domain\Identity\Services;

use App\Domain\Booking\Contracts\AuthorizableReservation;
use App\Domain\Booking\Contracts\PartyGuests;
use App\Domain\Booking\Contracts\ReservationPlacesTaken;
use App\Domain\Identity\Models\DependentAssignment;
use App\Domain\Identity\Models\GuardianAuthorization;

/**
 * **Cuántos menores INVITADOS caben todavía en una reserva**
 * (`docs/specs/waiver-por-reserva.md` §13.3).
 *
 * ❗❗ **Lo encontró el owner con un pedido real**: compró UNA entrada, se la asignó a un menor a
 * cargo suyo y además marcó que venía un menor invitado. La pantalla decía *«0 justificantes
 * firmados · 3 plazas»* de una línea cuya única plaza ya tenía dueño.
 *
 * La cuenta es de una línea, y desde `#576` de cuatro sumandos:
 *
 *     libres = cantidad − menores a cargo YA asignados − justificantes YA firmados
 *                       − «sí» de la invitación digital que aún no han firmado
 *
 * ⚠️⚠️ **Vive en Identity y no en el contrato de Booking, y no es una preferencia**: la cantidad la
 * sabe Booking y los menores a cargo los sabe Identity — **Booking no puede mirar a Identity**
 * (`ModuleBoundariesTest`). El único sitio donde las dos cifras coexisten es aquí.
 *
 * ⚠️ **Una plaza de un menor a cargo NO es una plaza que pueda ocupar un invitado**, y eso es un
 * HECHO, no una estimación — a diferencia de «cuántos de los que vienen son menores», que §4.10
 * declara incognoscible y por eso no se inventa ningún denominador. Aquí sí se sabe: esa entrada
 * tiene nombre.
 *
 * ⚠️ Los ADULTOS no se restan: una entrada sin asignar puede ser un adulto o un menor invitado, y
 * suponer lo primero cerraría la puerta a quien tiene derecho a firmar. **La cota es superior a
 * propósito**: el tope existe para que nadie autorice a más gente de la que se ha comprado, no para
 * adivinar la composición del grupo.
 *
 * ▶ **Y desde `#444` es además el implementador de {@see ReservationPlacesTaken}**, el contrato por el
 * que Booking pregunta lo mismo sin poder mirar a Identity: es uno de los dos suelos de una bajada de
 * invitados desde el post-formulario. La frontera no cambia —sigue siendo Identity quien sabe de
 * menores—; lo que se publica es la RESPUESTA, no la consulta.
 */
final class GuardianPlaces implements ReservationPlacesTaken
{
    public function __construct(private readonly PartyGuests $guests) {}

    /** Plazas de la reserva que todavía podrían recibir un menor invitado. Nunca negativo. */
    public function freeIn(AuthorizableReservation $reservation): int
    {
        return max(0, $reservation->quantity - $this->takenIn($reservation->reservationId));
    }

    /**
     * Lo que ya tiene dueño en esa reserva: menores a cargo asignados + justificantes firmados **+ los
     * «sí» de la invitación digital que todavía no tienen justificante** (V4,
     * `specs/celebracion-e-invitacion.md` §4.5·8, `DECISIONES #576`).
     *
     * ⚠️ El tercer sumando entra para que **el suelo de `#444` proteja a un niño que confirmó**: sin él,
     * el anfitrión podría bajar los invitados por debajo de los «sí» que ya tiene y dejar fuera a quien
     * le había dicho que venía, sin que nada avisara.
     */
    public function takenIn(int $reservationId): int
    {
        return $this->assignedDependents($reservationId)
            + $this->authorizations($reservationId)
            + $this->committedGuests($reservationId);
    }

    /**
     * Los «sí» vivos que **todavía no tienen justificante atado**.
     *
     * ⚠️⚠️ **La resta se hace AQUÍ y no en Booking, y es la razón de que el contrato devuelva ids.** Las
     * respuestas las sabe Booking y las firmas las sabe Identity: éste es el único sitio donde las dos
     * mitades coexisten — exactamente el motivo por el que esta clase existe desde `#401`. Si no se
     * restaran, un niño que dijo «sí» **y** firmó ocuparía dos plazas del suelo.
     *
     * ⚠️ **Lo que sí cuenta dos veces, declarado** (§4.5·8): un justificante SUELTO de un niño que
     * además dijo «sí» — porque su firma no viene atada a la respuesta y no hay forma de saber que son
     * el mismo niño sin comparar nombres, que es justo lo que `#328` decidió no hacer. El suelo sale
     * alto, que es el lado seguro: protege de más, nunca de menos.
     */
    public function committedGuests(int $reservationId): int
    {
        $ids = $this->guests->committedReplyIdsIn($reservationId);
        if ($ids === []) {
            return 0;
        }

        $signed = GuardianAuthorization::query()
            ->where('order_item_id', $reservationId)
            ->whereIn('invitation_reply_id', $ids)
            ->pluck('invitation_reply_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        return count(array_diff($ids, $signed));
    }

    /**
     * Cuántas plazas de esta reserva están asignadas a menores a cargo del titular.
     *
     * ⚠️ Se acota a la CANTIDAD de la línea igual que `DependentAssigner::forOrderItems()`: si la
     * línea bajó de unidades desde el panel, las asignaciones sobrantes ya no ocupan nada. Sin ese
     * tope, bajar una línea podría hacer que las plazas libres salieran negativas y el `max(0, …)`
     * escondería la incoherencia en vez de que la cuenta sea correcta.
     */
    public function assignedDependents(int $reservationId): int
    {
        return DependentAssignment::query()->where('order_item_id', $reservationId)->count();
    }

    /** Justificantes ya escritos para esa reserva. */
    public function authorizations(int $reservationId): int
    {
        return GuardianAuthorization::query()->where('order_item_id', $reservationId)->count();
    }
}
