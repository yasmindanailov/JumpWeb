<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\CartPricing;
use App\Domain\Booking\Contracts\CartQuote;
use App\Domain\Booking\Contracts\CartQuoteAddon;
use App\Domain\Booking\Contracts\CartQuoteLine;
use App\Domain\Booking\Models\TicketType;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Tarificación de cesta ({@see CartPricing}).
 *
 * Reúne lo que estaba repartido en TRES métodos de `Livewire\Tickets\Purchase` —`cartLines()` (el
 * desglose), `cartTotalCents()` (el total) y `cartDepositCents()` (lo que se cobra online)—, que
 * recorrían la misma cesta con la misma aritmética y podían separarse en cuanto se tocara uno solo.
 * No inventa reglas: el precio del día sigue saliendo de `RateResolver`, los complementos de
 * `AddonResolver` —el mismo que usa `OrderCreator` para cobrar— y la señal de
 * `TicketType::depositCents()`.
 *
 * **Espejo declarado de `OrderCreator`.** Estos importes tienen que ser los que se cobrarán:
 * `totalCents` espeja `Order::total` y `onlineAmountCents` espeja `Order::onlineDueCents()` para la
 * misma cesta. `CartPricerTest` lo comprueba creando el pedido de verdad y comparando; si alguien
 * cambia una de las dos aritméticas, ese test cae — que es justo lo que no existía cuando las
 * reglas vivían en la vista.
 *
 * **Dos diferencias deliberadas con `OrderCreator`**, porque un presupuesto no es un checkout:
 *  - una línea sin precio para la tarifa del día NO lanza: se tarifica a 0 con `unitPriceCents`
 *    nulo, que es lo que hace hoy el carrito de la web. El rechazo (`PAY-12`) lo pone el checkout;
 *  - una selección de complementos inconsistente NO rompe: se tarifica la línea sin ellos. Romper
 *    la previsualización de un carrito por un complemento que dejó de estar disponible dejaría al
 *    cliente sin poder ni siquiera quitar la línea.
 *
 * **Una sola pasada.** La web llamaba a los tres métodos en cada render y cada uno resolvía otra vez
 * el precio de cada línea y de cada complemento (`RateResolver::priceCents` consulta por llamada, la
 * trampa que el spec §10.ter 17 documentó en el catálogo). Aquí la tarifa de cada fecha distinta se
 * resuelve UNA vez y el precio se lee de la relación ya cargada (`priceCentsForRate`), con resultado
 * idéntico.
 */
class CartPricer implements CartPricing
{
    public function __construct(private RateResolver $rates, private AddonResolver $addons) {}

    public function quote(array $cart): CartQuote
    {
        // Forma canónica de línea, la misma que aplica `OrderCreator`: no puede haber dos ideas de
        // qué es una cesta válida. Sobre una cesta ya saneada —la de la web— es idempotente.
        $cart = Cart::sanitize($cart);

        $types = $this->sellableTypesIn($cart);

        // Tarifa por fecha distinta, resuelta UNA sola vez y **solo si hace falta**: una cesta vacía
        // —o cuyas líneas ya no se venden— no resuelve ninguna. No es una micro-optimización: sin
        // tarifas configuradas `RateResolver::for()` lanza, así que resolver por adelantado rompía
        // pantallas que hoy funcionan (lo cazó la suite).
        //
        // Es una variable LOCAL y no un memo estático a propósito: un estático sobreviviría a la
        // petición y al test siguiente, que es la trampa que `SUITE-02` documenta para `Setting::$memo`.
        $rates = [];

        $lines = [];
        $total = 0;
        $online = 0;

        foreach ($cart as $index => $line) {
            $type = $types->get($line['ticket_type_id']);

            // Producto retirado de la venta o con la zona desactivada: la línea no se tarifica ni
            // se lista (P8). Su hueco en la secuencia de `index` es la señal de que cayó.
            if (! $type) {
                continue;
            }

            $quantity = (int) $line['qty'];

            // ⚠️ **Todo se tarifica por el día de la LÍNEA, complementos incluidos** (`#415`). Antes
            // había aquí una excepción explícita —un complemento como línea principal se tarificaba a
            // HOY «porque no tiene fecha propia»— y no era cierto: `date` es obligatorio en TODA línea
            // de cesta (`CartLine.required` del contrato), así que la fecha existe siempre. Que un
            // complemento aparezca como línea principal no pasa por la interfaz (no es seleccionable),
            // pero una cesta forjada podría traerlo: se tarifica igual y el checkout lo rechazará.
            $priceDate = $line['date'];
            $rates[$priceDate] ??= $this->rates->for(Carbon::parse($priceDate));

            // Leer el precio de la relación ya cargada equivale a `RateResolver::priceCents()`
            // —misma tarifa, misma columna— pero sin su consulta por llamada: la trampa que el spec
            // §10.ter 17 destapó en el read-model de catálogo. Diez líneas del mismo día resuelven
            // una tarifa, no diez.
            // `#324`: la CANTIDAD entra en el precio (tramos de volumen). Va aquí y no en un cálculo
            // aparte porque el presupuesto y el checkout tienen que dar el MISMO número — es la
            // segunda fuente de verdad que `CartPricing` existe para evitar.
            $unitPrice = $type->priceCentsForRate($rates[$priceDate], $quantity);
            $subtotal = $quantity * (int) $unitPrice;

            $addons = $this->resolveAddons($type, $quantity, $line['addons'], Carbon::parse($priceDate));
            $addonsSubtotal = array_sum(array_map(
                static fn (CartQuoteAddon $addon): int => $addon->subtotalCents,
                $addons,
            ));

            // Señal por línea (#225 F2). `depositCents` es data-driven y devuelve el subtotal entero
            // cuando el producto no cobra señal, así que `hasDeposit` se decide COMPARANDO, no
            // preguntando al producto: una señal configurada al 100 % no deja resto en el parque.
            $deposit = $type->depositCents($subtotal);
            $hasDeposit = $deposit < $subtotal;

            // Opción A de #225 (decisión de producto): los complementos de un producto CON señal se
            // cobran íntegros en el parque; los de un producto sin señal, online con el resto.
            $gateRemainder = $hasDeposit ? ($subtotal - $deposit) + $addonsSubtotal : 0;

            $total += $subtotal + $addonsSubtotal;
            $online += $deposit + ($hasDeposit ? 0 : $addonsSubtotal);

            $lines[] = new CartQuoteLine(
                index: $index,
                productId: (int) $type->id,
                name: (string) $type->tr('name'),
                isPack: $type->isPack(),
                // El icono lo resuelve el producto, no la superficie (`DECISIONES #140`).
                icon: $type->iconKey(),
                date: $line['date'],
                time: $line['time'],
                quantity: $quantity,
                unitPriceCents: $unitPrice,
                subtotalCents: $subtotal,
                addons: $addons,
                hasDeposit: $hasDeposit,
                depositCents: $deposit,
                gateRemainderCents: $gateRemainder,
            );
        }

        return new CartQuote($lines, $total, $online);
    }

