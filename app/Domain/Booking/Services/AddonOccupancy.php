<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use Illuminate\Support\Collection;

/**
 * La HORA EXTRA (`specs/hora-extra.md`): las reglas de un complemento que OCUPA, en UN sitio.
 *
 * Un complemento con `occupies_after_parent` es un OCUPANTE: su línea hija nace con la franja
 * siguiente al tramo de su padre, sus plazas y la duración de su producto — y con eso
 * `SlotAvailability::occupancyMap()` la cuenta sin tocarse, porque el mapa no filtra por tipo.
 *
 * Aquí viven las tres reglas que NO pueden tener dos copias:
 *
 *  1. **El cinturón de §4.1** ({@see sellableOccupant()}): un ocupante declarado con configuración
 *     rota (sin duración, sin plaza por unidad) **ni se ofrece ni se vende** — jamás degrada a
 *     complemento neutro (sería vender sin ocupar) ni ocupa «hasta el cierre» (que es lo que
 *     `occupancyMap` haría con una duración nula). El guard de `TicketType::booted()` impide esa
 *     fila por Eloquent; este cinturón cubre lo que entra por `Query\Builder::update()`, que los
 *     eventos no ven (el límite documentado de `#299`).
 *
 *  2. **La regla determinista de «la franja siguiente» (§4.6·8)** ({@see childEntryStart()} +
 *     {@see childSlotAmong()}): la franja de la MISMA zona y día cuyo `start_time` es EXACTAMENTE
 *     el fin del tramo del padre — la misma aritmética que el aforo
 *     ({@see SlotAvailability::spanEnd()}), única por `UNIQUE(zone_id, date, start_time)`. Si no
 *     existe (el cierre, un hueco de rejilla, un padre sin duración —borde 7—, un tramo que cruza
 *     medianoche), NO hay hora extra para esa hora: la ausencia se declara invendible, no se
 *     resuelve eligiendo una franja parecida.
 *
 *  3. **Las plazas de la hija** ({@see seats()}): `cantidad × seats_per_unit`, la misma cuenta que
 *     una línea base — la cantidad SON entradas que se quedan (§4.2).
 *
 * ⚠️ Este fichero es AFORO: está en el `CRITICAL_RE` del pre-push (`INVARIANTES §6`).
 */
class AddonOccupancy
{
    /**
     * ¿Este complemento se puede VENDER como ocupante? El interruptor declarado + el cinturón.
     *
     * `false` con el interruptor puesto significa configuración ROTA (metida por la puerta de
     * atrás): el que llama tiene que RECHAZAR la venta, no tratarlo como complemento neutro —
     * distinción que hace {@see TicketType::occupiesAfterParent()} vs este método.
     */
    public static function sellableOccupant(TicketType $addon): bool
    {
        return $addon->occupiesAfterParent() && $addon->hasSaneOccupancyConfig();
    }

    /**
     * Inicio del tramo de la HIJA: el fin EXACTO del tramo del padre, o `null` si el padre no
     * tiene fin propio — un padre de duración nula («ilimitada hasta el cierre», borde 7) no tiene
     * «franja siguiente» de la que hablar.
     *
     * El clamp de `spanEnd` a '24:00:00' colabora: un tramo que cruza medianoche devuelve una hora
     * que jamás casa con un `start_time` real, así que la búsqueda posterior falla hacia invisible.
     */
    public static function childEntryStart(TicketType $parent, string $parentStartTime): ?string
    {
        if ($parent->duration_min === null || (int) $parent->duration_min <= 0) {
            return null;
        }

        return SlotAvailability::spanEnd($parentStartTime, (int) $parent->duration_min);
    }

    /**
     * La franja de la hija dentro de un conjunto de franjas YA CARGADO (p. ej. las bloqueadas por
     * `ZoneDaySlotLock`, que trae la zona/día enteros), o `null` si no existe — la regla del borde 8
     * aplicada sobre filas consistentes con el lock, sin una consulta que leería otro snapshot.
     *
     * @param  Collection<array-key, Slot>  $slots  franjas entre las que buscar (pueden ser de varias zonas/días)
     */
    public static function childSlotAmong(Collection $slots, TicketType $parent, Slot $parentSlot): ?Slot
    {
        $start = self::childEntryStart($parent, (string) $parentSlot->start_time);
        if ($start === null) {
            return null;
        }

        return $slots->first(fn (Slot $slot): bool => (int) $slot->zone_id === (int) $parentSlot->zone_id
            && $slot->date->toDateString() === $parentSlot->date->toDateString()
            && (string) $slot->start_time === $start);
    }

    /** Plazas que la hija se queda: `cantidad × seats_per_unit` — la cantidad SON entradas (§4.2). */
    public static function seats(TicketType $addon, int $quantity): int
    {
        return max(0, $quantity) * (int) ($addon->seats_per_unit ?? 1);
    }
}
