<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\CounterSale;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Payments\Services\PaymentSettings;
use App\Domain\Platform\Services\DisplayTime;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Fuente ÚNICA de "qué fechas y horas se pueden OFRECER" de un producto (entrada/pack).
 *
 * Antes (bug Fase 1, auditoría 2026-06-12) la compra pública (`Livewire\Tickets\Purchase`) y el
 * pedido manual del panel (`Filament\Pages\CreateManualOrderPage`) derivaban su oferta de fuentes
 * DISTINTAS: la web de las franjas materializadas (`slots`) + la ventana viva del día; el panel
 * abría todo el rango aritmético `hoy..hoy+horizonte` SIN mirar franjas ni la ventana del día. Por
 * eso "no correspondían" en fechas/horas. Este servicio centraliza la lógica CORRECTA de la web
 * para que AMBAS superficies la consuman y no puedan volver a divergir.
 *
 * Reglas (idénticas a las que ya aplicaba la web):
 *  - Solo franjas `sellableOnline()` (online_sales_open=true, status≠closed) de la zona del producto.
 *  - Dentro del horizonte de venta [hoy, hoy+`PaymentSettings::purchaseHorizonMonths`] (zona del parque).
 *  - Dentro de la ventana viva del día del producto (`ProductAvailability::allowsStart`): una
 *    `special_date` que cierra/recorta un día filtra la oferta EN VIVO (sin regenerar franjas).
 *  - Horas de PACK: solo las que tienen cupo para al menos `min_qty` invitados (un cumpleaños bajo
 *    su mínimo no es reservable). Horas de ENTRADA: todas las de la ventana; las llenas se marcan
 *    `sellable=false` (se muestran deshabilitadas, no se ocultan).
 *
 * "Hoy" se calcula en la zona OPERATIVA del parque (`DisplayTime::today()`), no en UTC.
 */
class SlotOffer
{
    public function __construct(
        private ProductAvailability $productWindow,
        private SlotAvailability $slotAvailability,
        private PackAvailability $packAvailability,
    ) {}

    /**
     * Meses del horizonte de compra/edición. Es la lectura Booking de
     * `PaymentSettings::purchaseHorizonMonths` — una flecha Booking→Payments
     * baselined en `ModuleBoundariesTest` que SOLO ENCOGE, así que los
     * consumidores nuevos de la familia de la oferta (extracción 3 del
     * desmontaje de `ViewOrder`: `ItemRescheduleOffer`) la leen POR AQUÍ en
     * vez de abrir otra flecha al módulo de pagos.
     */
    public static function horizonMonths(): int
    {
        return PaymentSettings::purchaseHorizonMonths();
    }

    /**
     * Franjas ofrecibles del producto en TODO el horizonte: `sellableOnline` + zona + horizonte +
     * ventana del día. Si `$type` es null (mes inicial del calendario público antes de elegir
     * producto) no filtra por zona ni ventana: devuelve todas las franjas vendibles del horizonte.
     *
     * ⚠️ **Es la vía PESADA a propósito**, y desde `#465` no es la única: hidrata un modelo por
     * franja porque sus llamadores necesitan el `Slot` entero (el cupo se calcula sobre él). Quien
     * solo necesite saber QUÉ DÍAS se ofrecen tiene {@see offerableDates()}, que hace la misma
     * pregunta sin construir 1.947 objetos para tirarlos.
     *
     * @return Collection<int, Slot>
     */
    public function offeredSlots(?TicketType $type, ?CounterSale $sale = null): Collection
    {
        [$from, $to] = $this->horizon();

        return $this->offeredSlotsBetween($type, $sale, $from, $to);
    }

