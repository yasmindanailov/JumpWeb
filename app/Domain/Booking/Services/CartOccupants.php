<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\TicketType;
use Illuminate\Support\Collection;

/**
 * La derivación ÚNICA de los ocupantes PROVISIONALES de plazas que una cesta aporta a una zona y
 * un día (`specs/hora-extra.md` §7·D1, `[DECIDIDO owner, 2026-09-02]`).
 *
 * Hasta la hora extra eran DOS derivaciones gemelas — `OrderCreator::otherOccupants()` (el cobro) y
 * `AvailabilityReader::occupantsOf()` (la oferta: web, API y `CartLineValidator` vía `SlotOffer`) —
 * y ninguna contaba a las líneas HIJAS que ocupan ni a los HERMANOS de la misma línea: tres de los
 * nueve bordes de §4.6 eran el mismo defecto visto desde sitios distintos. Una copia que cuente
 * distinto ofrece horas que el checkout rechaza (`AFORO-02`), así que la cuenta vive AQUÍ y las dos
 * la llaman.
 *
 * **La semántica es la de `occupancyMap`** (lo ALMACENADO), que es la verdad con la que estos
 * provisionales se suman: cuenta cualquier línea con franja y plazas usando la duración de su
 * producto. Dos consecuencias deliberadas:
 *
 *  - **Las líneas de PACK también ocupan plazas** (`qty × seats_per_unit`): sus filas nacen con
 *    `slot_id` y `seats`, así que `occupancyMap` las cuenta en cuanto existen. Aquí las dos
 *    derivaciones DIVERGÍAN — el cobro las contaba y la oferta no — sin morder solo porque los
 *    packs viven en zona propia; la unificación resuelve hacia lo almacenado. (El CUPO de packs es
 *    otro pool: lo lleva {@see packs()}, y del lado del COBRO sigue teniéndolo
 *    `OrderCreator::otherPackOccupants()`, que excluye por índice la línea en validación.)
 *
 *  - **Las hijas que OCUPAN cuentan como ocupantes propios**: franja = la siguiente al tramo del
 *    padre ({@see AddonOccupancy}), plazas = cantidad efectiva × `seats_per_unit` — la MISMA
 *    cantidad que cobraría `AddonResolver` (paridad oferta/cobro). Una hija invendible (config
 *    rota, sin franja siguiente, padre pack) no aporta ocupante: tampoco se podrá comprar.
 *
 * Cada ocupante viaja ETIQUETADO por `(line, addon)` para que el validador pueda excluir EXACTAMENTE
 * al elemento en validación ({@see excluding()}): al validar la línea base se excluye solo ella (sus
 * hijas no pisan su tramo: empiezan donde él acaba), y al validar una hija se excluye solo esa hija
 * — **sus hermanos se quedan dentro**, que es el arreglo del borde 6 («la última plaza vendida dos
 * veces en una sola petición, sin carrera y con el lock puesto»).
 * `occupancyMap` lee solo `entry_start`/`duration_min`/`seats`; las etiquetas extra las ignora.
 *
 * **Los dos POOLS y las cuatro funciones** (`#464`). Las plazas y el cupo de fiestas son aforos
 * distintos, así que la cesta aporta DOS listas: {@see entries()} y {@see packs()}. Las dos son
 * PURAS —reciben los productos ya resueltos— y {@see forCart()} es la que las une para quien tiene
 * una cesta en bruto y ningún producto cargado: **el filtro de qué producto retiene aforo es parte
 * de la derivación**, y copiarlo fuera es la misma divergencia que esta clase existe para cerrar.
 *
 * ⚠️ Este fichero es AFORO: está en el `CRITICAL_RE` del pre-push (`INVARIANTES §6`).
 */
