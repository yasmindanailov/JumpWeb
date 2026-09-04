<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\AddonDateChange;
use App\Domain\Booking\Contracts\AddonDatePlan;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Identity\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * **MOVER EL DÍA ES MOVER LAS CONDICIONES DE ESE DÍA, también para los complementos**
 * (`specs/hora-extra.md` §9, `DECISIONES #417`; `[DECIDIDO owner, 2026-09-04]`).
 *
 * Hasta hoy, al cambiar la fecha de una reserva el PADRE se re-tarificaba (`PAY-18`) y el suplemento
 * de fiesta MIXTA también (`cumple-mixto.md` §12, con su porqué escrito: *«mover el día es mover el
 * importe… es un cambio del HECHO, no de la configuración, y por eso sí reconcilia»*), pero **los
 * complementos no**: conservaban el precio del día viejo, y si su producto no se vendía ese día
 * sobrevivían igual. La hora extra no estrena el problema — **es incoherente con una regla que sus
 * dos vecinos ya cumplen**.
 *
 * ## Las dos escrituras NO son simétricas, y está medido (§9.6)
 *
 * | acción | qué se escribe | por qué |
 * |---|---|---|
 * | **retirar** | `markCancelled()` y **NINGÚN** `recordEdit` | el libro ya emite su `−fila`; escribirlo restaría dos veces |
 * | **re-tarificar** | `unit_price` **+ `recordEdit(±Δ)`** | sin el hecho el libro deja de cerrar: el pedido pasa a `under_review` y **el cliente se queda sin desglose** (`#132`) |
 *
 * Es la misma asimetría que `complementos-post-reserva.md` §4.5.1 documenta para el post-form,
 * alcanzada por otro camino: la señal de que es la regla del subsistema y no de ninguna tanda.
 *
 * ## Lo que este servicio NO gobierna, y por qué (§9.8·H2)
 *
 * **Los PORTADORES de fiesta mixta.** Tienen **cero precios en catálogo**, así que la regla «sin
 * precio ese día ⇒ retirar» los retiraría **en todos los cambios de fecha, siempre**. Y no es que
 * sobre: `MixedPartySurcharge::reconcile()` corre en el MISMO post-commit justo después, así que
 * serían dos servicios peleando por la misma línea con el dinero moviéndose dos veces. El editor ya
 * tiene escrito por qué esa línea es intocable: *no es un complemento que el operador gobierne, es el
 * reflejo de una edad que declaró el cliente*.
 *
 * ⚠️ **`free_quantity` tampoco se toca**: sale del pivote, no del día. Re-tarificar un complemento
 * INCLUIDO cambia su `unit_price`, pero lo cobrado sigue siendo `max(0, qty − free) × precio` — con
 * las unidades gratis intactas, un incluido sigue costando cero.
 *
 * ## La frontera transaccional es el diseño, no un detalle (§9.8·H3)
 *
 * {@see plan()} es **LECTURA PURA** y se llama TRES veces con el mismo resultado esperado: para
 * avisar al operador antes de confirmar, para decidir bajo el lock qué hijas hay que aterrizar, y
 * para aplicar. {@see applyMutations()} corre **DENTRO** del lock de zona/día (es mutación de aforo);
 * {@see applyMoney()} corre **POST-COMMIT** en su propia transacción corta, porque —doctrina de
 * `OrderItemEditor` §4.3— *el dinero no puede ir dentro de la txn de aforo*.
 *
 * ⚠️ Este fichero es DINERO: entra en el `CRITICAL_RE` del pre-push (`INVARIANTES §6`).
 */
class AddonDateReconciler
{
    public function __construct(private RateResolver $rates) {}

