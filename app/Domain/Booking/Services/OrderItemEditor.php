<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\ItemActionOutcome;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\AuditLogger;
use App\Notifications\OrderItemModified;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * La EDICIÓN de un ítem ya comprado desde el panel — la orquestación de
 * dinero/aforo que vivía en `ViewOrder` (extracción 4b del desmontaje,
 * `docs/specs/desmontar-view-order.md` §9.6).
 *
 * Dos mitades. La PURA (sub-paso A): los validadores de destino (producto,
 * cantidad, complementos, franja) y las lecturas sobre las que deciden —
 * devuelven la RAZÓN estructurada de bloqueo, la misma clave que la capa de
 * entrega audita (`orders.item_edit_blocked`) y traduce al operador, o
 * `null` si pasa; sin efectos, y por eso se prueban DIRECTAMENTE. Y las
 * OPERACIONES: `changeSlot()` (sub-paso C) y `edit()` (sub-paso F), dueñas
 * de la transacción de aforo y orquestadoras de lo que viene después.
 *
 * ⚠️ **UN solo punto de lock**: las dos operaciones mutan a través de
 * `withZoneDayLock()`, que abre la transacción y toma `ZoneDaySlotLock`
 * como PRIMERA sentencia (`AFORO-01`/`AFORO-05`). Así el escenario
 * `panel-edit` de `purchase:verify-oversell` —que conduce `changeSlot()`—
 * vigila el lock de las DOS; un lock retirado en `edit()` no sería
 * invisible. Por eso este fichero está en el `CRITICAL_RE` del `pre-push`.
 *
 * La topología transaccional es la de la spec §4.3 y NO se «normaliza»: las
 * guardas van ANTES de abrir transacción alguna; la transacción contiene
 * SOLO aforo/mutación; el audit de éxito, la secuencia financiera (cada
 * paso con su propia transacción corta en `Order`) y el email van DESPUÉS
 * del commit. Envolverlo todo en una transacción sostendría el lock de
 * zona/día durante el email y metería el rastro en alcance de rollback
 * (`PAY-05`).
 *
 * Devuelve un `ItemActionOutcome`: la página traduce el RECHAZO (audit
 * `orders.item_edit_blocked` + aviso) y el éxito a su toast; lo que se
 * persiste —audit de éxito, ajustes, email— se escribe aquí.
 */
class OrderItemEditor
{
    public function __construct(
        private ItemRescheduleOffer $rescheduleOffer,
        private OperatingSchedule $schedule,
        private ProductAvailability $productWindow,
        private SlotAvailability $slotAvailability,
        private PackAvailability $packAvailability,
        private ZoneDaySlotLock $zoneDayLock,
        private OrderItemEventDataWriter $eventData,
        private ItemEditPricing $pricing,
        private MixedPartySurcharge $mixedParty,
        private AgeFamilySealer $sealer,
        private AddonDateReconciler $addonDates,
    ) {}

    // ─── Operaciones ────────────────────────────────────────────────────────

    /**
     * Mueve un ítem a otra franja de su zona, sin tocar el precio (sub-fase
     * 7.2e.2 del origen, decisión #159). Un cambio de fecha que RE-TARIFICA
     * (`PAY-18`) no entra por aquí: el despachador de la página lo enruta a
     * `edit()`, que sabe cobrar y acreditar.
     *
     * Defense in depth, en este orden:
     *  1. Permiso `orders.edit_item` — re-exigido AQUÍ, en el punto de
     *     ejecución, sea quien sea el llamante (`SEC-04`).
     *  2. `editItemBlockedReason` con fila fresca.
     *  3. Optimistic lock contra `updated_at` (el token que el modal envió).
     *  4. La franja elegida: existe, misma zona, no cerrada, no pasada,
     *     dentro del horizonte, parque abierto, ventana del producto
     *     (`validateNewSlot`).
     *  5. Transacción bajo `withZoneDayLock` (TODA la zona/día del destino:
     *     serializa contra compras y otras ediciones que pudieran agotar
     *     plazas mientras procesamos), lock del ítem, y el aforo revalidado
     *     DENTRO del lock **EXCLUYENDO la huella propia** (`AFORO-06`): un
     *     pack o entrada multi-franja cuya ventana SOLAPA con la del destino
     *     (recolocar media hora) se contaría a sí mismo y se bloquearía
     *     siempre. Mantener la MISMA franja es un no-op que no consume aforo.
     *
     * Después del commit: el audit `orders.item_slot_changed`, los datos del
     * evento si venían en el mismo guardado (con el token ya refrescado —
     * el `save` del slot movió el `updated_at`—, y su rechazo auditado como
     * siempre lo estuvo), y UN email consolidado al cliente.
     *
     * `insufficient_capacity_at_save` es también la razón cuando el ítem
     * resultó cancelado entre la capa 2 y el lock: se conserva tal cual.
     *
     * @param  array<string,mixed>|null  $eventData  los datos del evento del formulario, o `null` si
     *                                               no aplican (no es pack, sin campos, sin permiso)
     */
    public function changeSlot(
        Order $order,
        OrderItem $item,
        string $newDate,
        string $newTime,
        string $optimisticToken,
        ?array $eventData,
        ?User $by,
    ): ItemActionOutcome {
        // Capa 1: permiso.
        if (! ($by?->hasPermission('orders.edit_item') ?? false)) {
            return ItemActionOutcome::blocked('permission_denied');
        }

        // Capa 2: revalidar bloqueo del item con fila fresca.
        $reason = $order->editItemBlockedReason($item);
        if ($reason !== null) {
            return ItemActionOutcome::blocked($reason);
        }

        // Capa 3: optimistic.
        $currentToken = (string) ($item->updated_at?->getTimestamp() ?? '');
        if ($optimisticToken === '' || $optimisticToken !== $currentToken) {
            return ItemActionOutcome::blocked('stale_item_version');
        }

        // Capa 4: validar el slot elegido.
        $newSlot = $this->resolveSlotForItem($item, $newDate, $newTime);
        $validationReason = $this->validateNewSlot($item, $newSlot);
        if ($validationReason !== null) {
            return ItemActionOutcome::blocked($validationReason);
        }

        $oldSlot = $item->slot;
        $oldLabel = self::humanSlotLabel($oldSlot);
        $newLabel = self::humanSlotLabel($newSlot);

        // El plan de complementos APLICADO sale del lock por referencia: su dinero se escribe
        // POST-COMMIT (`#417`), y para eso hace falta saber qué se aplicó de verdad ahí dentro —
        // no lo que se previó fuera.
        $appliedDatePlan = null;

        // Capa 5: txn bajo el lock de zona/día del destino + revalidar aforo. Devuelve `true`, o el
        // MOTIVO del bloqueo (`false` conserva su significado histórico: sin plazas / cancelado).
        $committed = $this->withZoneDayLock($newSlot, function ($lockedSlots) use ($item, $newSlot, &$appliedDatePlan): bool|string {
            /** @var OrderItem $locked */
            $locked = OrderItem::query()->lockForUpdate()->findOrFail($item->id);

            // Re-check defensivo: el item podría haber sido cancelado entre la capa 2 y este lock.
            // `markCancelled` es terminal.
            if ($locked->isCancelled()) {
                return false;
            }

            // Si el item YA está en este slot (caso defensivo — el despachador debería filtrarlo
            // antes de entrar), mantenerlo es no-op y no consume aforo nuevo: commit sin validar
            // capacidad (#164 del origen).
            if ($locked->slot_id === $newSlot->id) {
                return true;
            }

            // La HORA EXTRA (`specs/hora-extra.md` §4.4·3, borde 4): mover el padre MUEVE a sus
            // hijas que ocupan — la familia aterriza entera en el destino o no se mueve nada. La
            // huella propia excluida es la de TODA la familia (borde 1): con un solo id, la edición
            // competiría contra su propia hora extra.
            $family = $this->liveOccupyingChildren($locked);
            $excludeIds = array_merge([(int) $locked->id], $family->pluck('id')->map(fn ($id) => (int) $id)->all());

            // ⚠️⚠️ **PRIMERO se decide QUIÉN sobrevive al día nuevo, y DESPUÉS se valida** (
            // `specs/hora-extra.md` §9.8·H1, `#417`). El orden es la propiedad: con la validación
            // delante, una reserva no se podía mover a un día en que su hora extra **no se vende** si
            // la franja de aterrizaje estaba llena — bloqueada por un aforo que nadie iba a consumir,
            // porque esa hija se retiraba de todas formas. Medido antes de invertirlo.
            //
            // ▶ Desde la T3 de §10 el plan va también delante del **aforo del padre**, por lo mismo:
            // los minutos que una extensión retirada iba a ocupar no pueden contar contra el cupo.
            $datePlan = $this->addonDates->plan($locked, $newSlot->date);
            $surviving = $datePlan->survivingChildIds;

            // La HORA EXTRA DE UN PACK (§10.3): mover la fiesta la mueve ALARGADA, así que lo que se
            // revalida es su ventana resultante — la de después del plan, no la de antes.
            $stayMinutes = $this->resultingStayMinutes($locked, [], collect(), $surviving);

            // Aforo del padre revalidado DENTRO del lock, EXCLUYENDO la huella familiar (AFORO-06 + borde 1).
            $available = $locked->ticketType?->isPack()
                ? $this->packAvailability->availableGuestsFor($newSlot, $locked->ticketType, [], $locked->id, $stayMinutes)
                : $this->slotAvailability->availableFor($newSlot, $locked->ticketType?->duration_min, [], $excludeIds);
            if ($available < (int) $locked->seats) {
                return false; // → `insufficient_capacity_at_save`, decidido tras la txn.
            }

            $resulting = $family
                ->filter(fn (OrderItem $child): bool => in_array((int) $child->id, $surviving, true))
                ->map(fn (OrderItem $child): array => [
                    'type' => $child->ticketType,
                    'seats' => (int) $child->seats,
                ])->all();
            [$childSlot, $familyReason] = $this->landOccupyingFamily($lockedSlots, $locked->ticketType, $newSlot, $resulting, $excludeIds);
            if ($familyReason !== null) {
                return $familyReason;
            }

            // El sello de condiciones se RE-PRECIA para el día nuevo en el MISMO `forceFill` que
            // mueve la franja (`specs/cumple-mixto.md` §21.4): si fuera en el post-commit, la
            // reconciliación —que relee la fila bloqueada— derivaría de un sello del día viejo.
            $locked->forceFill([
                'slot_id' => $newSlot->id,
                // El HECHO de la ventana con la que la fiesta aterriza en su día nuevo (§10.3.2):
                // exactamente el número con el que se acaba de validar el cupo.
                'extra_minutes' => $stayMinutes,
            ] + $this->sealUpdateFor($locked, $locked->ticketType, $newSlot))->save();

            // La MUTACIÓN de los complementos va dentro del lock —retirar es aforo— y su DINERO
            // fuera, tras el commit (`#417`, §9.8·H3: la doctrina §4.3 de este mismo fichero).
            //
            // ⚠️ Va ANTES del re-aterrizaje, igual que en `edit()`: con el orden al revés el filtro
            // de abajo quedaba REDUNDANTE —`applyMutations()` le quitaba la franja después— y lo
            // dijo el arnés, que no conseguía matar esa línea. *Una guarda que otro paso repara a
            // continuación no protege nada: solo esconde de qué depende el resultado.*
            $this->addonDates->applyMutations($datePlan, $locked);
            $appliedDatePlan = $datePlan;

            // Solo las SUPERVIVIENTES aterrizan. `$family` se calculó antes de retirar, así que
            // sigue teniéndolas en memoria: el filtro es lo único que impide darle franja —y plazas—
            // a una línea que se acaba de cancelar.
            foreach ($family as $child) {
                if (in_array((int) $child->id, $surviving, true)) {
                    $child->forceFill(['slot_id' => $childSlot?->id])->save();
                }
            }

            return true;
        });

        if ($committed !== true) {
            return ItemActionOutcome::blocked(is_string($committed) ? $committed : 'insufficient_capacity_at_save');
        }

        // Audit log del cambio de slot.
        AuditLogger::log(
            action: 'orders.item_slot_changed',
            target: $order,
            payload: [
                'order_code' => $order->code,
                'order_item_id' => $item->id,
                'ticket_type_id' => $item->ticket_type_id,
                'from_slot_id' => $oldSlot?->id,
                'from_date' => $oldSlot?->date?->toDateString(),
                'from_time' => $oldSlot?->start_time,
                'to_slot_id' => $newSlot->id,
                'to_date' => $newDate,
                'to_time' => $newTime,
            ],
        );

        // Los datos del evento en el mismo guardado, tras mover el slot: el token se refresca
        // (el save bumpeó `updated_at`). Su rechazo se audita como siempre lo estuvo en esta ruta
        // (solo la versión rancia y los obligatorios ausentes); no cambia el desenlace del slot.
        $eventDataChanged = $eventData !== null
            && $this->saveEventDataAfterMutation($order, $item, $eventData, $by);

        // Email consolidado de cambio de slot (+ event_data si aplica).
        $changes = ['slot_change' => ['old' => $oldLabel, 'new' => $newLabel]];
        if ($eventDataChanged) {
            $changes['event_data_change'] = true;
        }

        $order->notifyCustomer(new OrderItemModified(
            order: $order->fresh(),
            item: $item->fresh(),
            changes: $changes,
        ));

        // El DINERO de los complementos re-tarificados (`#417`): POST-COMMIT y en su propia
        // transacción corta, como el resto de la secuencia financiera (§4.3) — la mutación ya se
        // confirmó dentro del lock. ⚠️ Las RETIRADAS no pasan por aquí: no llevan hecho, porque el
        // libro emite su `−fila` solo (§9.6 de `hora-extra.md`).
        if ($appliedDatePlan !== null) {
            $this->addonDates->applyMoney(
                $appliedDatePlan,
                $order->fresh(['items.children', 'items.ticketType', 'adjustments']) ?? $order,
                $by,
            );
        }

        // El suplemento de fiesta MIXTA se re-tarifica al mover la fecha (`specs/cumple-mixto.md`
        // §12): la diferencia entre packs sale del catálogo de ESE día, así que mover el día es
        // mover el importe — el mismo criterio que `PAY-18` aplica al precio del propio ítem.
        // Es un cambio del HECHO, no de la configuración, y por eso sí reconcilia.
        // POST-COMMIT y fuera del lock de zona/día: abre su propia transacción corta (§4.3).
        // ⚠️ Va DESPUÉS del reconciliador de complementos y no antes: aquél excluye los portadores
        // justamente para que sea ÉSTE quien los gobierne (§9.8·H2).
        if ($by !== null) {
            $this->mixedParty->reconcile($item->fresh(), $by, 'panel_slot_change');
        }

        return ItemActionOutcome::done(['event_data_changed' => $eventDataChanged]);
    }