class CartOccupants
{
    /**
     * Los dos grupos de ocupantes que una cesta EN BRUTO aporta a una zona y un día.
     *
     * Es la puerta de los consumidores que solo tienen la cesta: el read-model de disponibilidad
     * (web y API) y la página de «Crear pedido» del panel. **Los dos preguntan lo mismo y por eso
     * preguntan aquí**: hasta `#464` el panel no descontaba su propia cesta y ofrecía horas que su
     * propio checkout habría rechazado (`AFORO-02`).
     *
     * ⚠️ **El SANEADO y el filtro de productos van dentro a propósito.** «Qué es una línea válida»
     * (`Cart::sanitize`) y «qué producto retiene aforo» (vendible, en zona operativa) son parte de
     * la respuesta: una copia que sanee distinto —o que cuente una línea de un producto retirado—
     * ofrece horas que el cobro rechaza. `OrderCreator` no pasa por aquí porque ya trae sus
     * productos resueltos de la transacción: llama a {@see entries()} directamente.
     *
     * @param  array<mixed>  $cart  cesta en bruto; se sanea aquí
     * @return array{entries: list<array{entry_start:string, duration_min:int|null, seats:int, line:int, addon:int|null}>, packs: list<array{start:string, prep_before_min:int, duration_min:int|null, prep_after_min:int, guests:int}>}
     */
    public static function forCart(array $cart, int $zoneId, string $date): array
    {
        $cart = Cart::sanitize($cart);

        if ($cart === []) {
            return ['entries' => [], 'packs' => []];
        }

        $types = self::typesOf($cart);

        return [
            'entries' => self::entries($cart, $types, $zoneId, $date),
            'packs' => self::packs($cart, $types, $zoneId, $date),
        ];
    }

    /**
     * TODOS los ocupantes de plazas que la cesta aporta a esta zona y día, etiquetados.
     *
     * @param  array<int, array{ticket_type_id:int, date:string, time:string, qty:int, addons?:array<int, array{ticket_type_id:int, qty:int}>}>  $cart  saneada (`Cart::sanitize`)
     * @param  Collection<int, TicketType>  $types  productos por id; los BASE con la relación `addons` cargada
     * @return list<array{entry_start:string, duration_min:int|null, seats:int, line:int, addon:int|null}>
     */
    public static function entries(array $cart, Collection $types, int $zoneId, string $date): array
    {
        $occupants = [];

        foreach ($cart as $i => $line) {
            if (($line['date'] ?? null) !== $date) {
                continue;
            }

            /** @var TicketType|null $type */
            $type = $types->get($line['ticket_type_id'] ?? 0);
            if (! $type || (int) $type->zone_id !== $zoneId) {
                continue;
            }

            // ⚠️ La duración es la EFECTIVA: si la línea lleva complementos que extienden la
            // estancia (la hora extra de un pack, §10.3), esta misma línea ocupa más rato. La
            // aritmética es la de `OrderItem::occupiedMinutes()` y la del `SELECT` de los mapas —
            // tres sitios que suman lo mismo porque son la misma regla.
            $occupants[] = [
                'entry_start' => (string) $line['time'],
                'duration_min' => self::effectiveDurationOf($type, $line),
                'seats' => (int) $line['qty'] * (int) ($type->seats_per_unit ?? 1),
                'line' => $i,
                'addon' => null,
            ];

            // Un pack no puede llevar complementos que OCUPEN —una línea hija con franja y plazas
            // propias— (D2 de §7): el pivote lo impide y `AddonResolver` lo rechaza. Lo que sí puede
            // llevar desde §10 son complementos que EXTIENDEN, y ésos no aportan un ocupante nuevo:
            // ya han alargado la duración de la línea base, dos líneas más arriba.
            if ($type->isPack()) {
                continue;
            }

            foreach (self::occupyingChildrenOf($type, $line) as $child) {
                $occupants[] = $child + ['line' => $i];
            }
        }

        return $occupants;
    }