    /**
     * El NÚCLEO: las franjas ofrecibles entre dos fechas, ya hidratadas.
     *
     * ⚠️ **El rango es una ACOTACIÓN, no una regla nueva.** Quien pide un día suelto tiene que
     * haberlo recortado antes contra el horizonte ({@see clampToHorizon()}): sin eso, acotar la
     * consulta **retiraría el techo de venta** y un día a dos años vista se ofrecería porque ya nadie
     * lo filtra. Es el defecto que introduce el atajo, y por eso el recorte vive en un sitio y tiene
     * caso propio en las dos direcciones.
     *
     * ⚠️ Aquí NO hay guarda de «rango invertido»: el llamador que recorta devuelve antes si el día
     * cae fuera, y los dos que quedan pasan `from <= to` por construcción. La escribí y **el arnés de
     * mutación la declaró inalcanzable** —quitarla no ponía nada en rojo, y `whereBetween` con el
     * rango al revés no devuelve filas de todos modos—: código defensivo que no defiende de nada es
     * ruido que el siguiente lector tiene que descartar.
     *
     * @return Collection<int, Slot>
     */
    private function offeredSlotsBetween(?TicketType $type, ?CounterSale $sale, string $from, string $to): Collection
    {
        $sale ??= CounterSale::no();
        $now = DisplayTime::now();

        return $this->offeredSlotQuery($type, $from, $to)
            ->when($type?->isPack(), fn ($q) => $q->with('zone')) // packs leen el cupo por zona
            ->get()
            ->filter(fn (Slot $s) => $this->passesOffer(
                $type, $s->date, $s->date->toDateString(), (string) $s->start_time, $now, $sale,
            ))
            ->values();
    }

    /**
     * La CONSULTA de la oferta, en UN sitio: franjas vendibles de la zona del producto en el rango.
     *
     * ⚠️ **Las dos vías —la pesada y la ligera— la comparten**, y por eso usa el *scope*
     * `sellableOnline()` en vez de repetir sus dos condiciones: una copia a mano se separa el día que
     * alguien añada un estado nuevo de franja, y entonces el calendario ofrecería días que las horas
     * rechazan.
     *
     * @return Builder<Slot>
     */
    private function offeredSlotQuery(?TicketType $type, string $from, string $to): Builder
    {
        return Slot::query()
            ->whereBetween('date', [$from, $to])
            ->sellableOnline()
            ->when($type && $type->zone_id, fn ($q) => $q->where('zone_id', $type->zone_id))
            ->orderBy('date')
            ->orderBy('start_time');
    }

    /**
     * El PREDICADO de la oferta, en UN sitio: ¿esta franja se ofrece?
     *
     * Son las tres reglas que ya aplicaba el filtro de `offeredSlots()`, y siguen siendo las mismas
     * para las dos vías: **corte intra-día**, **ventana viva del día** (`ProductAvailability`, que es
     * el punto de estrangulamiento del horario en toda la venta) y **antelación mínima**, que el
     * mostrador no atiende (`#330`).
     *
     * ⚠️ Recibe la fecha DOS veces —el objeto y su `Y-m-d`— porque la vía ligera trabaja con filas
     * crudas y parsear un `Carbon` por franja costaría justo lo que se viene a ahorrar: parsea uno
     * por DÍA y lo reutiliza para sus once franjas.
     */
    private function passesOffer(
        ?TicketType $type,
        CarbonInterface $date,
        string $ymd,
        string $startTime,
        Carbon $now,
        CounterSale $sale,
    ): bool {
        // Corte intra-día (floor, auditoría Fase 1): una franja de HOY cuya hora de inicio YA pasó no
        // se ofrece (p. ej. las 10:00 cuando son las 10:31). Aplica siempre. Fuente ÚNICA
        // (`passesIntradayFloor`) compartida con el backstop del checkout (`OrderCreator`).
        if (! self::passesIntradayFloor($ymd, $startTime, $now)) {
            return false;
        }

        if ($type === null) {
            return true;
        }

        // Ventana viva del día (special_dates/horario) + ANTELACIÓN MÍNIMA de reserva del producto
        // (días de calendario u horas rodantes). Ambas comparten esta única fuente.
        // `#330` — la ANTELACIÓN MÍNIMA no ata al mostrador: es una regla para quien compra solo.
        // ⚠️ La ventana de horario del producto SÍ sigue mandando, y el corte intra-día de arriba
        // también: aquello es el parque y esto es el tiempo, ninguna de las dos las decide quien
        // vende.
        return $this->productWindow->allowsStart($type, $date, $startTime)
            && ($sale->ignoresMinAdvance() || $type->meetsMinAdvance($ymd, $startTime, $now));
    }

    /**
     * El horizonte de venta `[hoy, hoy + N meses]` en la zona OPERATIVA del parque.
     *
     * @return array{0: string, 1: string}
     */
    private function horizon(): array
    {
        $now = DisplayTime::now();

        return [
            $now->toDateString(),
            $now->copy()->addMonths(PaymentSettings::purchaseHorizonMonths())->toDateString(),
        ];
    }

