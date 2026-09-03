<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Platform\Services\DisplayTime;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * **Los complementos que el cliente añade DESPUÉS de reservar** (`specs/complementos-post-reserva.md`
 * §4.5, T2 de `DECISIONES #413`).
 *
 * Reconcilia las líneas hijas de venta posterior de una reserva con el estado que el cliente pide en
 * su post-form. Calcado de {@see MixedPartySurcharge}, que resolvió el mismo problema desde el mismo
 * formulario: reconciliar, no acumular.
 *
 * ## La propiedad que lo gobierna todo, y por qué la puerta es un HECHO
 *
 * Una línea creada DESPUÉS del pedido lleva un ajuste `edit` de su importe exacto, así que
 * `LineFacts::birthValue()` vale 0: **no aportó ni un céntimo al cobro online**, y quitarla es
 * neutro en dinero. Por eso **R0 no mira el eje del catálogo, mira la fila**: el eje es
 * configuración MUTABLE —pasar «Tarta» de `booking` a `postform` es lo primero que el parque hará—
 * y con él como puerta el cliente habría podido retirar líneas ya cobradas, **con el libro cerrando
 * en verde**. El hecho no envejece con el catálogo.
 *
 * ## Las TRES escrituras, que NO son simétricas
 *
 * | gesto | qué se escribe | por qué |
 * |---|---|---|
 * | **alta** | línea nueva + `recordEdit(+cobrado)` | nace con `nac = 0` |
 * | **subir / bajar** | `quantity` + `recordEdit(±Δ)` | sin el movimiento, `nac` se desplaza y `I1` falla |
 * | **retirar** | `markCancelled()` y **NINGÚN** `recordEdit` | el libro ya emite su `−fila`; escribirlo restaría DOS veces |
 *
 * ⚠️⚠️ Las dos roturas tienen la misma consecuencia: el pedido entero pasa a «en revisión» y **al
 * cliente se le oculta su desglose** (`#132`) por haber tocado un cubo de refrescos. `chargedSubtotalCents()`
 * **no mira `cancelled_at`**, que es lo que hace que la retirada no necesite hecho propio.
 *
 * ## El orden de LOCKS es una regla del subsistema, no un detalle
 *
 * **`orders` → `order_items` → hijas.** La primera versión de la spec afirmaba que no había inversión
 * posible; la revisión adversarial **reprodujo el interbloqueo** (`SQLSTATE[40001]`) con dos
 * conexiones reales: `OrderItemCanceller::cancel()`, las dos transacciones de
 * `Order::executePartialRefund()` y la acción «Cancelar pedido» toman `orders` ANTES que el ítem.
 * Tomar aquí el pedido como PRIMERA sentencia elimina la inversión — y el `recordEdit` anidado, que
 * es un SAVEPOINT y retiene su lock hasta el commit exterior, ya no adquiere nada nuevo.
 * ⚠️ Coste asumido y dicho: dos reservas del mismo pedido no guardan su post-form a la vez. Es
 * contención, no incorrección.
 *
 * ## Lo que se re-valida BAJO el lock (`SEC-04` aplicado al tiempo)
 *
 * Entre pintar el formulario y guardarlo puede pasar la fiesta, una cancelación o el plazo de un
 * complemento. Todo se re-comprueba dentro: elegibilidad, cancelación, franja terminada y el corte
 * de CADA complemento.
 */
final class PostFormAddons
{
    public function __construct(private RateResolver $rates) {}