    /**
     * Las FIESTAS provisionales que la cesta aporta al cupo de packs de esta zona y día.
     *
     * Vivía dentro de `AvailabilityReader::occupantsOf()` y sube aquí en `#464` porque el panel
     * necesita la misma cuenta: **dos copias de esto ofrecen fiestas que el cobro rechaza**.
     *
     * ⚠️ **Es OTRO pool, no otra forma de contar lo mismo** (#82): el cupo de fiestas se mide en
     * INVITADOS sobre la zona de packs y cuenta además el montaje y la limpieza, mientras las plazas
     * de {@see entries()} se ocupan a lo largo de la duración. Por eso la forma de cada ocupante es
     * distinta y no se pueden mezclar en una lista.
     *
     * ⚠️ **Aquí no se excluye nada.** El llamador que valida una línea YA existente —`OrderCreator`—
     * tiene su propia exclusión por índice; este camino sirve a la OFERTA, donde la línea que se
     * está configurando todavía no está en la cesta.
     *
     * @param  array<int, array{ticket_type_id:int, date:string, time:string, qty:int}>  $cart  saneada (`Cart::sanitize`)
     * @param  Collection<int, TicketType>  $types  productos por id
     * @return list<array{start:string, prep_before_min:int, duration_min:int|null, prep_after_min:int, guests:int}>
     */
    public static function packs(array $cart, Collection $types, int $zoneId, string $date): array
    {
        $occupants = [];

        foreach ($cart as $line) {
            if (($line['date'] ?? null) !== $date) {
                continue;
            }

            /** @var TicketType|null $type */
            $type = $types->get($line['ticket_type_id'] ?? 0);
            if (! $type || (int) $type->zone_id !== $zoneId || ! $type->isPack()) {
                continue;
            }

            $occupants[] = [
                'start' => (string) $line['time'],
                'prep_before_min' => (int) $type->prep_before_min,
                // La hora extra alarga la ventana de ESTA fiesta (§10.3): sigue siendo UNA fiesta con
                // SUS invitados, sólo que durante más rato. Si esto se quedara en la duración base,
                // la oferta enseñaría horas que el checkout rechaza — el primo de `AFORO-02`.
                'duration_min' => self::effectiveDurationOf($type, $line),
                'prep_after_min' => (int) $type->prep_after_min,
                'guests' => (int) $line['qty'] * (int) ($type->seats_per_unit ?? 1),
            ];
        }

        return $occupants;
    }

    /**
     * **La duración con la que esta línea de la CESTA ocupa**: la de su producto más lo que la
     * alargan los complementos que extienden la estancia (`specs/hora-extra.md` §10.3).
     *
     * Es la mitad PROVISIONAL de lo que `OrderItem::occupiedMinutes()` es para una línea guardada y
     * lo que `duration_min + extra_minutes` es en los dos mapas. **Las tres tienen que dar el mismo
     * número**: una cesta que cuente distinto ofrece horas que el cobro rechaza.
     *
     * ⚠️ `null` (producto ilimitado) se queda en `null`: lo que ya llega al cierre no se alarga.
     *
     * @param  array{qty:int, time:string, addons?:array<int, array{ticket_type_id:int, qty:int}>}  $line
     */
    private static function effectiveDurationOf(TicketType $type, array $line): ?int
    {
        if ($type->duration_min === null) {
            return null;
        }

        $extra = 0;
        foreach ($line['addons'] ?? [] as $request) {
            $id = (int) ($request['ticket_type_id'] ?? 0);
            $qty = (int) ($request['qty'] ?? 0);
            if ($id <= 0 || $qty <= 0) {
                continue;
            }

            /** @var TicketType|null $addon */
            $addon = $type->addons->firstWhere('id', $id);
            if (! $addon || ! AddonOccupancy::sellableStayExtension($addon)) {
                continue; // no ofrecido, neutro, o config rota: no alargará porque no se venderá
            }

            $effective = AddonResolver::effectiveQuantity($addon->pivot, $qty, (int) $line['qty']);
            $extra += AddonOccupancy::extraMinutes($addon, max(0, $effective));
        }

        return (int) $type->duration_min + $extra;
    }