    /**
     * Recorta un día contra el horizonte: devuelve `null` si cae fuera.
     *
     * ⚠️⚠️ **Es la mitad que no se ve fallar.** Mientras la oferta cargaba el horizonte entero, el
     * techo de venta lo ponía la propia consulta; al preguntar por un día suelto ese techo desaparece,
     * y un día a dos años vista **se vendería** sin que nada avisara. Aquí se vuelve a poner.
     */
    private function clampToHorizon(string $date): ?string
    {
        [$from, $to] = $this->horizon();

        return ($date >= $from && $date <= $to) ? $date : null;
    }

    /**
     * Corte intra-día (floor, auditoría Fase 1): ¿la franja `(date, startTime)` sigue siendo
     * ofrecible respecto a "ahora"? Una franja de HOY cuya hora de inicio YA pasó NO lo es; las de
     * días futuros, siempre. Fuente ÚNICA del corte para que la OFERTA (`offeredSlots`, web+panel) y
     * el BACKSTOP del checkout (`OrderCreator`) no diverjan. "Ahora" se pasa explícito en la zona
     * operativa del parque (`DisplayTime::now()`); ambos `date`/`startTime` en formato canónico de BD
     * (`Y-m-d` / `H:i:s`), comparables lexicográficamente.
     */
    public static function passesIntradayFloor(string $date, string $startTime, Carbon $now): bool
    {
        if ($date !== $now->toDateString()) {
            return true;
        }

        return substr($startTime, 0, 8) >= $now->format('H:i:s');
    }

    /**
     * Fechas (`Y-m-d`, ordenadas) con al menos una franja ofrecible para el producto.
     *
     * ❗❗ **Es la vía LIGERA, y no es una regla distinta: es la MISMA pregunta sin construir lo que
     * no se usa** (`#465`). La consulta es la de {@see offeredSlotQuery()} —con sus *scopes*, no con
     * un `WHERE` copiado— y el filtro es {@see passesOffer()}, el mismo que aplica la vía pesada. Lo
     * único que cambia es lo que se trae y cuándo se para:
     *
     *  - **Filas crudas** (`toBase()`, solo `date` y `start_time`): un calendario no necesita el
     *    modelo `Slot`, y hidratar 1.947 para tirarlos costaba 68 ms de los 329 que costaba esto.
     *  - **Un `Carbon` por DÍA y no por franja**: la ventana del día es la misma para sus once
     *    franjas, y resolverla once veces era **124 ms de los 167** que costaba el filtro.
     *  - **En cuanto un día pasa, se salta el resto de sus franjas**: para decir que un día se ofrece
     *    basta una.
     *
     * ▶ Medido con control (mismo producto, mismo horizonte): **329 ms → 18**, y las **mismas 176
     * fechas**. Que las dos vías no puedan separarse lo fija `SlotOfferPathParityTest`.
     *
     * @return array<int, string>
     */
    public function offerableDates(TicketType $type, ?CounterSale $sale = null): array
    {
        $sale ??= CounterSale::no();
        $now = DisplayTime::now();
        [$from, $to] = $this->horizon();

        $dias = [];
        $fecha = null;      // el `Carbon` del día en curso, reutilizado por todas sus franjas
        $ymd = null;

        foreach ($this->offeredSlotQuery($type, $from, $to)->toBase()->get(['date', 'start_time']) as $fila) {
            // ⚠️ El driver devuelve `date` como cadena en MySQL y puede traer la hora en SQLite: se
            // recorta a `Y-m-d`, que es el formato canónico con el que compara todo este servicio.
            $dia = substr((string) $fila->date, 0, 10);

            if (isset($dias[$dia])) {
                continue;   // ese día ya se ofrece: sus demás franjas no cambian la respuesta
            }

            if ($dia !== $ymd) {
                $ymd = $dia;
                $fecha = Carbon::parse($dia);
            }

            if ($this->passesOffer($type, $fecha, $dia, (string) $fila->start_time, $now, $sale)) {
                $dias[$dia] = true;
            }
        }

        return array_keys($dias);
    }