    /**
     * Deja las líneas de venta posterior de esta reserva igual a lo que el cliente pide.
     *
     * @param  array<int,int>  $desired  addonId → cantidad deseada (0 = quitarlo)
     * @param  string  $via  por dónde entró quien guarda (`signed_link` | `account` | `panel`)
     * @param  User|null  $by  el OPERADOR cuando guarda el panel; `null` = el propio titular
     * @param  string|null  $expectedVersion  el TOKEN OPTIMISTA que el cliente vio ({@see versionOf});
     *                                        `null` = sin comprobar (el llamante decide si lo exige)
     */
    public function reconcile(OrderItem $principal, array $desired, string $via, ?User $by = null, ?string $expectedVersion = null): PostFormAddonChanges
    {
        $order = $principal->order;
        if ($order === null) {
            return new PostFormAddonChanges;
        }

        $changes = DB::transaction(function () use ($principal, $order, $desired, $by, $expectedVersion): PostFormAddonChanges {
            // D11 · el PEDIDO primero, y el ítem después. Ver el docblock de la clase: es la regla del
            // subsistema, y el interbloqueo que evita está REPRODUCIDO, no supuesto.
            Order::query()->lockForUpdate()->find($order->getKey());

            /** @var OrderItem|null $item */
            $item = OrderItem::query()
                ->with(['ticketType.addons', 'slot', 'order', 'children'])
                ->whereKey($principal->getKey())
                ->lockForUpdate()
                ->first();

            // `SEC-04` aplicado al tiempo: lo que valía al pintar puede no valer al guardar.
            if ($item === null || ! $item->acceptsGuestForm() || $item->isFinishedInPractice()) {
                return new PostFormAddonChanges(blocked: $this->blockAll($desired, 'closed'));
            }

            // El TOKEN OPTIMISTA (borde 5 de §4.8): el lock SERIALIZA las dos escrituras, pero **no
            // decide cuál gana**. Sin esto, el operador sube «Tarta» a 3 por teléfono, el cliente
            // guarda con la pantalla cargada antes y su estado deseado la baja a 1 — borrando el
            // trabajo del operador en silencio y con un movimiento de dinero. Es la única superficie
            // de dinero del producto sin token, y va a mover dinero.
            if ($expectedVersion !== null && $expectedVersion !== self::versionOf($item)) {
                return new PostFormAddonChanges(blocked: $this->blockAll($desired, 'stale'));
            }

            return $this->apply($item, $desired, $by);
        });

        if ($changes->changed()) {
            // `RGPD-02`: ni un nombre ni una alergia — qué reserva, por dónde entró y cuánto se movió.
            AuditLogger::log('orders.postform_addons_changed', $order, [
                'order_code' => $order->code,
                'order_item_id' => $principal->getKey(),
                'via' => $via,
                'delta_cents' => $changes->deltaCents,
                'moves' => array_map(
                    static fn (array $m): array => ['addon_id' => $m['addon_id'], 'from' => $m['from'], 'to' => $m['to']],
                    $changes->moves,
                ),
            ]);
        }

        return $changes;
    }

    /**
     * El TOKEN OPTIMISTA de una reserva: la misma convención que las CINCO puertas del operador
     * (`OrderItemEditor`, `OrderItemCanceller`, `OrderItemRefunder`, `OrderItemGuestDataWriter`,
     * `OrderItemEventDataWriter`), para que cliente y panel hablen del mismo instante.
     */
    public static function versionOf(OrderItem $item): string
    {
        return (string) ($item->updated_at?->getTimestamp() ?? '');
    }

    /**
     * Los complementos que el cliente PUEDE mover ahora mismo en esta reserva: los de fase
     * `postform` con configuración sana ({@see AddonResolver::forStage}) **y dentro de su plazo**.
     *
     * ⚠️⚠️ **«Fuera de plazo» NO cuenta como ofrecido, y de ahí sale la regla que evita una trampa
     * medida**: los `<input disabled>` **no se envían**, así que con plazos distintos por complemento
     * («tapas 48 h», «cubo 2 h») un guardado normal llegaría sin las tapas — y si el estado deseado
     * gobernase todo lo ofrecido, **se cancelarían solas**, justo lo contrario de D5. Conservar lo
     * que está fuera de plazo es lo que hace que ese guardado sea inofensivo.
     *
     * @return Collection<int, TicketType> por id de complemento
     */
    public function offerableFor(OrderItem $principal): Collection
    {
        $type = $principal->ticketType;
        if ($type === null || ! $principal->acceptsGuestForm() || $principal->isFinishedInPractice()) {
            return collect();
        }

        return AddonResolver::forStage($type->addons, ProductAddon::STAGE_POSTFORM)
            ->filter(fn (TicketType $addon): bool => self::isWithinWindow($principal, $addon->pivot))
            ->keyBy('id');
    }