    /**
     * Lo que hay que escribir en `age_family_seal` cuando una reserva cambia de pack o de día —
     * `[DECIDIDO owner, 2026-08-31]`, `specs/cumple-mixto.md` §21.4 y §21.8 (Q2):
     *  - **pack nuevo** → sello NUEVO desde el catálogo de hoy para el día efectivo («solo cambia
     *    de condiciones lo que cambia de producto»);
     *  - **solo día nuevo** → el MISMO sello re-preciado: la familia y los tramos con los que compró
     *    se conservan y cada precio pasa al del día destino, que es lo que `PAY-18` ya hace con el
     *    precio de la propia fiesta. Sin sello previo no se inventa uno: el silencio persiste;
     *  - **ni uno ni otro** (cantidad, complementos, misma fecha con otra hora) → nada.
     *
     * Devuelve las columnas a añadir al `forceFill` de la mutación, o `[]`. Va dentro de la misma
     * transacción y del mismo lock a propósito: es lo que impide que la reconciliación post-commit
     * lea condiciones que ya no son las de la fila.
     *
     * @return array<string, mixed>
     */
    private function sealUpdateFor(OrderItem $locked, ?TicketType $newType, Slot $slot): array
    {
        if ($newType === null) {
            return [];
        }
        if ((int) $newType->id !== (int) $locked->ticket_type_id) {
            return ['age_family_seal' => $this->sealer->build($newType, $slot->date)?->toArray()];
        }

        $seal = $locked->ageFamilySeal();
        if ($seal === null || $seal->pricedOn === $slot->date->toDateString()) {
            return [];
        }

        return ['age_family_seal' => $this->sealer->reprice($seal, $slot->date)->toArray()];
    }

    /**
     * EL punto único de lock de este servicio: abre la transacción y toma el lock de TODA la
     * zona/día de `$slot` como PRIMERA sentencia (`AFORO-01`/`AFORO-05`), y ejecuta `$body` con el
     * lock en mano. `$slot` se cargó ANTES de abrir la transacción: su zona y su fecha son
     * literales, que es lo que la receta exige. Nada más entra en esta transacción: ni audits de
     * éxito, ni dinero, ni email — eso va después del commit (spec §4.3).
     *
     * El cuerpo recibe las franjas BLOQUEADAS (la hora extra busca «la franja siguiente» entre
     * ellas, `AddonOccupancy::childSlotAmong` — una consulta aparte leería otro snapshot).
     *
     * @template T
     *
     * @param  Closure(Collection): T  $body
     * @return T
     */
    private function withZoneDayLock(Slot $slot, Closure $body): mixed
    {
        return DB::transaction(function () use ($slot, $body): mixed {
            $lockedSlots = $this->zoneDayLock->acquire([(int) $slot->zone_id], [$slot->date->toDateString()]);

            return $body($lockedSlots);
        });
    }

    /**
     * Los datos del evento guardados DESPUÉS de una mutación del ítem, con el token refrescado.
     * Devuelve si hubo diff (para que el email consolidado cite `event_data_change`). El rechazo
     * se audita solo por versión rancia y obligatorios ausentes — exactamente lo que auditaba la
     * variante consolidada de la página; permiso, no-pack y sin-campos salen en silencio.
     *
     * @param  array<string,mixed>  $eventData
     */
    private function saveEventDataAfterMutation(Order $order, OrderItem $item, array $eventData, ?User $by): bool
    {
        $fresh = $item->fresh();
        $outcome = $this->eventData->save(
            $order,
            $fresh,
            $eventData,
            (string) ($fresh?->updated_at?->getTimestamp() ?? ''),
            $by,
        );

        if ($outcome->isBlocked()) {
            if (in_array($outcome->reason, ['stale_version', 'required_missing'], true)) {
                $this->eventData->auditBlocked($order, $fresh, $outcome->reason, $outcome->extra);
            }

            return false;
        }

        return $outcome->changed;
    }

    /**
     * Etiqueta humana de una franja ("dd/mm/YYYY HH:MM"), o "—" si no hay franja (ítems sin
     * slot). ⚠️ No es solo presentación: viaja en el email, en el audit y en el CONTEXTO de los
     * ajustes de dinero (`slot_change.old/new`), que se persiste — por eso vive en el dominio y no
     * en la página.
     */
    public static function humanSlotLabel(?Slot $slot): string
    {
        if ($slot === null) {
            return '—';
        }

        return $slot->date->format('d/m/Y').' '.Str::substr($slot->start_time, 0, 5);
    }