    /**
     * Productos de la cesta que HOY se venden, con sus precios y complementos ya cargados.
     *
     * Mismo filtro que aplicaban la selección y el carrito de la web (`sellable` + zona operativa):
     * lo que se puede elegir, lo que se tarifica y lo que se cobra tienen que ser el mismo conjunto.
     *
     * @param  array<int, array{ticket_type_id:int, addons:array<int,array{ticket_type_id:int,qty:int}>}>  $cart
     * @return Collection<int, TicketType>
     */
    private function sellableTypesIn(array $cart): Collection
    {
        $ids = [];
        foreach ($cart as $line) {
            $ids[] = (int) $line['ticket_type_id'];
        }

        if ($ids === []) {
            return new Collection;
        }

        return TicketType::sellable()
            ->inOperationalZone()
            ->whereIn('type', [TicketType::TYPE_ENTRY, TicketType::TYPE_PACK, TicketType::TYPE_ADDON])
            ->whereIn('id', array_values(array_unique($ids)))
            // `prices` para leer el precio del día sin una consulta por línea, y `addons` (con su
            // pivote) porque `AddonResolver` recorre esa relación entera en cada línea. NO se carga
            // `addons.prices`: el resolutor pide el precio de cada complemento por consulta —usa el
            // *query builder* de la relación, no la colección— así que precargarla no ahorra nada.
            // `#324`: `priceTiers` viaja con el resto para que el tramo se resuelva sobre la relación
            // ya cargada, igual que `prices` — la razón entera de que este lector no llame a
            // `RateResolver` es no consultar por línea.
            ->with(['prices', 'priceTiers', 'addons'])
            ->get()
            ->keyBy('id');
    }

    /**
     * Complementos de la línea resueltos con la autoridad de `AddonResolver` (la misma que cobra
     * `OrderCreator`): obligatorios inyectados, default de cada grupo, cantidad efectiva de los
     * per-invitado y unidades incluidas descontadas.
     *
     * El nombre sale de la relación `addons` del propio producto, que es de donde el resolutor toma
     * los complementos ofrecibles: preguntarlo a otra consulta abriría la puerta a que un
     * complemento resuelto no tuviera nombre que mostrar.
     *
     * ⚠️ `$date` es el día de la VISITA de la línea, el MISMO con el que se tarifica el padre
     * (`#415`): el presupuesto y el checkout tienen que dar el mismo número, y desde la hora extra
     * hay complementos cuyo precio depende del tipo de día.
     *
     * @param  array<int, array{ticket_type_id:int, qty:int}>  $requested
     * @return list<CartQuoteAddon>
     */
    private function resolveAddons(TicketType $product, int $quantity, array $requested, CarbonInterface $date): array
    {
        if ($product->isAddon() || $requested === []) {
            return [];
        }

        try {
            $resolved = $this->addons->resolve($product, $quantity, $requested, $date);
        } catch (Throwable) {
            // Selección inconsistente (p. ej. un complemento que dejó de estar disponible): la línea
            // se tarifica sin complementos en vez de romper. El checkout volverá a resolverlos y
            // dará el error que corresponda.
            return [];
        }

        $names = $product->addons->keyBy('id');

        $rows = [];
        foreach ($resolved['rows'] as $row) {
            $quantityResolved = (int) $row['quantity'];
            $free = (int) $row['free_quantity'];

            $rows[] = new CartQuoteAddon(
                productId: (int) $row['ticket_type_id'],
                name: (string) $names->get($row['ticket_type_id'])?->tr('name'),
                quantity: $quantityResolved,
                freeQuantity: $free,
                subtotalCents: max(0, $quantityResolved - $free) * (int) $row['unit_price'],
            );
        }

        return $rows;
    }
}
