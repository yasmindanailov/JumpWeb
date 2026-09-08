<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\ProductAddon;
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

    // ─── La hora extra de un PACK (§10): EXTENDER, que no es ocupar ──────────────────────────

    /**
     * ¿Este complemento se puede VENDER como extensor de estancia? El interruptor declarado + su
     * cinturón, exactamente como {@see sellableOccupant()} para el ocupante.
     *
     * `false` con el interruptor puesto significa configuración ROTA (metida por
     * `Query\Builder::update()`, que los guards de Eloquent no ven): el que llama tiene que
     * RECHAZAR la venta. **Jamás degradar a complemento neutro**, que sería vender una hora extra
     * que no ocupa — el modo de fallo caro, porque no falla nada: solo se sobrevende la sala.
     */
    public static function sellableStayExtension(TicketType $addon): bool
    {
        return $addon->extendsParentStay() && $addon->hasSaneStayExtensionConfig();
    }

    /**
     * **Los BLOQUES de tiempo que compra una cantidad** (§11.5, `#443`).
     *
     * ❗❗❗ **Ésta es LA regla que hace posible cobrar la hora extra por invitado, y vive aquí sola.**
     * §10.3.1 declaró que la cantidad de un extensor «son bloques de tiempo» y prohibió `per_guest`
     * por eso: con `extraMinutes = cantidad × duración`, una fiesta de 15 habría alargado la sala
     * **900 minutos**. Lo que cambia no es el guard, es de dónde salen los minutos —
     *
     *  - enganche **por-invitado**: la cantidad son PERSONAS y los bloques son **1**. *Una hora es
     *    una hora, la compren 8 invitados o 20*; lo que escala con los invitados es el PRECIO, y eso
     *    lo resuelve `chargedSubtotalCents()` sin enterarse de nada (`cantidad × unit_price`).
     *  - enganche **de cantidad fija**: la cantidad SON los bloques, como siempre (§10.3.1).
     *
     * ⚠️ Es lo único que separa las dos lecturas de `per_guest`, que hoy dice DOS cosas a la vez:
     * *cuántas unidades hay* y *cuánto se cobra*. Para un extensor solo la segunda tiene sentido.
     */
    public static function blocksFor(ProductAddon $pivot, int $quantity): int
    {
        return self::blocksForUnit($pivot->quantityUnit(), $quantity);
    }

    /**
     * La MISMA regla sobre una unidad ya resuelta, venga de donde venga (`#448`).
     *
     * ⚠️⚠️ **Existe porque la unidad tiene DOS orígenes legítimos y no se pueden confundir**: la
     * OFERTA la saca del pivote de hoy ({@see blocksFor}) y una línea YA VENDIDA, de su sello
     * ({@see AddonResolver::soldQuantityUnit}). *La regla es una; de dónde sale el dato, no.* Sin
     * este corte habría que elegir un origen para los dos —y cualquiera de los dos elegidos es un
     * defecto: con el catálogo, cambiarlo re-alarga fiestas vendidas; con el sello, el escaparate
     * se congela—.
     */
    public static function blocksForUnit(string $unit, int $quantity): int
    {
        return $unit === ProductAddon::MODE_PER_GUEST ? 1 : max(0, $quantity);
    }

    /** Minutos que alarga una línea VENDIDA, con la unidad que ella misma declara (`#448`). */
    public static function minutesForUnit(TicketType $addon, string $unit, int $quantity): int
    {
        return self::minutesForBlocks($addon, self::blocksForUnit($unit, $quantity));
    }

    /**
     * El máximo de BLOQUES que este enganche puede vender: `1` por-invitado (la cantidad no la elige
     * nadie), y su `max_qty` si la cantidad es fija.
     *
     * Existe para {@see AddonOfferReader::stayExtensionCaps()}, que barre bloques 1..N preguntando
     * si la fiesta cabe alargada. Sin este tope, un extensor por-invitado repetiría el mismo cálculo
     * `max_qty` veces y ofrecería bloques que nadie puede comprar (`#443` · A2).
     */
    public static function maxBlocks(ProductAddon $pivot): int
    {
        return $pivot->isPerGuest() ? 1 : max(1, (int) ($pivot->max_qty ?? 1));
    }

    /** Minutos que alargan N BLOQUES de este complemento. La aritmética, sin la regla. */
    public static function minutesForBlocks(TicketType $addon, int $blocks): int
    {
        return max(0, $blocks) * (int) ($addon->duration_min ?? 0);
    }

    /**
     * Minutos que esta compra alarga la fiesta: `bloques(cantidad) × duración del bloque`.
     *
     * ⚠️⚠️ **El pivote es OBLIGATORIO y no tiene valor por defecto, a propósito.** Son CINCO los
     * sitios que derivan minutos (el resolutor, la cesta, la oferta y el editor dos veces), y con un
     * `?ProductAddon $pivot = null` el que se olvidara **multiplicaría por los invitados en
     * silencio** — la lección de `#329` («un parámetro con valor por defecto no avisa de que hacía
     * falta», 140,00 € de desfase) aplicada a algo que no es dinero, sino AFORO. Con el parámetro
     * obligatorio, el que falte no compila.
     *
     * ⚠️ Sigue siendo el hermano de {@see seats()} y no una variante suya: dos unidades distintas
     * para dos preguntas distintas. Mezclarlas es el defecto (b) de §10.1 —la línea que dice «1
     * persona» donde hay veinte—.
     */
    public static function extraMinutes(TicketType $addon, ProductAddon $pivot, int $quantity): int
    {
        return self::minutesForBlocks($addon, self::blocksFor($pivot, $quantity));
    }
}