    /**
     * La EDICIÓN unificada de un ítem (sub-fase 7.2e.3 del origen, decisión
     * #167): producto y/o cantidad y/o franja y/o complementos y/o datos del
     * evento, en UN guardado → UN audit (`orders.item_edited`) → los
     * movimientos de dinero que correspondan → UN email (`OrderItemModified`).
     * Es el único camino que TOCA DINERO: el despachador de la página lo elige
     * cuando cambia el producto, la cantidad, algún complemento o la TARIFA
     * del día (`PAY-18`: mover la fecha re-tarifica).
     *
     * Las cuatro fases de la spec §4.3, y su frontera transaccional es el
     * diseño, no un accidente:
     *  1. **Guardas, ANTES de abrir transacción alguna**: permiso
     *     `orders.edit_item` (`SEC-04`) · `editItemBlockedReason` con fila
     *     fresca · optimistic lock · el producto nuevo existe y encaja
     *     (`validateItemEditTarget`; los complementos HUÉRFANOS del producto
     *     nuevo dejan de bloquear si se QUITAN en este mismo guardado) · la
     *     franja (`validateNewSlot`, contra el producto NUEVO) · el precio del
     *     día (`ItemEditPricing`: sin tarifa ese día no se edita a un importe
     *     indefinido) · los complementos (`validateAddonEdits` y su tarifa).
     *  2. **UNA transacción SOLO de aforo/mutación**, bajo `withZoneDayLock`
     *     (`ZoneDaySlotLock` como primera sentencia): lock del ítem,
     *     revalidación de cupo EXCLUYENDO la huella propia (`AFORO-06`: si no,
     *     crecer en la propia franja se contaría a sí mismo), `forceFill` del
     *     ítem, los complementos (subir → `forceFill`; quitar → `markCancelled`
     *     SIN reembolso; añadir → child nuevo, y un miembro de un grupo de
     *     elección SUSTITUYE al presente) y la RE-ESCALA de los per-invitado al
     *     nuevo nº de invitados (M4: si no, un per-invitado de pago infra-cobra
     *     al subir y sobre-cobra al bajar).
     *  3. **Tras el commit, la secuencia financiera** — cada paso abre su
     *     PROPIA transacción corta en `Order`, y los buckets leen estado
     *     committed entre ellas: el audit de éxito; por SIGNO del diff de
     *     Tab 1, SUBIDA → `recordEdit` con delta positivo (cobro en puerta, sin Redsys) o
     *     BAJADA → `recordEdit(−Δ)` (T1 del libro: UNA fila con su delta entero;
     *     `#225` D8 «bajar = solo cancelar», sin auto-reembolso; lo que queda
     *     por debajo de lo cobrado online aflora como pendiente de
     *     devolución); el cobro de cada complemento añadido/subido ATADO a
     *     su child (`#170`: movimiento separado, no se netea); y el delta de
     *     cada per-invitado re-escalado con el MISMO criterio de signo.
     *     ⚠️ La REST de un reembolso no puede ir dentro de la txn de aforo, y
     *     por eso esta fase va después: la mutación se confirma PRIMERO.
     *  4. Los datos del evento (token refrescado) y el email consolidado.
     *
     * Devuelve `blocked(reason[, extra])` con la misma clave que la página
     * audita, o `done(extra_due_cents, reduced_cents, item_edit_context)` para
     * el toast de éxito.
     *
     * @param  array{edits: array<int, array{child_id:int, quantity:int}>, adds: array<int, array{ticket_type_id:int, quantity:int}>}  $addonEdits  la intención de complementos, YA normalizada por la página
     * @param  array<string,mixed>|null  $eventData  los datos del evento del formulario, o `null` si no aplican
     * @param  bool  $belowMinimum  D7 (`specs/cumple-mixto.md` §23.4): el operador ACTIVÓ el
     *                              interruptor de bajar del mínimo del pack. Solo surte efecto con
     *                              el permiso `orders.edit_item_below_minimum`; sin él, el mínimo
     *                              sigue mandando aunque el interruptor venga puesto.
     */
    public function edit(
        Order $order,
        OrderItem $item,
        string $newDate,
        string $newTime,
        bool $slotChanged,
        int $newProductId,
        int $newQty,
        ?array $eventData,
        array $addonEdits,
        string $optimisticToken,
        ?User $by,
        bool $belowMinimum = false,
    ): ItemActionOutcome {
        // Capa 1: permiso.
        if (! ($by?->hasPermission('orders.edit_item') ?? false)) {
            return ItemActionOutcome::blocked('permission_denied');
        }

        // D7 (`[DECIDIDO owner]`, §18.3): «al final él decide sobre su producto» — pero el mínimo
        // existe por una razón de negocio, así que la excepción exige un permiso PROPIO (revocable
        // por rol sin tocar `orders.edit_item`) y se REGISTRA, no se silencia. Sin el permiso, el
        // interruptor se ignora y el mínimo rechaza como siempre (`SEC-04`: se decide aquí, en el
        // punto de ejecución, no en la visibilidad del formulario).
        $belowMinimum = $belowMinimum && ($by?->hasPermission('orders.edit_item_below_minimum') ?? false);

        // Capa 2: bloqueo del item con fila fresca.
        $reason = $order->editItemBlockedReason($item);
        if ($reason !== null) {
            return ItemActionOutcome::blocked($reason);
        }

        // Capa 3: optimistic.
        $currentToken = (string) ($item->updated_at?->getTimestamp() ?? '');
        if ($optimisticToken === '' || $optimisticToken !== $currentToken) {
            return ItemActionOutcome::blocked('stale_item_version');
        }

        $oldType = $item->ticketType;

        // Capa 4a: resolver el producto nuevo.
        $newType = $newProductId === (int) $item->ticket_type_id
            ? $oldType
            : TicketType::find($newProductId);
        if ($newType === null) {
            return ItemActionOutcome::blocked('invalid_product');
        }
        $productChanged = (int) $newType->id !== (int) $item->ticket_type_id;

        // Capa 4b: validar producto + cantidad. El huérfano se trata aparte para listar los
        // complementos afectados en el banner: deja de bloquear si se QUITA (cantidad 0) en este
        // mismo guardado (sub-fase 7.2e.4, #170); solo bloquea si quedan huérfanos sin quitar.
        $targetReason = $this->validateItemEditTarget($item, $newType, $newQty, $belowMinimum);
        if ($targetReason !== null) {
            if ($targetReason === 'orphan_addons') {
                $orphans = $this->orphanAddonsForNewProduct($item, $newType);
                $removingIds = $this->addonChildIdsBeingRemoved($addonEdits);
                $unresolved = array_diff_key($orphans, array_flip($removingIds));
                if ($unresolved !== []) {
                    return ItemActionOutcome::blocked('orphan_addons', [
                        'orphan_addon_item_ids' => array_keys($unresolved),
                        'unresolved' => $unresolved,
                    ]);
                }
                // Todos los huérfanos se están quitando → continuar.
            } else {
                return ItemActionOutcome::blocked($targetReason);
            }
        }

        // Capa 4c: resolver slot efectivo (cambiado o el actual).
        if ($slotChanged) {
            $effectiveSlot = $this->resolveSlotForItem($item, $newDate, $newTime);
            $validationReason = $this->validateNewSlot($item, $effectiveSlot, $newType);
            if ($validationReason !== null) {
                return ItemActionOutcome::blocked($validationReason);
            }
        } else {
            $effectiveSlot = $item->slot;
        }
        if ($effectiveSlot === null) {
            return ItemActionOutcome::blocked('invalid_slot_selection');
        }

        // Capa 4d: precio unitario nuevo + diff (la misma aritmética que la vista previa).
        // `new === null` ⇒ producto cambiado sin tarifa de catálogo ese día → no editamos a un
        // importe indefinido.
        $pricing = $this->pricing->computeEditPricing($item, (int) $newType->id, $newQty, $effectiveSlot->date->toDateString());
        if ($pricing['new'] === null) {
            return ItemActionOutcome::blocked('product_unavailable_on_date');
        }
        $newUnit = (int) $pricing['unit'];
        $newSeats = $newQty * (int) ($newType->seats_per_unit ?? 1);

        $oldSlot = $item->slot;
        $oldQty = (int) $item->quantity;
        $oldUnit = (int) $item->unit_price;
        $diff = (int) $pricing['diff'];

        // Capa 4e (7.2e.4, #170): validar + tarificar los cambios de complementos contra el
        // producto NUEVO.
        $addonReason = $this->validateAddonEdits($item, $newType, $addonEdits['edits'] ?? [], $addonEdits['adds'] ?? []);
        if ($addonReason !== null) {
            return ItemActionOutcome::blocked($addonReason);
        }
        $addonPricing = $this->pricing->computeAddonPricing(
            $item, $newType, $addonEdits['edits'] ?? [], $addonEdits['adds'] ?? [], $effectiveSlot->date->toDateString(),
        );
        if ($addonPricing['error'] !== null) {
            return ItemActionOutcome::blocked($addonPricing['error']);
        }
        $addonUpcharge = (int) $addonPricing['upcharge'];
        $addonAddUnitPrices = $addonPricing['add_unit_prices'];
        $addonAddQuantities = $addonPricing['add_quantities'] ?? [];
        $addonAddFreeQuantities = $addonPricing['add_free_quantities'] ?? [];
        // El SELLO DEL MODO (`specs/hora-extra.md` §12, `#448`): viaja junto a la cantidad que
        // describe y desde la MISMA lectura del pivote, no desde `$offeredAddons` (§12.6.1).
        $addonAddQuantityModes = $addonPricing['add_quantity_modes'] ?? [];
        $addonCharges = $addonPricing['charges'] ?? [];
        // typeId → id del child creado en la txn (para atar el extra_due del add a SU child).
        $addonAddChildIds = [];

        // Grupo de elección de cada complemento del producto (para el CAMBIO de menú: añadir un
        // miembro de un grupo sustituye al miembro presente de ese mismo grupo).
        $offeredAddons = $newType->addons()->get()->keyBy('id');
        $addonGroupByTypeId = [];
        foreach ($offeredAddons as $addonOption) {
            $addonGroup = $addonOption->pivot->choiceGroup();
            if ($addonGroup !== null) {
                $addonGroupByTypeId[(int) $addonOption->id] = $addonGroup;
            }
        }

        // La HORA EXTRA (`specs/hora-extra.md` §4.4·3 + §4.6·5): la familia OCUPANTE que RESULTARÁ
        // de este guardado — las hijas vivas que no se cancelan aquí (con su cantidad subida si se
        // sube) más los añadidos de complementos que ocupan. De ella salen las dos reglas:
        //  · el borde 5 («no se quedan más de los que entran»): Σ cantidades ≤ cantidad NUEVA del
        //    padre — la mitad del tope de `AddonResolver` que la edición tiene que decir igual;
        //  · la validación de aterrizaje bajo el lock (más abajo), que mueve a toda la familia con
        //    el padre o no mueve nada.
        $editByChildId = [];
        foreach ($addonEdits['edits'] ?? [] as $edit) {
            $editByChildId[(int) $edit['child_id']] = (int) $edit['quantity'];
        }
        $resultingOccupying = []; // list<{type, qty, seats, child_id|null, type_id|null}>
        $stayingTotal = 0;
        foreach ($this->liveOccupyingChildren($item) as $child) {
            $edited = $editByChildId[(int) $child->id] ?? null;
            if ($edited === 0) {
                continue; // se cancela en este mismo guardado: deja de ocupar
            }
            $qty = ($edited !== null && $edited > (int) $child->quantity) ? $edited : (int) $child->quantity;
            $resultingOccupying[] = [
                'type' => $child->ticketType,
                'qty' => $qty,
                'seats' => AddonOccupancy::seats($child->ticketType, $qty),
                'child_id' => (int) $child->id,
                'type_id' => null,
            ];
            $stayingTotal += $qty;
        }
        foreach ($addonEdits['adds'] ?? [] as $add) {
            $addTypeId = (int) $add['ticket_type_id'];
            /** @var TicketType|null $addType */
            $addType = $offeredAddons->get($addTypeId);
            if ($addType === null || ! $addType->occupiesAfterParent()) {
                continue;
            }
            // El cinturón de §4.1 en la puerta del panel: un ocupante con configuración rota no se
            // añade (jamás degrada a neutro — sería vender sin ocupar).
            if (! $addType->hasSaneOccupancyConfig()) {
                return ItemActionOutcome::blocked('addon_incompatible_with_product');
            }
            $qty = (int) ($addonAddQuantities[$addTypeId] ?? $add['quantity']);
            $resultingOccupying[] = [
                'type' => $addType,
                'qty' => $qty,
                'seats' => AddonOccupancy::seats($addType, $qty),
                'child_id' => null,
                'type_id' => $addTypeId,
            ];
            $stayingTotal += $qty;
        }
        if ($stayingTotal > $newQty) {
            return ItemActionOutcome::blocked('addon_stay_exceeds_quantity', [
                'staying' => $stayingTotal,
                'entering' => $newQty,
            ]);
        }

        // Cambios estructurados para el email + audit.
        $changes = [];
        if ($slotChanged) {
            $changes['slot_change'] = [
                'old' => self::humanSlotLabel($oldSlot),
                'new' => self::humanSlotLabel($effectiveSlot),
            ];
        }
        if ($productChanged) {
            $changes['product_change'] = [
                'old' => $oldType?->tr('name') ?? '—',
                'new' => $newType->tr('name'),
            ];
        }
        if ($newQty !== $oldQty) {
            $changes['quantity_change'] = ['old' => $oldQty, 'new' => $newQty];
        }
        // ⚠️⚠️ **El cambio de PRECIO UNITARIO viaja como cambio estructurado desde `#150`** (D4/D3 de
        // `#146`). La re-tarificación (`PAY-18`) puede bajar el valor SIN tocar la cantidad, y
        // `itemOriginalOnlineCents` solo sabía reconstruir por cantidad: tras una bajada por fecha,
        // el tope de reembolso caía al precio YA re-tarificado (medido con los números del owner en
        // `#149`: pagó 40,00, se le debían 40,00 y el tope decía 30,00 — y con el pedido cancelado,
        // 10,00 quedaban ATRAPADOS sin vía de panel). Con el `old` en el contexto del ajuste, la
        // reconstrucción vuelve a saber lo que se cobró de verdad.
        if ($newUnit !== $oldUnit) {
            $changes['unit_price_change'] = ['old' => $oldUnit, 'new' => $newUnit];
        }
        if (($addonPricing['changes']['addon_change'] ?? null) !== null) {
            $changes['addon_change'] = $addonPricing['changes']['addon_change'];
        }

        // Capa 5: mutación atómica bajo el lock de zona/día con revalidación de aforo EXCLUYENDO la
        // huella propia del item (si no, un crecimiento en su propia franja se contaría a sí mismo
        // y se bloquearía). Devuelve `true`, o el MOTIVO del bloqueo (`false` conserva su
        // significado histórico: sin plazas / cancelado).
        $perGuestRescales = []; // child_id => ['delta'=>cents, 'old_qty'=>n, 'new_qty'=>n, 'name'=>str] (M4)
        // El plan de complementos APLICADO sale del lock por referencia: su dinero se escribe
        // POST-COMMIT, y hace falta saber qué se aplicó DENTRO, no lo que se previó fuera (`#417`).
        $appliedDatePlan = null;
        $committed = $this->withZoneDayLock($effectiveSlot, function ($lockedSlots) use ($item, $effectiveSlot, $newType, $newQty, $oldQty, $newUnit, $newSeats, $addonEdits, $addonAddUnitPrices, $addonAddQuantities, $addonAddFreeQuantities, $addonAddQuantityModes, $addonGroupByTypeId, $offeredAddons, $resultingOccupying, $by, &$addonAddChildIds, &$perGuestRescales, &$appliedDatePlan): bool|string {
            /** @var OrderItem $locked */
            $locked = OrderItem::query()->lockForUpdate()->findOrFail($item->id);
            if ($locked->isCancelled()) {
                return false;
            }

            // La huella excluida es la de TODA la familia (hora extra, borde 1 de §4.6): con solo
            // el id del padre, su edición competiría contra su propia hija en la franja siguiente.
            $familyIds = array_merge(
                [(int) $locked->id],
                $this->liveOccupyingChildren($locked)->pluck('id')->map(fn ($id) => (int) $id)->all(),
            );

            // ⚠️⚠️ **QUIÉN sobrevive al día nuevo se decide ANTES de validar NADA**
            // (`specs/hora-extra.md` §9.8·H1, `#417`): con el orden al revés, una reserva no se podía
            // mover a un día en que su hora extra **no se vende** si la franja de aterrizaje estaba
            // llena — bloqueada por un aforo que nadie iba a consumir. Medido antes de invertirlo.
            //
            // ▶ Desde la T3 de §10 el plan va también delante del **aforo del padre**, y por la misma
            // razón exacta: los minutos que una extensión retirada iba a ocupar no pueden contar
            // contra el cupo que se pide. El plan es lectura pura, así que adelantarlo no cambia nada
            // más.
            $datePlan = $this->addonDates->plan($locked, $effectiveSlot->date);
            $surviving = $datePlan->survivingChildIds;

            // La HORA EXTRA DE UN PACK (§10.3): la fiesta se revalida con la ventana que va a tener
            // DESPUÉS de este guardado, no con la que tenía.
            $stayMinutes = $this->resultingStayMinutes($locked, $addonEdits, $offeredAddons, $surviving, $addonAddQuantities);

            $available = $newType->isPack()
                ? $this->packAvailability->availableGuestsFor($effectiveSlot, $newType, [], $locked->id, $stayMinutes)
                : $this->slotAvailability->availableFor($effectiveSlot, $newType->duration_min, [], $familyIds);
            if ($available < $newSeats) {
                return false;
            }

            // Las hijas AÑADIDAS en este mismo guardado (`child_id === null`) no pasan por el plan:
            // se están comprando ahora, ya tarificadas al día nuevo por `ItemEditPricing`.
            $occupyingToLand = array_values(array_filter(
                $resultingOccupying,
                fn (array $c): bool => $c['child_id'] === null || in_array((int) $c['child_id'], $surviving, true),
            ));

            // La HORA EXTRA: la familia resultante ATERRIZA detrás de la posición nueva del padre
            // (producto nuevo = duración nueva = otra «franja siguiente»; día/hora nuevos, ídem) o
            // no se guarda nada — el borde 4 de §4.6, dentro del mismo lock.
            $childSlot = null;
            if ($occupyingToLand !== []) {
                [$childSlot, $familyReason] = $this->landOccupyingFamily(
                    $lockedSlots,
                    $newType,
                    $effectiveSlot,
                    array_map(fn (array $c): array => ['type' => $c['type'], 'seats' => $c['seats']], $occupyingToLand),
                    $familyIds,
                );
                if ($familyReason !== null) {
                    return $familyReason;
                }
            }

            // El sello de condiciones viaja en el MISMO `forceFill` que el pack y la franja
            // (`specs/cumple-mixto.md` §21.4): pack nuevo → sello nuevo; solo día nuevo → el mismo
            // sello re-preciado; ni uno ni otro → intacto. Nunca en el post-commit.
            $locked->forceFill([
                'ticket_type_id' => $newType->id,
                'quantity' => $newQty,
                'unit_price' => $newUnit,
                'seats' => $newSeats,
                'slot_id' => $effectiveSlot->id,
                // La HORA EXTRA DE UN PACK (§10.3.2): el HECHO de cuánto queda alargada esta fiesta,
                // en la misma sentencia que la mueve — el hermano de `seats`, que va dos líneas
                // arriba por lo mismo. Es EXACTAMENTE el número con el que se acaba de validar el
                // cupo: si se recalculara aquí, la validación y el hecho podrían separarse.
                'extra_minutes' => $stayMinutes,
            ] + $this->sealUpdateFor($locked, $newType, $effectiveSlot))->save();

            // Sub-fase 7.2e.4 (#170): complementos en la misma txn — neutros al aforo salvo la HORA
            // EXTRA, cuya fila lleva franja y plazas (validadas arriba con la familia). Subir
            // cantidad → forceFill; quitar (0) → markCancelled (sin refund, #170); añadir → nuevo
            // child enlazado al parent.
            // ⚠️⚠️ La línea del SUPLEMENTO de fiesta mixta no es un complemento que el operador
            // gobierne: es el reflejo de una edad que declaró el cliente. Aceptar aquí un cambio
            // suyo era aceptar un gesto que la reconciliación POST-COMMIT deshace en la MISMA
            // pulsación —medido: la línea se cancelaba y volvía a nacer, con dos correos al cliente
            // que se contradicen—. Va en el EDITOR y no solo en la pantalla porque esta es la puerta
            // por la que entra todo cambio de complementos (`specs/cumple-mixto.md` §12.4).
            $mixedPartyLines = $this->mixedParty->governedLineIds($locked);

            foreach ($addonEdits['edits'] ?? [] as $edit) {
                /** @var OrderItem|null $child */
                $child = OrderItem::query()->lockForUpdate()->find((int) $edit['child_id']);
                if ($child === null || (int) $child->parent_item_id !== (int) $locked->id || $child->isCancelled()) {
                    continue;
                }
                if (in_array((int) $child->id, $mixedPartyLines, true)) {
                    continue;
                }
                $q = (int) $edit['quantity'];
                if ($q === 0) {
                    $child->markCancelled($by);
                } elseif ($q > (int) $child->quantity) {
                    // Una hija que OCUPA (franja en la fila, el HECHO) recalcula sus plazas con la
                    // cantidad — el «gemelo silencioso» de §4.4·6: subir 1 → 3 dejando `seats`
                    // quieto son tres personas ocupando una plaza, sin carrera y sin fallo.
                    $seatsUpdate = $child->slot_id !== null && $child->ticketType !== null
                        ? ['seats' => AddonOccupancy::seats($child->ticketType, $q)]
                        : [];
                    $child->forceFill(['quantity' => $q] + $seatsUpdate)->save();
                }
            }
            foreach ($addonEdits['adds'] ?? [] as $add) {
                $typeId = (int) $add['ticket_type_id'];

                // CAMBIO de menú (grupo de elección): añadir un miembro de un grupo SUSTITUYE al que
                // estuviera presente de ese mismo grupo (soft-cancel; si era de pago queda pendiente
                // de reembolso por #170, si era el incluido gratis no hay nada que devolver).
                $group = $addonGroupByTypeId[$typeId] ?? null;
                if ($group !== null) {
                    foreach ($locked->children()->whereNull('cancelled_at')->get() as $present) {
                        if (($addonGroupByTypeId[(int) $present->ticket_type_id] ?? null) === $group) {
                            $present->markCancelled($by);
                        }
                    }
                }

                // La HORA EXTRA (§4.4·6): una hija que OCUPA nace con su franja y sus plazas — este
                // `create` era el punto de NACIMIENTO con literales que la primera revisión cazó
                // (un operador creaba una ocupación que el aforo no contaba, sin error y sin franja
                // que imprimir). La franja es la validada arriba con la familia; las plazas, la
                // MISMA cuenta que `AddonResolver` pone al vender.
                $addQty = (int) ($addonAddQuantities[$typeId] ?? $add['quantity']);
                $addType = $offeredAddons->get($typeId);
                $occupies = $addType !== null && $addType->occupiesAfterParent();

                $created = $locked->children()->create([
                    'order_id' => $locked->order_id,
                    'ticket_type_id' => $typeId,
                    'slot_id' => $occupies ? $childSlot?->id : null,
                    // Cantidad efectiva + unidades gratis las computó computeAddonPricing con la
                    // config del pivote (incluido/por-invitado), no el qty crudo del operador.
                    'quantity' => $addQty,
                    'free_quantity' => (int) ($addonAddFreeQuantities[$typeId] ?? 0),
                    'unit_price' => (int) ($addonAddUnitPrices[$typeId] ?? 0),
                    'seats' => $occupies ? AddonOccupancy::seats($addType, $addQty) : 0,
                    // El SELLO DEL MODO (`#448`): la unidad de `quantity`, escrita al nacer igual
                    // que en la venta pública. `null` si el complemento no cuelga de este producto
                    // —no lo gobierna ningún enganche—, que es SILENCIO y no `fixed`.
                    'addon_quantity_mode' => $addonAddQuantityModes[$typeId] ?? null,
                    'event_data' => null,
                ]);
                $addonAddChildIds[$typeId] = (int) $created->id;
            }

            // Las condiciones del día NUEVO, aplicadas a los complementos (`#417`): retirar lo que ese
            // día no se vende y mover el precio de lo que cambia. ⚠️ Va ANTES del re-aterrizaje a
            // propósito: una hija retirada ya no aparece en `liveOccupyingChildren()`, así que no se
            // le busca franja — reservarle plazas para soltarlas en la misma transacción sería el
            // gemelo silencioso de H1. Su DINERO va post-commit ({@see AddonDateReconciler}).
            $this->addonDates->applyMutations($datePlan, $locked);
            $appliedDatePlan = $datePlan;

            // La HORA EXTRA (borde 4): las hijas que ocupan y SIGUEN vivas se mudan CON el padre —
            // el producto nuevo (otra duración) o el día/hora nuevos cambian su «franja siguiente»,
            // que es la ya validada arriba. Sin esto, la hija se queda ocupando una franja de un
            // día en el que la familia ya no está, sin fallo y sin aviso.
            foreach ($this->liveOccupyingChildren($locked) as $child) {
                if ((int) $child->slot_id !== (int) $childSlot?->id && $childSlot !== null) {
                    $child->forceFill(['slot_id' => $childSlot->id])->save();
                }
            }

            // M4 (auditoría Fase 1): re-escalar los complementos PER-INVITADO al nuevo nº de
            // invitados. Su cantidad efectiva SIGUE al principal (AddonResolver::effectiveQuantity →
            // invitados); si no se re-escalan, un addon per-invitado DE PAGO INFRA-cobra al subir y
            // SOBRE-cobra (de forma invisible) al bajar. Recogemos el delta por child para
            // canalizarlo financieramente FUERA de la txn con el MISMO criterio que el principal.
            // Los children per-invitado están bloqueados en la UI → nunca llegan por `addonEdits`.
            if ($newQty !== $oldQty) {
                $pivotByAddonId = $newType->addons()->get()->keyBy('id');
                foreach ($locked->children()->whereNull('cancelled_at')->get() as $child) {
                    $pivot = $pivotByAddonId->get($child->ticket_type_id)?->pivot;
                    if ($pivot === null || ! $pivot->isPerGuest()) {
                        continue;
                    }
                    $oldChildQty = (int) $child->quantity;
                    $oldChildFree = (int) $child->free_quantity;
                    $newChildQty = AddonResolver::effectiveQuantity($pivot, 0, $newQty);
                    $newChildFree = AddonResolver::freeUnits($pivot, $newChildQty);
                    if ($newChildQty === $oldChildQty && $newChildFree === $oldChildFree) {
                        continue;
                    }
                    $oldCharged = max(0, $oldChildQty - $oldChildFree) * (int) $child->unit_price;
                    $newCharged = max(0, $newChildQty - $newChildFree) * (int) $child->unit_price;
                    $child->forceFill(['quantity' => $newChildQty, 'free_quantity' => $newChildFree])->save();
                    $perGuestRescales[(int) $child->id] = [
                        'delta' => $newCharged - $oldCharged,
                        'old_qty' => $oldChildQty,
                        'new_qty' => $newChildQty,
                        'name' => $child->ticketType?->tr('name') ?? ('#'.$child->id),
                    ];
                }
            }

            return true;
        });

        if ($committed !== true) {
            return ItemActionOutcome::blocked(is_string($committed) ? $committed : 'insufficient_capacity_at_save');
        }

        // D7: la excepción del mínimo se AUDITA solo cuando de verdad se usó — un `false` en cada
        // edición normal sería ruido que entierra la señal. `pack_min_qty` acompaña para que el
        // rastro diga de qué mínimo se bajó sin tener que reconstruir el catálogo de entonces.
        $packMin = $newType->isPack() ? (int) ($newType->min_qty ?? 1) : 1;
        $usedBelowMinimum = $belowMinimum && $newType->isPack() && $newQty < $packMin;

        // Audit del edit con el desglose de `changes`.
        AuditLogger::log(
            action: 'orders.item_edited',
            target: $order,
            payload: [
                'order_code' => $order->code,
                'order_item_id' => $item->id,
                'from_ticket_type_id' => $oldType?->id,
                'to_ticket_type_id' => $newType->id,
                'from_quantity' => $oldQty,
                'to_quantity' => $newQty,
                'from_unit_price' => $oldUnit,
                'to_unit_price' => $newUnit,
                'from_slot_id' => $oldSlot?->id,
                'to_slot_id' => $effectiveSlot->id,
                'price_diff_cents' => $diff,
                'addon_upcharge_cents' => $addonUpcharge,
                'changes' => array_keys($changes),
                'addon_changes' => $addonPricing['changes']['addon_change'] ?? null,
            ] + ($usedBelowMinimum ? ['below_pack_minimum' => true, 'pack_min_qty' => $packMin] : []),
        );

        // Lado financiero por SIGNO del diff de Tab 1.
        //  - SUBIDA (#150): el incremento se cobra en puerta (`recordEdit` +Δ); la señal se congela.
        //  - BAJADA (#225, D8): «bajar cantidad = SOLO cancelar». Desde la T1 del libro la bajada es
        //    UN hecho con su delta entero (`recordEdit` −Δ); qué parte la absorbe la puerta y qué
        //    parte aflora como saldo «a devolver» (#198) lo DERIVA el saldo del libro
        //    (`OrderBook`). NO se auto-reembolsa (cancelar ≠ reembolsar): el operador devuelve
        //    APARTE con «Reembolsar». (Evita además el fallo en pedidos manuales: sin gateway_order
        //    el refund REST fallaba siempre.)
        // Context ESTRUCTURADO (no solo claves): es lo que el libro convierte en la etiqueta de la
        // línea de valor («Cantidad: 2 → 3», «Cambio de fecha a …»).
        // Las dos cifras del OUTCOME (lo que subió / lo que bajó) alimentan la notificación del
        // OPERADOR en el panel; el correo del cliente ya no las recibe (T3·3 del libro: pinta el libro).
        $extraDueCents = null;
        $reducedCents = null;
        // ⚠️⚠️ **`slot_change` ENTRA aquí desde `DECISIONES #145`, y su ausencia era un defecto con
        // fecha.** El filtro es del commit fundacional (2026-08-12), cuando mover la fecha NO
        // re-tarificaba: un cambio de franja no podía generar diferencia de precio, así que no había
        // nada que anotar. `PAY-18` creó esa causa nueva y nadie extendió el filtro: el ajuste se
        // guardaba con `context = {"changes": []}` mientras su hermano del registro guardaba
        // `"changes": ["slot_change"]` con los precios de origen y destino.
        $itemEditContext = array_intersect_key($changes, array_flip(['product_change', 'quantity_change', 'slot_change', 'unit_price_change']));
        $fresh = $item->fresh();
        // Una SUBIDA y una BAJADA son el MISMO hecho con su delta entero y su signo (T1 del libro,
        // `specs/desglose-libro.md` §4.2). Qué parte absorbe la puerta y qué parte se devuelve lo
        // dice el LIBRO al leer (el saldo de la reserva), no una cascada al escribir.
        if ($diff > 0) {
            $order->recordEdit($fresh, $diff, $by, 'item_edit', ['changes' => $itemEditContext]);
            $extraDueCents = $diff;
        } elseif ($diff < 0) {
            $order->recordEdit($fresh, $diff, $by, 'item_edit_reduction', ['changes' => $itemEditContext]);
            $reducedCents = -$diff;
        }

        // Sub-fase 7.2e.4 (#170): el upcharge de COMPLEMENTOS es un MOVIMIENTO SEPARADO (decisión
        // #170, "no netear"): cobro en puerta independiente del diff de Tab 1. Las bajadas de
        // complementos NO mueven dinero aquí (refund manual aparte). Sin red → no puede fallar.
        //
        // Cada cargo se ata a SU child (el complemento), no al principal: así, si ese complemento se
        // cancela luego (p. ej. un cambio de menú lo sustituye), su cargo se ANULA solo (el libro
        // retira el valor del child cancelado con su movimiento de cancelación) → no quedan cargos
        // fantasma ni devoluciones espurias.
        foreach ($addonCharges as $charge) {
            $amount = (int) ($charge['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $childId = $charge['child_id'] ?? ($addonAddChildIds[$charge['type_id']] ?? null);
            $childItem = $childId !== null ? OrderItem::find((int) $childId) : null;
            // Defensa: si por lo que sea no se resuelve el child, se ata al principal
            // (comportamiento previo) en vez de perder el cobro.
            $target = $childItem ?? $item->fresh();
            $order->recordEdit($target, $amount, $by, 'addon_edit', $charge['context'] ?? [
                'addon_change' => $addonPricing['changes']['addon_change'] ?? null,
            ]);
            $extraDueCents = ($extraDueCents ?? 0) + $amount;
        }

        // M4 (auditoría Fase 1): canalizar el delta de los complementos PER-INVITADO re-escalados,
        // atado a CADA child (#170) y con el MISMO criterio de signo que el principal. El
        // `quantity_change` en el contexto permite a `itemOriginalOnlineCents` reconstruir el
        // sobre-cobro de una BAJADA como «pendiente de devolución» (igual que el principal). Para un
        // pack con SEÑAL, el crédito recae en el `deposit_remainder` del child (puerta); para uno
        // pagado online, en el marcador (online).
        foreach ($perGuestRescales as $childId => $r) {
            $delta = (int) $r['delta'];
            if ($delta === 0) {
                continue;
            }
            $child = OrderItem::find($childId);
            if ($child === null) {
                continue;
            }
            $ctx = ['changes' => [
                'quantity_change' => ['old' => (int) $r['old_qty'], 'new' => (int) $r['new_qty']],
                'addon_change' => ['added' => [], 'removed' => [], 'updated' => [
                    ['name' => $r['name'], 'old' => (int) $r['old_qty'], 'new' => (int) $r['new_qty']],
                ]],
            ]];

            $order->recordEdit($child, $delta, $by, $delta > 0 ? 'addon_per_guest_rescale' : 'addon_per_guest_rescale_reduction', $ctx);
            if ($delta > 0) {
                $extraDueCents = ($extraDueCents ?? 0) + $delta;
            }
        }

        // event_data en el mismo guardado (tras mutar el item; el token se refresca: la mutación
        // bumpeó `updated_at`).
        if ($eventData !== null && $this->saveEventDataAfterMutation($order, $item, $eventData, $by)) {
            $changes['event_data_change'] = true;
        }

        // Un solo email consolidado: qué cambió, y el LIBRO del pedido a día de hoy (T3·3 del libro,
        // D-T3·5): los importes —con su signo y su saldo— los dice el bloque compartido, no tres
        // céntimos calculados aquí (los de `#155` murieron con la cascada).
        $order->notifyCustomer(new OrderItemModified(
            order: $order->fresh(),
            item: $item->fresh(),
            changes: $changes,
        ));

        // El DINERO de los complementos re-tarificados por el día nuevo (`#417`): post-commit y en
        // su propia transacción corta, como el resto de la secuencia financiera. ⚠️ Las RETIRADAS no
        // pasan por aquí — no llevan hecho (§9.6 de `hora-extra.md`).
        if ($appliedDatePlan !== null) {
            $this->addonDates->applyMoney(
                $appliedDatePlan,
                $order->fresh(['items.children', 'items.ticketType', 'adjustments']) ?? $order,
                $by,
            );
        }

        // ⚠️⚠️ **Sin esto, la línea del suplemento se queda MINTIENDO.** Si el operador baja los
        // invitados de 10 a 8, o cambia el pack, el veredicto derivado cambia al instante pero lo
        // ESCRITO no — y seguiría cobrando por niños que ya no están. La reconciliación no puede
        // colgar solo del guardado del cliente, que puede no volver a producirse nunca
        // (`specs/cumple-mixto.md` §12). POST-COMMIT y fuera del lock de zona/día.
        // ⚠️ Va DESPUÉS del reconciliador de complementos, que excluye los portadores justamente
        // para que sea ÉSTE quien los gobierne (§9.8·H2).
        if ($by !== null) {
            $this->mixedParty->reconcile($item->fresh(), $by, 'panel_item_edit');
        }

        return ItemActionOutcome::done([
            'extra_due_cents' => $extraDueCents,
            'reduced_cents' => $reducedCents,
            'item_edit_context' => $itemEditContext,
        ]);
    }

    // ─── Producto y cantidad ────────────────────────────────────────────────

    /**
     * Validación PURA del destino de un edit (sub-fase 7.2e.3, #167):
     * producto (mismo tipo + misma zona + vendible, sin addons huérfanos) +
     * cantidad (rango del pack). Devuelve la razón estructurada de bloqueo o
     * `null` si pasa.
     *
     * ⚠️ `$allowBelowMinimum` (D7, `specs/cumple-mixto.md` §23.4) salta SOLO el mínimo del pack:
     * el máximo y el `>= 1` siguen mandando, y quien lo pasa en `true` es `edit()` DESPUÉS de
     * comprobar el permiso `orders.edit_item_below_minimum` — este validador es puro y no mira
     * permisos. Solo el panel: `OrderCreator` sigue exigiendo el mínimo al vender.
     */
    /**
     * Las hijas VIVAS de un ítem que OCUPAN aforo — por los HECHOS de la fila (franja y plazas),
     * nunca por el catálogo vivo: una hija vendida ocupando sigue ocupando aunque el interruptor
     * del producto cambie después (la doctrina del sello, `specs/hora-extra.md` §4.3), y una
     * vendida neutra sigue neutra.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, OrderItem>
     */
    private function liveOccupyingChildren(OrderItem $item): \Illuminate\Database\Eloquent\Collection
    {
        return $item->children()
            ->whereNull('cancelled_at')
            ->whereNotNull('slot_id')
            ->where('seats', '>', 0)
            ->with('ticketType')
            ->get();
    }

    /**
     * Las hijas VIVAS que EXTIENDEN la estancia del padre (la hora extra de un pack, §10).
     *
     * ⚠️ Su filtro es el ESPEJO del de {@see liveOccupyingChildren()} y por eso no se pueden
     * fusionar: una hija ocupante se reconoce por tener **franja y plazas**, y una extensora
     * precisamente por **no tenerlas** — lo que la identifica es el interruptor de su producto.
     */
    private function liveStayExtendingChildren(OrderItem $item): \Illuminate\Database\Eloquent\Collection
    {
        return $item->children()
            ->whereNull('cancelled_at')
            ->whereHas('ticketType', fn ($q) => $q->where('extends_parent_stay', true))
            ->with('ticketType')
            ->get();
    }

    /**
     * **Los minutos que la fiesta quedará alargada DESPUÉS de este guardado** (§10.3).
     *
     * Hermana de la composición de `$resultingOccupying`, y con la misma forma: las hijas
     * extensoras vivas que no se cancelan aquí (con su cantidad editada si sube) más las que se
     * añaden en este mismo guardado. De ella salen las dos cosas que la edición tiene que decir
     * igual que la compra: **el cupo que se revalida bajo el lock** y **el hecho que se escribe**.
     *
     * ⚠️ `$survivingChildIds` a `null` = «todas sobreviven» (el caso normal, sin cambio de día).
     * Cuando el día cambia, el plan de `AddonDateReconciler` decide quién se queda **y esos minutos
     * se descuentan ANTES de validar** — la lección de `#417` (§9.8·H1) aplicada a la extensión: con
     * el orden al revés, una reserva no se podría mover a un día en que su hora extra no se vende si
     * la sala está ocupada después, *bloqueada por un aforo que nadie iba a consumir*.
     *
     * @param  array{edits?: array<int, array{child_id:int, quantity:int}>, adds?: array<int, array{ticket_type_id:int, quantity:int}>}  $addonEdits
     * @param  Collection<int, TicketType>  $offeredAddons
     * @param  array<int, int>|null  $survivingChildIds
     * @param  array<int, int>  $addQuantities  cantidades efectivas de los `adds`, por id de producto
     */
    private function resultingStayMinutes(
        OrderItem $item,
        array $addonEdits,
        $offeredAddons,
        ?array $survivingChildIds = null,
        array $addQuantities = [],
    ): int {
        $editByChildId = [];
        foreach ($addonEdits['edits'] ?? [] as $edit) {
            $editByChildId[(int) $edit['child_id']] = (int) $edit['quantity'];
        }

        // El pivote de cada complemento, que es quien dice si su cantidad son BLOQUES o PERSONAS
        // (`specs/hora-extra.md` §11.5, `#443`). Manda el del producto NUEVO cuando el guardado
        // cambia de producto —es el que gobernará la línea a partir de ahora—, y el del actual
        // cuando no (el camino de `changeSlot`, que llega con la colección vacía).
        $pivots = $this->addonPivotsFor($item);
        foreach ($offeredAddons as $offeredAddon) {
            $pivots[(int) $offeredAddon->id] = $offeredAddon->pivot;
        }

        $minutes = 0;
        foreach ($this->liveStayExtendingChildren($item) as $child) {
            if ($survivingChildIds !== null && ! in_array((int) $child->id, $survivingChildIds, true)) {
                continue; // el día nuevo no vende esta hora extra: se retira y deja de alargar
            }
            $edited = $editByChildId[(int) $child->id] ?? null;
            if ($edited === 0) {
                continue; // se quita en este mismo guardado
            }
            $qty = ($edited !== null && $edited > (int) $child->quantity) ? $edited : (int) $child->quantity;
            $pivot = $pivots[(int) $child->ticket_type_id] ?? null;
            // Sin pivote la línea es HUÉRFANA y el guardado ya está bloqueado más arriba
            // (`orphan_addons`), así que esto es inalcanzable; se cae al lado que NO sobrevende
            // —tratar la cantidad como bloques reserva igual o más sala— en vez de suponer un modo.
            $minutes += $pivot !== null
                ? AddonOccupancy::extraMinutes($child->ticketType, $pivot, $qty)
                : AddonOccupancy::minutesForBlocks($child->ticketType, $qty);
        }

        foreach ($addonEdits['adds'] ?? [] as $add) {
            $addTypeId = (int) $add['ticket_type_id'];
            /** @var TicketType|null $addType */
            $addType = $offeredAddons->get($addTypeId);
            if ($addType === null || ! AddonOccupancy::sellableStayExtension($addType)) {
                continue;
            }
            $minutes += AddonOccupancy::extraMinutes(
                $addType, $addType->pivot, (int) ($addQuantities[$addTypeId] ?? $add['quantity']),
            );
        }

        return $minutes;
    }

    /**
     * Dónde ATERRIZA la familia ocupante detrás de la posición (nueva) del padre, validando que
     * CABE — `[franja de la hija, null]` o `[null, motivo de bloqueo]` (`specs/hora-extra.md`
     * §4.4·3 + borde 4).
     *
     * La franja sale de la regla determinista del borde 8 (`AddonOccupancy::childSlotAmong`),
     * buscada entre las franjas BLOQUEADAS por `withZoneDayLock` — no con una consulta que leería
     * otro snapshot. Cada hija se valida excluyendo la huella actual de TODA la familia
     * (`$excludeItemIds`, el borde 1) y con sus HERMANOS resultantes como ocupantes provisionales
     * (el borde 6: dos hijas de la misma línea caen sobre la misma franja y ninguna está aún
     * escrita en su posición nueva). El padre no hace falta como provisional: su tramo acaba
     * exactamente donde el de las hijas empieza.
     *
     * @param  Collection<array-key, Slot>  $lockedSlots
     * @param  list<array{type: TicketType|null, seats: int}>  $children  la familia RESULTANTE
     * @param  list<int>  $excludeItemIds  padre + hijas vivas (huella actual, excluida en bloque)
     * @return array{0: ?Slot, 1: ?string}
     */
    private function landOccupyingFamily($lockedSlots, TicketType $parentType, Slot $parentSlot, array $children, array $excludeItemIds): array
    {
        if ($children === []) {
            return [null, null];
        }

        $childSlot = AddonOccupancy::childSlotAmong($lockedSlots, $parentType, $parentSlot);
        if ($childSlot === null) {
            return [null, 'addon_occupancy_at_destination'];
        }

        foreach ($children as $i => $child) {
            if ($child['type'] === null) {
                return [null, 'addon_occupancy_at_destination'];
            }
            $siblings = [];
            foreach ($children as $j => $other) {
                if ($j === $i || $other['type'] === null) {
                    continue;
                }
                $siblings[] = [
                    'entry_start' => (string) $childSlot->start_time,
                    'duration_min' => $other['type']->duration_min,
                    'seats' => (int) $other['seats'],
                ];
            }
            $available = $this->slotAvailability->availableFor(
                $childSlot,
                $child['type']->duration_min,
                $siblings,
                $excludeItemIds,
            );
            if ($available < (int) $child['seats']) {
                return [null, 'addon_occupancy_at_destination'];
            }
        }

        return [$childSlot, null];
    }

    public function validateItemEditTarget(OrderItem $item, TicketType $newType, int $newQty, bool $allowBelowMinimum = false): ?string
    {
        $oldType = $item->ticketType;
        $productChanged = (int) $newType->id !== (int) $item->ticket_type_id;

        if ($productChanged) {
            if (! $newType->is_sellable) {
                return 'invalid_product';
            }
            if ($oldType === null || $newType->type !== $oldType->type) {
                return 'cross_type_change_forbidden';
            }
            if ($newType->zone_id === null || (int) $newType->zone_id !== (int) $oldType->zone_id) {
                return 'cross_zone_change_forbidden_product';
            }
            if ($this->orphanAddonsForNewProduct($item, $newType) !== []) {
                return 'orphan_addons';
            }
        }

        if ($newQty < 1) {
            return 'invalid_quantity';
        }
        if ($newType->isPack()) {
            $min = (int) ($newType->min_qty ?? 1);
            $max = $newType->max_qty !== null ? (int) $newType->max_qty : null;
            if (($newQty < $min && ! $allowBelowMinimum) || ($max !== null && $newQty > $max)) {
                return 'pack_quantity_range';
            }
        }

        return null;
    }

    /**
     * Complementos del item que el producto NUEVO no admite (no están en su
     * pivote `product_addons`). Solo cuentan los children ACTIVOS (no
     * cancelados). Devuelve [order_item_id => nombre] para listarlos en el
     * banner (sub-fase 7.2e.3, #167).
     *
     * @return array<int, string>
     */
    public function orphanAddonsForNewProduct(OrderItem $item, TicketType $newType): array
    {
        $allowedAddonIds = $newType->addons()->pluck('ticket_types.id')->all();

        // ⚠️⚠️ La línea del SUPLEMENTO de fiesta mixta NO es un complemento del pack: es el reflejo
        // de una edad que declaró el cliente, y la gobierna la reconciliación post-commit, que la
        // re-deriva bajo el sello NUEVO (`specs/cumple-mixto.md` §21.4 y §21.8). Contarla aquí como
        // huérfana la convertía en un cerrojo: el editor pedía quitarla para poder cambiar de pack,
        // y quitarla es justo el gesto que `#271` descarta — medido con el caso `AgeFamilySealTest`
        // del cambio de pack, una fiesta mixta con cargo NO PODÍA cambiar de pack desde el panel.
        $governed = $this->mixedParty->governedLineIds($item);

        $orphans = [];
        foreach ($item->children as $child) {
            if ($child->isCancelled() || in_array((int) $child->id, $governed, true)) {
                continue;
            }
            if (! in_array((int) $child->ticket_type_id, array_map('intval', $allowedAddonIds), true)) {
                $orphans[(int) $child->id] = $child->ticketType?->tr('name') ?? ('#'.$child->id);
            }
        }

        return $orphans;
    }

    // ─── Complementos ───────────────────────────────────────────────────────

    /**
     * Validación PURA de los edits de complementos (sub-fase 7.2e.4, #170).
     * Devuelve la razón estructurada de bloqueo o `null`. Reglas:
     *  - cada EDIT referencia un child ACTIVO del item (`addon_not_in_parent`);
     *  - cantidad de un edit ≥ 0 (`addon_quantity_invalid`); la reducción
     *    parcial a un valor intermedio (0 < q < actual) NO está soportada sin
     *    refund (`addon_partial_reduce_unsupported`) — quitar (0) sí;
     *  - cada ADD está en el pivote `addons()` del producto NUEVO
     *    (`addon_incompatible_with_product`), con cantidad ≥ 1
     *    (`addon_quantity_invalid`), sin duplicar un complemento ya presente
     *    ni repetirlo en la misma tanda (`addon_already_added`).
     *
     * @param  array<int, array{child_id:int, quantity:int}>  $edits
     * @param  array<int, array{ticket_type_id:int, quantity:int}>  $adds
     */
    public function validateAddonEdits(OrderItem $item, TicketType $newType, array $edits, array $adds): ?string
    {
        $childById = $item->children->keyBy('id');
        $meta = $this->childAddonMeta($item);

        foreach ($edits as $edit) {
            $child = $childById->get($edit['child_id']);
            if ($child === null || (int) $child->parent_item_id !== (int) $item->id || $child->isCancelled()) {
                return 'addon_not_in_parent';
            }
            $q = (int) $edit['quantity'];
            if ($q < 0) {
                return 'addon_quantity_invalid';
            }
            // MISMAS condiciones que la web: los incluidos/obligatorios no se quitan ni bajan de lo
            // incluido; los per-invitado/grupo están bloqueados (se cambian con "elige menú").
            $cmeta = $meta[(int) $child->id] ?? ['min' => 0, 'locked' => false];
            if (($cmeta['locked'] ?? false) && $q !== (int) $child->quantity) {
                return 'addon_locked';
            }
            if ($q < ($cmeta['min'] ?? 0)) {
                return 'addon_locked';
            }
            // Tope de un INCLUIDO sin extras (#225 · auditoría Fase 1 P4): no se cobran unidades de
            // pago por encima de lo incluido (misma autoridad que la compra pública, AddonResolver).
            if (($cmeta['max'] ?? null) !== null && $q > $cmeta['max']) {
                return 'addon_no_extra';
            }
            if ($q > 0 && $q < (int) $child->quantity) {
                return 'addon_partial_reduce_unsupported';
            }
        }

        $allowedAddonIds = array_map('intval', $newType->addons()->pluck('ticket_types.id')->all());
        $activeAddonTypeIds = $item->children
            ->reject(fn (OrderItem $c): bool => $c->isCancelled())
            ->map(fn (OrderItem $c): int => (int) $c->ticket_type_id)
            ->all();

        // Pivotes del producto nuevo para detectar conflictos de grupo entre los adds.
        $newPivots = [];
        foreach ($newType->addons()->get() as $a) {
            $newPivots[(int) $a->id] = $a->pivot;
        }

        $seen = [];
        $seenGroups = [];
        foreach ($adds as $add) {
            $typeId = (int) $add['ticket_type_id'];
            if ((int) $add['quantity'] < 1) {
                return 'addon_quantity_invalid';
            }
            if (! in_array($typeId, $allowedAddonIds, true)) {
                return 'addon_incompatible_with_product';
            }
            if (in_array($typeId, $activeAddonTypeIds, true) || in_array($typeId, $seen, true)) {
                return 'addon_already_added';
            }
            // No se pueden elegir DOS del mismo grupo a la vez (la web es radio). Añadir UN miembro
            // de un grupo que ya tiene otro presente es un CAMBIO de menú (se permite; el guardado
            // sustituye el anterior).
            $group = $newPivots[$typeId]?->choiceGroup();
            if ($group !== null) {
                if (in_array($group, $seenGroups, true)) {
                    return 'addon_group_conflict';
                }
                $seenGroups[] = $group;
            }
            $seen[] = $typeId;
        }

        // Dependencias «requiere» (data-driven): tras aplicar edits+adds, ningún complemento ACTIVO
        // puede quedar con su requisito ausente. Conjunto de tipos que QUEDARÁN activos = children no
        // quitados (edit qty != 0) ∪ los añadidos; cada dependiente exige su requisito dentro (misma
        // autoridad que la compra pública, AddonResolver). Cubre tanto «añadir el dependiente sin su
        // requisito» como «quitar el requisito dejando al dependiente huérfano».
        $removedChildIds = [];
        foreach ($edits as $edit) {
            if ((int) $edit['quantity'] === 0) {
                $removedChildIds[(int) $edit['child_id']] = true;
            }
        }
        $finalActiveTypeIds = [];
        foreach ($item->children as $child) {
            if ($child->isCancelled() || isset($removedChildIds[(int) $child->id])) {
                continue;
            }
            $finalActiveTypeIds[(int) $child->ticket_type_id] = true;
        }
        foreach ($adds as $add) {
            $finalActiveTypeIds[(int) $add['ticket_type_id']] = true;
        }
        // El GUARDADO sustituye al miembro de grupo presente cuando un add es del MISMO grupo
        // (group-replacement, ②b). Simúlalo aquí: ese miembro NO seguirá activo, así que un
        // dependiente que lo «requiere» debe disparar `addon_requires_missing` (igual que la web),
        // en vez de quedar huérfano. Sin esto, el panel divergiría de la autoridad pública.
        // ⚠️⚠️ **`?->` NO protege de una clave AUSENTE.** `$newPivots` solo lleva los complementos
        // VENDIBLES Y ACTIVOS del producto (`addons()` filtra por los dos), así que un hijo vivo
        // cuyo producto ya no cumple una de esas condiciones —despublicado después de venderse, o
        // un complemento de sistema que nunca se ofrece— no tiene entrada aquí y esto lanzaba
        // «Undefined array key». Medido el 2026-08-29 al editar un pedido con la línea del
        // suplemento de fiesta mixta, que es no vendible a propósito (`specs/cumple-mixto.md` §12).
        // Un hijo sin pivote no tiene grupo ni requisito, que es exactamente lo que dice `null`.
        $addedGroups = [];
        foreach ($adds as $add) {
            $group = ($newPivots[(int) $add['ticket_type_id']] ?? null)?->choiceGroup();
            if ($group !== null) {
                $addedGroups[$group] = true;
            }
        }
        if ($addedGroups !== []) {
            foreach ($item->children as $child) {
                if ($child->isCancelled()) {
                    continue;
                }
                $childGroup = ($newPivots[(int) $child->ticket_type_id] ?? null)?->choiceGroup();
                if ($childGroup !== null && isset($addedGroups[$childGroup])) {
                    unset($finalActiveTypeIds[(int) $child->ticket_type_id]);
                }
            }
        }
        foreach (array_keys($finalActiveTypeIds) as $typeId) {
            $required = ($newPivots[$typeId] ?? null)?->requiresAddonId();
            if ($required !== null && ! isset($finalActiveTypeIds[$required])) {
                return 'addon_requires_missing';
            }
        }

        return null;
    }

    /**
     * Metadatos de cada complemento ACTUAL del item para aplicar las MISMAS condiciones que la web:
     * cantidad mínima (los incluidos/obligatorios no se quitan ni bajan de lo incluido), bloqueo de
     * cantidad (per-invitado/grupo: se cambian con "elige menú", no editando la cantidad) y badge.
     * Los lee la validación de arriba y el formulario del modal (pistas y etiquetas).
     *
     * @return array<int, array{min:int, max:?int, locked:bool, badge:?string, group:?string, free:int}>
     */
    public function childAddonMeta(OrderItem $item): array
    {
        $pivots = $this->addonPivotsFor($item);
        $isPack = $item->ticketType?->isPack() ?? false;

        $meta = [];
        foreach ($item->children as $child) {
            $pivot = $pivots[(int) $child->ticket_type_id] ?? null;
            $perGuest = $pivot?->isPerGuest() ?? false;
            $group = $pivot?->choiceGroup();
            $locked = $perGuest || $group !== null;
            $min = $locked
                ? (int) $child->quantity
                : (($pivot?->is_mandatory ?? false) ? max(1, (int) $pivot->included_quantity) : 0);
            $badge = ($pivot?->is_included ?? false) ? ($isPack ? 'included' : 'free') : null;
            // Tope superior (#225 · auditoría Fase 1 P4): un complemento INCLUIDO SIN extras
            // (`is_included && !allow_extra`) no admite unidades de pago por encima de lo incluido —
            // la compra pública lo capa en `AddonResolver::effectiveQuantity`; el edit del panel
            // debe heredar la MISMA autoridad (antes cobraba `extra_due` de unidades no vendibles).
            // null = sin tope (admite extras de pago).
            // Un PER-INVITADO no tiene «extras» que topar: su cantidad es fija (= invitados) y
            // bloqueada. Los topes (incluido-sin-extra / max_qty) SOLO aplican a cantidad FIJA. Sin
            // este guard, un Menú incluido per-invitado daba max=included_quantity(1) y la validación
            // rechazaba CUALQUIER cambio de complementos del pack con `addon_no_extra`.
            $caps = [];
            if (! $perGuest) {
                if (($pivot?->is_included ?? false) && ! ($pivot?->allow_extra ?? false)) {
                    $caps[] = max(1, (int) $pivot->included_quantity);
                }
                if (($pivot?->max_qty ?? null) !== null) { // P9: tope por complemento
                    $caps[] = (int) $pivot->max_qty;
                }
            }
            $max = $caps === [] ? null : min($caps);

            $meta[(int) $child->id] = [
                'min' => $min,
                'max' => $max,
                'locked' => $locked,
                'badge' => $badge,
                'group' => $group,
                'free' => (int) $child->free_quantity,
            ];
        }

        return $meta;
    }

    /**
     * Pivotes de los complementos del producto del item, indexados por ticket_type_id, para leer
     * su config (incluido/obligatorio/por-invitado/grupo) al editar (modal "Gestionar"). Misma
     * autoridad que la compra pública.
     *
     * @return array<int, ProductAddon>
     */
    private function addonPivotsFor(OrderItem $item): array
    {
        $type = $item->ticketType;
        if ($type === null) {
            return [];
        }

        $out = [];
        foreach ($type->addons()->get() as $addon) {
            $out[(int) $addon->id] = $addon->pivot;
        }

        return $out;
    }

    /**
     * ¿El guardado incluye algún cambio REAL de complementos? (sub-fase 7.2e.4).
     * Un edit que deja la cantidad igual NO cuenta. Lo usa el despachador de la
     * página para decidir si entrar al guardado unificado.
     *
     * @param  array{edits: array<int, array{child_id:int, quantity:int}>, adds: array<int, array{ticket_type_id:int, quantity:int}>}  $normalized
     */
    public function addonEditsPresent(OrderItem $item, array $normalized): bool
    {
        if (($normalized['adds'] ?? []) !== []) {
            return true;
        }
        $childById = $item->children->keyBy('id');
        foreach ($normalized['edits'] ?? [] as $edit) {
            $child = $childById->get($edit['child_id']);
            if ($child === null) {
                // Referencia desconocida (p. ej. IDOR cross-item o concurrente):
                // enrutar para que `validateAddonEdits` la bloquee con razón.
                return true;
            }
            if ($child->isCancelled()) {
                // Ya cancelado → un edit sobre él es no-op (no enrutar por esto).
                continue;
            }
            if ((int) $edit['quantity'] !== (int) $child->quantity) {
                return true;
            }
        }

        return false;
    }

    /**
     * child_ids de complementos que se QUITAN (cantidad 0) en este guardado.
     * Usado por la orphan-resolution: un complemento huérfano deja de bloquear
     * si se está quitando en el mismo guardado (sub-fase 7.2e.4, #170).
     *
     * @param  array{edits: array<int, array{child_id:int, quantity:int}>, adds: array<int, mixed>}  $addonEdits
     * @return array<int, int>
     */
    public function addonChildIdsBeingRemoved(array $addonEdits): array
    {
        $ids = [];
        foreach ($addonEdits['edits'] ?? [] as $edit) {
            if ((int) ($edit['quantity'] ?? -1) === 0) {
                $ids[] = (int) $edit['child_id'];
            }
        }

        return $ids;
    }

    // ─── Franja ─────────────────────────────────────────────────────────────

    /**
     * Resuelve un Slot concreto (zone del item + fecha + hora). Devuelve
     * null si no existe (defense in depth — el guardado lo trata como
     * "selección inválida").
     */
    public function resolveSlotForItem(OrderItem $item, string $date, string $time): ?Slot
    {
        $ticketType = $item->ticketType;
        if ($ticketType === null || $ticketType->zone_id === null) {
            return null;
        }

        return Slot::query()
            ->where('zone_id', $ticketType->zone_id)
            ->where('date', $date)
            ->where('start_time', $time)
            ->first();
    }

    /**
     * Valida el slot elegido en capa 4 del cambio. Devuelve la razón
     * estructurada para audit log o null si pasa.
     */
    public function validateNewSlot(OrderItem $item, ?Slot $newSlot, ?TicketType $forType = null): ?string
    {
        if ($newSlot === null) {
            return 'invalid_slot_selection';
        }
        // Sub-fase 7.2e.3 (#167): cuando se valida un cambio de producto, el
        // slot debe encajar con el producto NUEVO (zona + ventana). Por
        // defecto valida contra el producto actual del item (path 7.2e.2).
        $ticketType = $forType ?? $item->ticketType;
        if ($ticketType === null || $ticketType->zone_id !== $newSlot->zone_id) {
            return 'cross_zone_change_forbidden';
        }
        if ($newSlot->online_sales_open === false || $newSlot->status === Slot::STATUS_CLOSED) {
            // Excepción: el slot ACTUAL del item podría estar cerrado por admin;
            // mantener el mismo slot no es un "cambio" (lo cubre el no-op del
            // handler). Si llegamos aquí con cerrado, es porque el operador
            // eligió el cerrado como destino — bloqueamos.
            return 'slot_closed';
        }
        $newDateCarbon = $newSlot->date;
        // El ancla de «hoy» es la del PARQUE, la misma que usa la oferta
        // (`ItemRescheduleOffer::today`, [DECIDIDO owner, 2026-08-26]): si la
        // validación anclara en UTC, entre las 00:00 y las ~02:00 del parque
        // rechazaría como pasada una fecha que el calendario acaba de ofrecer.
        if ($newDateCarbon->lt($this->rescheduleOffer->today())) {
            return 'slot_in_past';
        }
        // Capa defense in depth — horizonte de compra (sub-fase 7.2e.2bis6, #160):
        // si el slot está más allá del límite global (default 6 meses), rechazo.
        // El calendario UI ya filtra esto (`ItemRescheduleOffer::selectableDates`);
        // este check protege contra atacante autenticado que manipule el form.
        if ($newDateCarbon->gt($this->rescheduleOffer->horizon())) {
            return 'beyond_horizon';
        }
        if (! $this->schedule->isOpenOn($newDateCarbon)) {
            return 'park_closed';
        }
        if (! $this->productWindow->allowsStart($ticketType, $newDateCarbon, $newSlot->start_time)) {
            return 'product_window';
        }

        return null;
    }
}
