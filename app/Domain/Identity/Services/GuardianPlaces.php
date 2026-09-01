<?php

namespace App\Domain\Identity\Services;

use App\Domain\Booking\Contracts\AuthorizableReservation;
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
 * La cuenta es de una línea y de tres sumandos:
 *
 *     libres = cantidad − menores a cargo YA asignados − justificantes YA firmados
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
 */
final class GuardianPlaces
{
    /** Plazas de la reserva que todavía podrían recibir un menor invitado. Nunca negativo. */
    public function freeIn(AuthorizableReservation $reservation): int
    {
        return max(0, $reservation->quantity - $this->takenIn($reservation->reservationId));
    }

    /** Lo que ya tiene dueño en esa reserva: menores a cargo asignados + justificantes firmados. */
    public function takenIn(int $reservationId): int
    {
        return $this->assignedDependents($reservationId) + $this->authorizations($reservationId);
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
