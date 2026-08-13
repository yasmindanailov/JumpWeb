<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\AvailabilityOffer;
use App\Domain\Booking\Contracts\OfferedDate;
use App\Domain\Booking\Contracts\OfferedTime;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use Illuminate\Support\Collection;

/**
 * Read-model de DISPONIBILIDAD ({@see AvailabilityOffer}).
 *
 * Reúne dos cosas que estaban en sitios distintos y ninguna de las dos donde debía:
 *  - las reglas de OFERTA, que ya vivían bien en `SlotOffer` (`AFORO-02`) y aquí solo se piden;
 *  - la derivación de la CESTA a ocupantes provisionales, que vivía dentro de
 *    `Livewire\Tickets\Purchase` —una clase de interfaz—, y que sin embargo es la que decide si una
 *    hora se puede vender. Cualquier otro cliente habría tenido que reescribirla, y una copia que
 *    cuente distinto los ocupantes ofrece horas que el checkout rechaza.
 *
 * **No reserva nada.** Lo que devuelve es cierto en el instante en que se calcula; la garantía la da
 * `OrderCreator` bajo lock (`AFORO-01`), que vuelve a comprobarlo todo.
 */
class AvailabilityReader implements AvailabilityOffer
{
    public function __construct(
        private SlotOffer $offer,
        private RateResolver $rates,
        private SlotAvailability $slotAvailability,
        private PackAvailability $packAvailability,
    ) {}

    /** @return list<OfferedDate> */
    public function dates(int $productId): array
    {
        $product = $this->sellableProduct($productId);

        if (! $product) {
            return [];
        }

        $offerable = $this->offer->offerableDates($product);

        // Las tarifas de TODOS los días, en una consulta: un calendario resuelve decenas de días
        // seguidos y hacerlo uno a uno costaba una consulta por celda. El precio, además, se lee de
        // la relación ya cargada en vez de con `RateResolver::priceCents()`, que consulta por
        // llamada — es la trampa que el spec §10.ter 17 destapó en el read-model del catálogo.
        $rates = $this->rates->forDates($offerable);

        $dates = [];
        foreach ($offerable as $date) {
            $dates[] = new OfferedDate(
                date: $date,
                priceCents: $product->priceCentsForRate($rates[$date]),
                rateKey: (string) $rates[$date]->key,
            );
        }

        return $dates;
    }

    /** @return list<OfferedTime> */
    public function times(int $productId, string $date, array $cart = []): array
    {
        $product = $this->sellableProduct($productId);

        if (! $product || ! $product->zone_id) {
            return [];
        }

        $occupants = $this->occupantsOf($cart, (int) $product->zone_id, $date);

        $times = [];
        foreach ($this->offer->offerableTimes($product, $date, $occupants['entries'], $occupants['packs']) as $time => $info) {
            $times[] = new OfferedTime(
                time: (string) $time,
                available: (int) $info['available'],
                maxQuantity: (int) $info['max_quantity'],
                sellable: (bool) $info['sellable'],
            );
        }

        return $times;
    }

    public function maxQuantity(int $productId, string $date, string $time, array $cart = []): int
    {
        $product = $this->sellableProduct($productId);

        if (! $product || ! $product->zone_id) {
            return 0;
        }

        $slot = Slot::query()
            ->where('zone_id', $product->zone_id)
            ->where('date', $date)
            ->where('start_time', $time)
            ->first();

        if (! $slot) {
            return 0;
        }

        $occupants = $this->occupantsOf($cart, (int) $product->zone_id, $date);

        return $product->isPack()
            ? $this->packAvailability->availableGuestsFor($slot, $product, $occupants['packs'])
            : $this->slotAvailability->availableFor($slot, $product->duration_min, $occupants['entries']);
    }

    /**
     * Producto del CATÁLOGO por id, o `null`.
     *
     * Mismo filtro que `CatalogReader`: en venta online, zona operativa y seleccionable. Que no
     * exista, que no se venda o que su zona esté apagada dan la misma respuesta a propósito —
     * distinguirlas le contaría a un desconocido qué hay en la base de datos—, y además es la
     * respuesta correcta: en los tres casos no hay disponibilidad que ofrecer.
     */
    private function sellableProduct(int $productId): ?TicketType
    {
        return TicketType::sellable()
            ->inOperationalZone()
            ->whereIn('type', [TicketType::TYPE_ENTRY, TicketType::TYPE_PACK])
            ->with('prices')   // el precio del día se lee de aquí, sin una consulta por fecha
            ->whereKey($productId)
            ->first();
    }

    /**
     * Ocupantes PROVISIONALES que la cesta del cliente aporta a esta zona y día.
     *
     * Es la pieza que estaba en la capa de UI. Las dos listas son deliberadamente distintas porque
     * los dos aforos lo son: las entradas ocupan PLAZAS a lo largo de su duración y los packs ocupan
     * CUPO en su propio pool, contando además montaje y limpieza (#82). Mezclarlas restaría plazas
     * de entrada por un cumpleaños, que es justo lo que el pool propio evita.
     *
     * La línea que el cliente está configurando **no** está en su cesta todavía, así que no se
     * cuenta a sí misma — igual que en la web.
     *
     * @param  array<mixed>  $cart
     * @return array{entries: list<array{entry_start:string, duration_min:int|null, seats:int}>, packs: list<array{start:string, prep_before_min:int, duration_min:int|null, prep_after_min:int, guests:int}>}
     */
    private function occupantsOf(array $cart, int $zoneId, string $date): array
    {
        $cart = Cart::sanitize($cart);

        if ($cart === []) {
            return ['entries' => [], 'packs' => []];
        }

        $types = $this->typesOf($cart);

        $entries = [];
        $packs = [];

        foreach ($cart as $line) {
            if ($line['date'] !== $date) {
                continue;
            }

            $type = $types->get($line['ticket_type_id']);
            if (! $type || (int) $type->zone_id !== $zoneId) {
                continue;
            }

            $units = (int) $line['qty'] * (int) ($type->seats_per_unit ?? 1);

            if ($type->isPack()) {
                $packs[] = [
                    'start' => $line['time'],
                    'prep_before_min' => (int) $type->prep_before_min,
                    'duration_min' => $type->duration_min,
                    'prep_after_min' => (int) $type->prep_after_min,
                    'guests' => $units,
                ];

                continue;
            }

            $entries[] = [
                'entry_start' => $line['time'],
                'duration_min' => $type->duration_min,
                'seats' => $units,
            ];
        }

        return ['entries' => $entries, 'packs' => $packs];
    }

    /**
     * Productos de la cesta que hoy se venden, en UNA consulta.
     *
     * Mismo filtro que aplica el resto del flujo: una línea de un producto retirado no retiene
     * aforo, porque tampoco se puede comprar.
     *
     * @param  array<int, array{ticket_type_id:int}>  $cart
     * @return Collection<int, TicketType>
     */
    private function typesOf(array $cart): Collection
    {
        $ids = array_values(array_unique(array_map(
            static fn (array $line): int => (int) $line['ticket_type_id'],
            $cart,
        )));

        return TicketType::sellable()
            ->inOperationalZone()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');
    }
}