    /**
     * ¿Estamos DENTRO del plazo de este complemento para esta reserva? (§4.6.)
     *
     * ⚠️⚠️ **Se mide con `DisplayTime::now()` contra la hora de PARED de la franja**, y no con el
     * predicado que cierra el post-form: `OrderItem::isFinishedInPractice()` parsea esa hora como UTC
     * y por eso declara terminada una reserva 1–2 h TARDE (ficha en `DEUDA.md`, §4.9). Heredarlo aquí
     * dejaría quitar un extra ya consumido — que es exactamente lo que el plazo existe para impedir.
     *
     * ⚠️ Y por eso el plazo es OBLIGATORIO en un enganche `postform` (D10): `null` significaría
     * «hereda el cierre del post-form», o sea heredar el reloj torcido. Sin plazo declarado, no se
     * ofrece — el cinturón de `AddonResolver::forStage()` ya lo excluye antes de llegar aquí.
     */
    public static function isWithinWindow(OrderItem $principal, ProductAddon $pivot): bool
    {
        $hours = $pivot->postformCutoffHours();
        $slot = $principal->slot;

        if ($hours === null || $slot === null || $slot->date === null || $slot->start_time === null) {
            return false;
        }

        // Hora de PARED del parque, que es como se guardan las franjas (lo demuestra que
        // `SlotOffer::passesIntradayFloor` las compare contra `DisplayTime::now()`).
        $start = Carbon::parse(
            $slot->date->format('Y-m-d').' '.$slot->start_time,
            DisplayTime::timezone(),
        );

        return DisplayTime::now()->lt($start->subHours($hours));
    }

    /**
     * La reconciliación, ya con el ítem bloqueado y fresco.
     *
     * @param  array<int,int>  $desired
     */
    private function apply(OrderItem $item, array $desired, ?User $by): PostFormAddonChanges
    {
        $order = $item->order;
        $offerable = $this->offerableFor($item);
        $actor = $by ?? $order?->user;

        if ($order === null || $actor === null) {
            return new PostFormAddonChanges(blocked: $this->blockAll($desired, 'closed'));
        }

        // R0 · solo se gobiernan las hijas que NACIERON DESPUÉS. Se mide por el HECHO de la fila
        // (`birthValue() === 0`), nunca por el eje del catálogo: ver el docblock de la clase.
        $order->loadMissing('adjustments');
        $governedLines = [];   // addonId => OrderItem
        $frozenLines = [];     // addonId => OrderItem (nacidas con el pedido: solo lectura para el cliente)
        foreach ($item->children as $child) {
            if ($child->isCancelled()) {
                continue;
            }
            $key = (int) $child->ticket_type_id;
            if (LineFacts::forItem($order, $child)->birthValue() === 0) {
                $governedLines[$key] = $child;
            } else {
                $frozenLines[$key] = $child;
            }
        }

        $moves = [];
        $blocked = [];
        $delta = 0;

        foreach ($desired as $addonId => $rawQty) {
            $addonId = (int) $addonId;
            $addon = $offerable->get($addonId);

            if ($addon === null) {
                // No ofrecido, fuera de plazo o de otra fase: no se toca y se DICE.
                $blocked[] = ['addon_id' => $addonId, 'reason' => 'not_offerable'];

                continue;
            }
            if (isset($frozenLines[$addonId])) {
                // Se compró al reservar: es del parque, no del cliente (R0).
                $blocked[] = ['addon_id' => $addonId, 'reason' => 'sold_at_booking'];

                continue;
            }

            $pivot = $addon->pivot;
            $current = $governedLines[$addonId] ?? null;
            // La MISMA autoridad de cantidad que la compra: tope del enganche incluido.
            $target = AddonResolver::effectiveQuantity($pivot, max(0, (int) $rawQty), (int) $item->quantity);
            $from = $current !== null ? (int) $current->quantity : 0;

            if ($target === $from) {
                continue; // R4: idempotente.
            }

            $moved = $this->write($item, $order, $addon, $current, $from, $target, $actor);
            if ($moved === null) {
                $blocked[] = ['addon_id' => $addonId, 'reason' => 'unpriced'];

                continue;
            }

            $delta += $moved;
            $moves[] = ['addon_id' => $addonId, 'name' => (string) $addon->tr('name'), 'from' => $from, 'to' => $target];
        }

        return new PostFormAddonChanges($moves, $blocked, $delta);
    }