    /**
     * Los ocupantes de {@see entries()} menos EXACTAMENTE el elemento en validación:
     * `(línea, null)` = la línea base; `(línea, id de complemento)` = esa hija. Todo lo demás —
     * hermanos incluidos — se queda dentro.
     *
     * @param  list<array{entry_start:string, duration_min:int|null, seats:int, line:int, addon:int|null}>  $occupants
     * @return list<array{entry_start:string, duration_min:int|null, seats:int, line:int, addon:int|null}>
     */
    public static function excluding(array $occupants, int $lineIndex, ?int $addonTypeId): array
    {
        return array_values(array_filter(
            $occupants,
            fn (array $occupant): bool => ! ($occupant['line'] === $lineIndex && $occupant['addon'] === $addonTypeId),
        ));
    }

    /**
     * Las hijas que OCUPAN de una línea de entrada, como ocupantes (sin la etiqueta `line`).
     *
     * La cantidad es la EFECTIVA de `AddonResolver` (pivote aplicado), no la cruda: es la que el
     * cobro escribirá, y contar otra rompería la paridad oferta/cobro. Los complementos repetidos
     * se funden por id antes (la misma suma que hace `resolve()`).
     *
     * @param  array{qty:int, time:string, addons?:array<int, array{ticket_type_id:int, qty:int}>}  $line
     * @return list<array{entry_start:string, duration_min:int|null, seats:int, addon:int}>
     */
    private static function occupyingChildrenOf(TicketType $type, array $line): array
    {
        $requested = [];
        foreach ($line['addons'] ?? [] as $request) {
            $id = (int) ($request['ticket_type_id'] ?? 0);
            $qty = (int) ($request['qty'] ?? 0);
            if ($id > 0 && $qty > 0) {
                $requested[$id] = ($requested[$id] ?? 0) + $qty;
            }
        }

        if ($requested === []) {
            return [];
        }

        $childStart = AddonOccupancy::childEntryStart($type, (string) $line['time']);
        if ($childStart === null) {
            return []; // borde 7: un padre sin fin no tiene franja siguiente — tampoco se podrá vender
        }

        $children = [];
        foreach ($requested as $addonId => $qty) {
            /** @var TicketType|null $addon */
            $addon = $type->addons->firstWhere('id', $addonId);
            if (! $addon || ! AddonOccupancy::sellableOccupant($addon)) {
                continue; // no ofrecido, neutro, o config rota: no ocupará porque no se venderá
            }

            $effective = AddonResolver::effectiveQuantity($addon->pivot, $qty, (int) $line['qty']);
            if ($effective <= 0) {
                continue;
            }

            $children[] = [
                'entry_start' => $childStart,
                'duration_min' => $addon->duration_min,
                'seats' => AddonOccupancy::seats($addon, $effective),
                'addon' => (int) $addonId,
            ];
        }

        return $children;
    }

    /**
     * Los productos de la cesta que HOY se venden, en UNA consulta.
     *
     * Mismo filtro que aplica el resto del flujo: una línea de un producto retirado o de una zona
     * apagada **no retiene aforo**, porque tampoco se puede comprar.
     *
     * `with('addons')` es de la hora extra: {@see occupyingChildrenOf()} deriva de esa relación —con
     * su pivote— las hijas que ocupan, y sin la precarga cada línea costaría una consulta.
     *
     * @param  array<int, array{ticket_type_id:int}>  $cart
     * @return Collection<int, TicketType>
     */
    private static function typesOf(array $cart): Collection
    {
        $ids = array_values(array_unique(array_map(
            static fn (array $line): int => (int) $line['ticket_type_id'],
            $cart,
        )));

        return TicketType::sellable()
            ->inOperationalZone()
            ->with('addons')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }
}