    /**
     * **Qué le pasaría a cada línea hija si esta reserva se moviera a `$newDate`.** Lectura pura: no
     * escribe, no consulta aforo y no depende de locks.
     *
     * ⚠️ El orden importa (§9.8·H1): quien llama tiene que usar `survivingChildIds` para decidir
     * **qué aterrizajes validar**. Validar el de una hija que se va a retirar bloquea el movimiento
     * entero por un aforo que nadie va a consumir — medido.
     */
    public function plan(OrderItem $item, CarbonInterface $newDate): AddonDatePlan
    {
        $changes = [];
        $surviving = [];

        foreach ($this->governedChildren($item) as $child) {
            $type = $child->ticketType;
            if ($type === null) {
                // Sin producto no hay precio que preguntar ni decisión que tomar: se deja quieta.
                $surviving[] = (int) $child->id;

                continue;
            }

            $current = (int) $child->unit_price;
            $quantity = (int) $child->quantity;
            $free = (int) ($child->free_quantity ?? 0);
            $chargedUnits = max(0, $quantity - $free);
            $new = $this->rates->priceCents($type, $newDate, $quantity);

            [$action, $delta] = match (true) {
                $new === null => [AddonDateChange::WITHDRAW, -($chargedUnits * $current)],
                (int) $new !== $current => [AddonDateChange::REPRICE, $chargedUnits * ((int) $new - $current)],
                default => [AddonDateChange::KEEP, 0],
            };

            if ($action !== AddonDateChange::WITHDRAW) {
                $surviving[] = (int) $child->id;
            }

            $changes[] = new AddonDateChange(
                childId: (int) $child->id,
                productId: (int) $type->getKey(),
                productName: (string) $type->tr('name'),
                action: $action,
                quantity: $quantity,
                freeQuantity: $free,
                currentUnitCents: $current,
                newUnitCents: $new === null ? null : (int) $new,
                chargedDeltaCents: $delta,
            );
        }

        return new AddonDatePlan($changes, $surviving);
    }

    /**
     * La parte que va **DENTRO del lock de zona/día**: retirar las que caen y mover el precio de las
     * que cambian. No escribe dinero — eso es {@see applyMoney()}.
     *
     * ⚠️ La retirada vive aquí y no en la fase financiera **porque no lleva hecho** (§9.6): es
     * mutación pura, coherente con que el libro emita su `−fila` solo.
     */
    public function applyMutations(AddonDatePlan $plan, OrderItem $item): void
    {
        $children = $this->governedChildren($item)->keyBy('id');

        foreach ($plan->changes as $change) {
            $child = $children->get($change->childId);
            if ($child === null) {
                continue;
            }

            if ($change->isWithdrawal()) {
                // `slot_id` a null junto con la cancelación: una hija retirada deja de ocupar su
                // franja, y `occupancyMap` cuenta por `slot_id`, no por `cancelled_at`.
                $child->forceFill(['cancelled_at' => now(), 'slot_id' => null, 'seats' => 0])->save();

                continue;
            }

            if ($change->isRepricing()) {
                $child->forceFill(['unit_price' => $change->newUnitCents])->save();
            }
        }
    }

    /**
     * La parte que va **POST-COMMIT**, en su propia transacción corta: el hecho de cada
     * re-tarificación.
     *
     * ⚠️⚠️ **Sin esto el libro deja de cerrar** y el pedido pasa a `under_review` — medido: total
     * 52,00 → 47,00 con el pagado intacto y `isConsistent` en `false`. El cliente se queda sin su
     * desglose por haber movido una fecha (`#132`).
     *
     * ⚠️ Las RETIRADAS no pasan por aquí a propósito ({@see applyMutations}).
     */
    public function applyMoney(AddonDatePlan $plan, Order $order, ?User $by): void
    {
        if ($by === null) {
            return;
        }

        foreach ($plan->repricings() as $change) {
            if ($change->chargedDeltaCents === 0) {
                continue;
            }
            $child = $order->items->firstWhere('id', $change->childId);
            if ($child === null) {
                continue;
            }

            $order->recordEdit($child, $change->chargedDeltaCents, $by, 'addon_date_reprice', [
                'product_id' => $change->productId,
                'unit_from' => $change->currentUnitCents,
                'unit_to' => $change->newUnitCents,
                'quantity' => $change->quantity,
                'free_quantity' => $change->freeQuantity,
            ]);
        }
    }

    /**
     * Las líneas hijas VIVAS que este servicio gobierna.
     *
     * ⚠️ Fuera: las canceladas (ya no son nada) y **los portadores de fiesta mixta** (§9.8·H2), que
     * los gobierna `MixedPartySurcharge` y que sin esta exclusión se retirarían en cada cambio de
     * fecha porque no tienen precio en catálogo NINGÚN día.
     *
     * @return Collection<int, OrderItem>
     */
    private function governedChildren(OrderItem $item): Collection
    {
        // ⚠️ Consulta FRESCA y no la relación cacheada: esto corre dentro del lock de zona/día, donde
        // la fila cargada antes de entrar puede estar rancia — y de esta lista sale qué se valida.
        return $item->children()
            ->whereNull('cancelled_at')
            ->with('ticketType.prices')
            ->get()
            ->reject(fn (OrderItem $child): bool => ProductAddon::isMixedPartyCarrierId((int) $child->ticket_type_id))
            ->values();
    }
}