    /**
     * Escribe UN gesto y devuelve el delta en céntimos, o `null` si no se pudo tarificar.
     *
     * ⚠️ **La fila y su hecho son una escritura INDIVISIBLE**: separarlos es exactamente el modo de
     * fallo que rompe `I1` —el editor del panel ya lo tuvo—, así que viven en el mismo método y no
     * hay otra puerta.
     *
     * ⚠️ **No se llama a `AddonResolver::resolve()`**, y es deliberado: aquél **LANZA** cuando un
     * complemento ofrecido no tiene precio ese día, y aquí eso tumbaría el guardado entero del
     * post-form —edades y nombres incluidos— por un hueco de tarifas. `MixedPartySurcharge` ya
     * resolvió esto al revés y lo dejó escrito: *no poder tarificar es una AUSENCIA*. Lo que sí se
     * comparte es la autoridad de la cantidad (`effectiveQuantity`) y la de lo gratis (`freeUnits`).
     */
    private function write(OrderItem $item, Order $order, TicketType $addon, ?OrderItem $current, int $from, int $target, User $actor): ?int
    {
        // R2 · la línea conserva su `unit_price`: subir de 2 a 3 cobra la tercera al precio de la
        // LÍNEA, no al de hoy. Solo una línea NUEVA se tarifica a hoy (§4.11 de la hora extra: los
        // complementos se tarifican al día de la compra, no al de la visita).
        $unit = $current !== null
            ? (int) $current->unit_price
            : $this->rates->priceCents($addon, Carbon::today());

        if ($unit === null) {
            return null;
        }

        // RETIRAR: `markCancelled()` y NINGÚN hecho. El libro emite su propio movimiento de
        // cancelación desde `chargedSubtotalCents()`, que no mira `cancelled_at`; escribir además un
        // `recordEdit` restaría DOS veces y rompería `I1` e `I3`.
        if ($target === 0) {
            $current?->markCancelled($actor);

            return 0;
        }

        $free = AddonResolver::freeUnits($addon->pivot, $target);
        $newCharged = max(0, $target - $free) * $unit;

        if ($current === null) {
            $child = $item->children()->create([
                'order_id' => $item->order_id,
                'ticket_type_id' => $addon->getKey(),
                // Neutro al aforo: un complemento de venta posterior no puede OCUPAR (§4.3·5), y esa
                // prohibición es la mitad del coste que esta feature se ahorra.
                'slot_id' => null,
                'quantity' => $target,
                'free_quantity' => $free,
                'unit_price' => $unit,
                'seats' => 0,
                'event_data' => null,
            ]);
            $this->recordMove($order, $child, $newCharged, $addon, 0, $target, $actor);

            return $newCharged;
        }

        $oldCharged = $current->chargedSubtotalCents();
        $current->forceFill(['quantity' => $target, 'free_quantity' => $free])->save();
        // ⚠️ El «desde» se recibe por parámetro y NO de `getOriginal()`: `save()` sincroniza los
        // originales, así que leerlo después devolvería el valor NUEVO y el libro rotularía
        // «Cubo de refrescos: 2 → 2». Lo cazó el caso del rótulo, no una revisión.
        $this->recordMove($order, $current, $newCharged - $oldCharged, $addon, $from, $target, $actor);

        return $newCharged - $oldCharged;
    }

    /**
     * El HECHO de la gestión, atado SIEMPRE a la hija.
     *
     * ⚠️⚠️ **Nunca al principal**, aunque las cuatro identidades del libro cerrarían igual: son
     * SUMAS y por construcción no ven una permuta entre líneas — medido, atarlo al principal hace
     * que el panel ofrezca devolver el importe de un extra que nadie pagó online.
     *
     * ⚠️ El contexto es `changes.quantity_change`, **no `addon_change`**: medido,
     * `MovementLabel::edit()` no lee la clave `removed` ni las bajadas, así que `addon_change` daría
     * «Cambios en Cumpleaños Jump» para un −12,00 €. Con `quantity_change` sobre la hija el libro ya
     * sabe decir «Cubo de refrescos: 3 → 2». (La prohibición de `changes.*` de `cumple-mixto.md` T4
     * es del ajuste del CRÉDITO mixto y por otra razón: allí haría reconstruir un original que no
     * existe. Aquí no hay crédito.)
     */
    private function recordMove(Order $order, OrderItem $child, int $delta, TicketType $addon, int $from, int $to, User $actor): void
    {
        if ($delta === 0) {
            // `Order::recordEdit()` LANZA con delta cero, y un complemento a 0 € es configuración
            // válida: la línea sigue con `nac = 0`, así que no hay hecho que escribir.
            return;
        }

        $order->recordEdit($child, $delta, $actor, 'postform_addon', [
            'changes' => ['quantity_change' => ['old' => $from, 'new' => $to]],
        ]);
    }

    /**
     * @param  array<int,int>  $desired
     * @return list<array{addon_id:int, reason:string}>
     */
    private function blockAll(array $desired, string $reason): array
    {
        return array_values(array_map(
            static fn (int $id): array => ['addon_id' => $id, 'reason' => $reason],
            array_map('intval', array_keys($desired)),
        ));
    }
}