    /**
     * Horas ofrecibles para un producto en una fecha, con su disponibilidad para mostrar.
     * Misma semántica que la web: packs filtrados a cupo ≥ min_qty; entradas todas las de la ventana
     * (las llenas con `sellable=false`). El llamador pasa los ocupantes provisionales de su cesta.
     *
     * ⚠️ **`available` y `max_quantity` no son el mismo número.** En una entrada coinciden; en un
     * PACK no: `available` son las plazas que le quedan a la franja (para MOSTRAR «quedan N») y
     * `max_quantity` es cuántos invitados admite ESA fiesta, topado además por el `max_qty` del pack
     * — con cupo 60 y un pack de máximo 20, son 60 y 20—. Un selector de cantidad construido sobre
     * `available` dejaría pedir invitados que el checkout rechazaría.
     * `max_quantity` se calculaba aquí desde siempre y se descartaba: lo expone Fase 3 · paso 4b
     * para que la API no tenga que recalcularlo (y con él, otra copia de la regla).
     *
     * ❗❗ **`#329` — `$allowBelowPackMinimum` es la mitad de la excepción del operador que NO se ve
     * fallar.** Sin ella, un operador con permiso para vender 20 invitados en un pack de mínimo 30
     * seguiría **sin ver ofertada** una franja con 25 plazas libres, porque este filtro descarta la
     * franja ENTERA cuando el hueco no llega al mínimo. La función habría funcionado en la franja
     * vacía y fallado justo en la compartida, sin dar ningún error: *el mínimo se impone en cuatro
     * sitios y solo dos de ellos avisan.* `[DECIDIDO owner, 2026-09-01]`: con el permiso activo, esas
     * franjas se ofrecen.
     *
     * ⚠️ El suelo baja a 1, **no a 0**: una franja sin una sola plaza libre (cupo de fiestas lleno,
     * franja cerrada) sigue fuera — eso es AFORO, no el mínimo del pack, y `AFORO-01` no se negocia.
     *
     * @param  array<int, array{entry_start:string, duration_min:int|null, seats:int}>  $cartOccupants
     * @param  array<int, array{start:string, prep_before_min:int, duration_min:int|null, prep_after_min:int, guests:int}>  $cartPackOccupants
     * @return array<string, array{available:int, max_quantity:int, sellable:bool}>
     */
    public function offerableTimes(TicketType $type, string $date, array $cartOccupants = [], array $cartPackOccupants = [], ?CounterSale $sale = null): array
    {
        $sale ??= CounterSale::no();

        // ❗❗ **Se carga SOLO ese día** (`#465`): antes se traía el horizonte entero —1.947 franjas—
        // para quedarse con las once de una fecha. Medido: **364 ms → 30**, con las mismas horas.
        // ⚠️⚠️ **Y el recorte contra el horizonte es OBLIGATORIO aquí**: mientras la consulta abarcaba
        // `[hoy, hoy+N meses]`, el techo de venta lo ponía ella; preguntando por un día suelto ese
        // techo desaparece y **un día a dos años vista se ofrecería** sin que nada fallara. Un día
        // fuera del horizonte da rango vacío, que es lo que daba antes.
        $dia = $this->clampToHorizon($date);
        $slots = $dia === null ? collect() : $this->offeredSlotsBetween($type, $sale, $dia, $dia);

        $out = [];

        if ($type->isPack()) {
            $min = $sale->allowsBelowPackMinimum() ? 1 : $type->contractableMinimum();
            foreach ($slots as $slot) {
                $free = $this->packAvailability->availableGuestsFor($slot, $type, $cartPackOccupants);
                if ($free < $min) {
                    continue; // bajo el mínimo de invitados: no reservable
                }
                $display = $this->packAvailability->freeGuestSlots($slot, $type, $cartPackOccupants) ?? $free;
                $out[(string) $slot->start_time] = [
                    'available' => (int) $display,
                    'max_quantity' => (int) $free,
                    'sellable' => true,
                ];
            }

            return $out;
        }

        foreach ($slots as $slot) {
            $available = $this->slotAvailability->availableFor($slot, $type->duration_min, $cartOccupants);
            $out[(string) $slot->start_time] = [
                'available' => (int) $available,
                'max_quantity' => (int) $available,
                'sellable' => $available > 0,
            ];
        }

        return $out;
    }
}
