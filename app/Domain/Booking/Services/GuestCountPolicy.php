<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\GuestCountChange;
use App\Domain\Booking\Contracts\ReservationPlacesTaken;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Support\Carbon;

/**
 * **Hasta dónde puede mover el cliente los invitados de su reserva, y hasta cuándo**
 * (`specs/invitados-en-post-form.md` §4.3–§4.5, `DECISIONES #444`).
 *
 * ❗❗ **Existe para que la PANTALLA y el ESCRITOR no puedan divergir.** La pantalla pinta el control
 * con estos mismos límites y el mismo plazo; `GuestCountAdjuster` los vuelve a preguntar **bajo el
 * lock**. Si cada uno tuviera su copia, el cliente vería un número que el servidor rechaza — que es
 * exactamente la clase de divergencia que `AFORO-02` existe para evitar en la oferta.
 *
 * ⚠️ **Y la pantalla NO es la autoridad** (`SEC-04`): esto decide qué se OFRECE; quien decide qué se
 * ESCRIBE es el adjuster, con la fila bloqueada y las franjas de la zona/día en la mano.
 */
final class GuestCountPolicy
{
    /** Horas antes del inicio de la fiesta hasta las que el cliente puede mover sus invitados. */
    public const SETTING_CUTOFF_HOURS = 'packs.guest_count_cutoff_hours';

    /**
     * `[DECIDIDO owner, 2026-09-07]`: *«hasta el plazo que pone el parte de celebración para poder
     * editarlo, vamos a hacerlo un día antes mínimo»*. Un día es el SUELO del producto, no un número
     * elegido al azar: por debajo, la cocina y la sala ya están preparadas para un número.
     */
    public const DEFAULT_CUTOFF_HOURS = 24;

    public function __construct(private ReservationPlacesTaken $places) {}

    /** ¿Esta reserva admite hoy que su titular toque los invitados? */
    public function isEditableBy(OrderItem $item): bool
    {
        return $this->lockedReason($item) === null;
    }

    /**
     * Por qué NO se puede mover ahora mismo, o `null` si sí se puede.
     *
     * ⚠️ Devuelve el motivo y no un booleano porque **la pantalla tiene que decirlo**: un control que
     * desaparece sin explicación es cómo el hueco original —el cliente que manda 12 fichas para una
     * línea de 10 y pierde dos en silencio— llegó a estar cinco meses sin que nadie lo viera.
     */
    public function lockedReason(OrderItem $item): ?string
    {
        if (! $this->isOpenFor($item)) {
            return GuestCountChange::REASON_CLOSED;
        }

        return $this->isWithinWindow($item) ? null : GuestCountChange::REASON_CUTOFF;
    }

    /** La reserva sigue viva y su formulario abierto: pagada, con invitados, ni cancelada ni celebrada. */
    public function isOpenFor(OrderItem $item): bool
    {
        return $item->acceptsGuestForm()
            && ! $item->isCancelled()
            && ! $item->isFinishedInPractice()
            && $item->slot !== null
            && $item->ticketType !== null;
    }

    /**
     * ¿Queda plazo? Se mide con **`DisplayTime::now()` contra la hora de PARED de la franja**, que es
     * como se guardan (lo demuestra que `SlotOffer::passesIntradayFloor` las compare así).
     *
     * ⚠️ **No se hereda `isFinishedInPractice()`, y el motivo NO es que aquel esté roto** —`#426` lo
     * arregló: hoy compara inicio + duración efectiva en hora del parque—: es que responde a **otra
     * pregunta**. «¿Ya terminó?» y «¿queda un día?» no son la misma, y colgar la segunda de la
     * primera haría que el plazo cambiara cada vez que alguien tocara la duración de un producto.
     */
    public function isWithinWindow(OrderItem $item): bool
    {
        $deadline = $this->deadlineFor($item);

        return $deadline !== null && DisplayTime::now()->lt($deadline);
    }

    /** El instante EXACTO del corte, en la zona del parque. `null` si la reserva no tiene franja. */
    public function deadlineFor(OrderItem $item): ?Carbon
    {
        $slot = $item->slot;
        if ($slot === null || $slot->date === null || $slot->start_time === null) {
            return null;
        }

        return Carbon::parse(
            $slot->date->format('Y-m-d').' '.$slot->start_time,
            DisplayTime::timezone(),
        )->subHours($this->cutoffHours());
    }

    /** Las horas de corte configuradas. Un valor no numérico o negativo cae al suelo del producto. */
    public function cutoffHours(): int
    {
        $raw = Setting::value(self::SETTING_CUTOFF_HOURS);

        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return self::DEFAULT_CUTOFF_HOURS;
        }

        return max(0, (int) $raw);
    }

    /** El TECHO: el máximo del producto (`[DECIDIDO owner]`). `null` = solo lo limita el aforo. */
    public function maxFor(OrderItem $item): ?int
    {
        $max = $item->ticketType?->max_qty;

        return $max === null ? null : max(1, (int) $max);
    }

    /**
     * El suelo CONTRATABLE del producto.
     *
     * ⚠️ Bajar de aquí existe, pero es una excepción del OPERADOR con permiso propio y rastro
     * (`orders.edit_item_below_minimum`, D7 de `cumple-mixto.md` §23): **al cliente no se le da**.
     */
    public function contractableFloorFor(OrderItem $item): int
    {
        return max(1, (int) ($item->ticketType?->contractableMinimum() ?? 1));
    }

    /**
     * El suelo de lo que YA TIENE DUEÑO: menores a cargo asignados + justificantes firmados.
     *
     * ⚠️ Es un motivo de bloqueo DISTINTO del anterior y no se funden: el remedio no es el mismo —el
     * mínimo del pack se resuelve llamando al parque; esto, quitando a alguien de la lista—.
     */
    public function assignedFloorFor(OrderItem $item): int
    {
        return max(0, $this->places->takenIn((int) $item->getKey()));
    }

    /** El suelo EFECTIVO para la pantalla: el mayor de los dos. */
    public function floorFor(OrderItem $item): int
    {
        return max($this->contractableFloorFor($item), $this->assignedFloorFor($item));
    }
}
