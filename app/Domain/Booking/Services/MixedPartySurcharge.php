<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\AuditLogger;
use App\Notifications\MixedPartySurchargeChanged;
use Illuminate\Support\Facades\DB;

/**
 * RECONCILIA el suplemento de una fiesta MIXTA con las edades declaradas
 * (`docs/specs/cumple-mixto.md` §12).
 *
 * `[DECIDIDO owner, 2026-08-29]` **el importe sigue al hecho, automáticamente**, y no hace falta que
 * un operador lo apruebe. El razonamiento que lo sostiene es suyo y es correcto: aquí **el dinero no
 * se cobra online, se cobra en el parque**, así que un cargo pendiente puede recalcularse mientras
 * nadie lo haya cobrado — igual que el cliente decide cuánto va a pagar eligiendo en el carrito.
 *
 * ▶ **Y el código ya garantizaba la mitad difícil**: la ventana en la que el cliente puede mover el
 * importe se cierra EXACTAMENTE cuando el dinero se da por cobrado. Las dos cosas cuelgan del mismo
 * predicado (`OrderItem::isFinishedInPractice()`): el post-form pasa a solo lectura al terminar la
 * franja, y `Order::itemGateResolved()` deja de considerar pendiente el cargo en ese mismo instante.
 * No hay ni un minuto en el que se pueda bajar un importe ya cobrado.
 *
 * ## Reconciliar, no acumular
 *
 * Cada pasada deja el estado ESCRITO igual al DERIVADO: crea la línea que falte, ajusta la que
 * cambió y cancela la que sobre. Es idempotente por construcción —dos pasadas seguidas no suman— y
 * la simetría que el owner pidió («si vuelve a bajar la edad, el precio vuelve a su normalidad») no
 * es una función aparte: es el mismo camino.
 *
 * ## Cuándo se dispara — y cuándo NO
 *
 * `[DECIDIDO owner, 2026-08-29]`: se reconcilia cuando cambia el **HECHO** (las edades, la cantidad
 * de invitados, el producto o la fecha de la reserva) y **nunca** cuando cambia la
 * **CONFIGURACIÓN** (el precio del día en el catálogo, el tramo de edad de un pack). Lo que se le
 * comunicó al cliente se respeta; una fiesta ya escrita no cambia de importe porque el parque
 * retoque una tarifa. El desfase entre lo escrito y lo derivado **se enseña** en la ficha del
 * pedido, que es lo que lo hace comprobable en vez de invisible.
 *
 * ## Lo que NO cierra, y hay que saberlo
 *
 * ⚠️ Un cliente puede declarar 8 años, ver el suplemento y bajarlo a 6 la víspera. Eso **no se puede
 * impedir sin quitarle la edición**, que es justo lo que da valor al post-form — y de hecho puede
 * mentir igual al reservar, con este sistema o sin él (`[owner]`: «eso es trabajo en persona»). Lo
 * que sí se hace es dejarlo TRAZADO: cada cambio de importe se registra en `audit_logs`, y la hoja
 * de sala imprime las edades declaradas, así que en la puerta se tiene delante lo que dijo.
 */
class MixedPartySurcharge
{
    public function __construct(private GuestAgeMixReader $mix) {}

    /**
     * Deja el suplemento de esta reserva igual a lo que dicen sus edades. Devuelve el importe
     * ANTERIOR y el NUEVO (en céntimos) si algo cambió, o `null` si no había nada que hacer —los
     * llamantes lo usan para decidir si hay algo que contarle a alguien.
     *
     * ⚠️ **El importe se re-deriva aquí dentro, con la fila bloqueada.** Nunca se acepta un importe
     * calculado antes (ni el que enseñaba una pantalla, ni el de una pasada anterior): es la misma
     * regla que `PAY-12` aplica al precio de la compra, y aquí además protege de dos guardados
     * simultáneos del post-form, que sin el lock crearían dos líneas.
     *
     * @param  string  $reason  por qué se reconcilia (solo para el rastro; sin PII)
     * @return array{old:int, new:int}|null
     */
    public function reconcile(OrderItem $principal, User $actor, string $reason): ?array
    {
        $change = DB::transaction(function () use ($principal, $actor, $reason): ?array {
            /** @var OrderItem|null $item */
            $item = OrderItem::query()
                ->with(['ticketType', 'slot', 'order'])
                ->whereKey($principal->getKey())
                ->lockForUpdate()
                ->first();

            // Una reserva cancelada, o de un pedido cancelado, no tiene suplemento que ajustar: sus
            // líneas ya están fuera de todos los desgloses (`ReservationFinancials` las salta).
            if ($item === null || $item->isCancelled() || $item->order?->status === Order::STATUS_CANCELLED) {
                return null;
            }

            $carrier = MixedPartySettings::surchargeProduct();
            $current = $this->currentLines($item);

            // Sin producto portador no se puede escribir NADA — ni crear ni corregir. Devolver
            // `null` en silencio dejaría fiestas mixtas sin cobrar sin que nadie se entere, así que
            // el aviso lo da la ficha del pedido leyendo `MixedPartySettings` (§12). Aquí, al menos,
            // no se toca lo que ya estaba escrito.
            if ($carrier === null) {
                return null;
            }

            $target = $this->targetState($item);
            $old = $this->totalOf($current);

            $this->apply($item, $carrier->id, $current, $target, $actor);

            $new = array_sum(array_map(
                static fn (array $t): int => $t['count'] * $t['unit'],
                $target,
            ));

            if ($new === $old) {
                return null;
            }

            // `RGPD-02`: el rastro NO lleva PII — ni edades ni nombres de niños. Solo cuánto era,
            // cuánto es y por qué se recalculó. Es lo único que deja ver «declaró 8 el día 3 y lo
            // bajó a 6 el día 20» sin guardar la edad de un menor en un registro de auditoría.
            AuditLogger::log('orders.mixed_party_surcharge_synced', $item->order, [
                'order_code' => $item->order?->code,
                'order_item_id' => $item->id,
                'old_cents' => $old,
                'new_cents' => $new,
                'guests' => array_sum(array_column($target, 'count')),
                'reason' => $reason,
            ]);

            return ['old' => $old, 'new' => $new];
        });

        if ($change !== null) {
            $this->notify($principal, $change);
        }

        return $change;
    }

    /**
     * El estado que DEBERÍA tener el suplemento, por producto de destino.
     *
     * Se agrupa por destino y no en una sola línea porque cada destino tiene **su** diferencia por
     * cabeza: con dos regímenes siempre sale un único grupo, pero con tres una línea sola tendría
     * que inventarse un precio unitario que no existe. Se descartan los destinos que no cobran (la
     * diferencia es 0 o no se pudo resolver el precio del día): una línea de 0,00 € no es
     * información, es ruido en el desglose del cliente.
     *
     * @return array<int, array{count:int, unit:int, name:string}>
     */
    private function targetState(OrderItem $item): array
    {
        $mix = $this->mix->for($item);

        $state = [];
        foreach ($mix->upgrades as $upgrade) {
            $unit = $upgrade['unit_cents'];
            if ($unit === null || $unit <= 0 || $upgrade['count'] <= 0) {
                continue;
            }
            $state[(int) $upgrade['type_id']] = [
                'count' => (int) $upgrade['count'],
                'unit' => (int) $unit,
                'name' => (string) $upgrade['name'],
            ];
        }

        return $state;
    }

    /**
     * Las líneas de suplemento VIVAS de esta reserva, indexadas por producto de destino.
     *
     * ⚠️ La marca de «esto es un suplemento de fiesta mixta» vive en el `context` del AJUSTE, no en
     * una columna nueva ni en el `event_data` del ítem: el ajuste es 1:1 con su línea y `context` ya
     * existe para describir de dónde sale un cargo. Meter una marca de sistema en `event_data`
     * —que es lo que contestó el CLIENTE, y que `RGPD-01` vacía al anonimizar— habría sido usar un
     * campo para lo que no es.
     *
     * @return array<int, array{item:OrderItem, adjustment:OrderAdjustment, count:int, unit:int}>
     */
    private function currentLines(OrderItem $principal): array
    {
        $order = $principal->order;
        if ($order === null) {
            return [];
        }

        // ⚠️ Se leen las RELACIONES, no consultas nuevas: la ficha del pedido pregunta por esto una
        // vez por reserva y ya las tiene cargadas (`$item->children`, `loadMissing('adjustments')`),
        // así que una consulta aquí sería una N+1 en la pantalla más pesada del panel. En la ruta
        // de escritura el ítem se recarga fresco y bloqueado, así que los accesores consultan igual.
        $children = $principal->children->reject(fn (OrderItem $c): bool => $c->isCancelled())->keyBy('id');

        $lines = [];
        foreach ($order->adjustments->where('type', OrderAdjustment::TYPE_EXTRA_DUE) as $adjustment) {
            $context = is_array($adjustment->context) ? $adjustment->context : [];
            $mark = $context['mixed_party'] ?? null;
            if (! is_array($mark) || $adjustment->order_item_id === null) {
                continue;
            }
            $child = $children->get((int) $adjustment->order_item_id);
            if ($child === null) {
                continue; // su línea ya está cancelada: el cargo es inerte.
            }

            $lines[(int) ($mark['target_type_id'] ?? 0)] = [
                'item' => $child,
                'adjustment' => $adjustment,
                'count' => (int) $child->quantity,
                'unit' => (int) $child->unit_price,
            ];
        }

        return $lines;
    }

    /**
     * Lleva lo escrito a lo derivado: actualiza, crea y cancela. Las tres operaciones en el mismo
     * sitio para que ninguna se olvide de su gemela — el modo de fallo de esto es dejar una línea
     * viva de un destino que ya no aplica, y eso cobra dinero que nadie debe.
     *
     * @param  array<int, array{item:OrderItem, adjustment:OrderAdjustment, count:int, unit:int}>  $current
     * @param  array<int, array{count:int, unit:int, name:string}>  $target
     */
    private function apply(OrderItem $principal, int $carrierId, array $current, array $target, User $actor): void
    {
        foreach ($target as $typeId => $want) {
            $amount = $want['count'] * $want['unit'];

            if (isset($current[$typeId])) {
                $line = $current[$typeId];
                if ($line['count'] === $want['count'] && $line['unit'] === $want['unit']) {
                    continue;
                }
                $line['item']->forceFill([
                    'quantity' => $want['count'],
                    'unit_price' => $want['unit'],
                ])->save();
                $line['adjustment']->forceFill([
                    'amount_cents' => $amount,
                    'context' => $this->context($typeId, $want, $want['name'] ?? null),
                ])->save();

                continue;
            }

            // ⚠️ `slot_id` a null y `seats` a 0, como cualquier complemento que crea el editor: es
            // lo que mantiene la línea FUERA de toda consulta de aforo (todas cruzan por `slots`).
            $child = $principal->children()->create([
                'order_id' => $principal->order_id,
                'ticket_type_id' => $carrierId,
                'slot_id' => null,
                'quantity' => $want['count'],
                'free_quantity' => 0,
                'unit_price' => $want['unit'],
                'seats' => 0,
                'event_data' => null,
            ]);

            OrderAdjustment::create([
                'order_id' => $principal->order_id,
                'order_item_id' => $child->id,
                'type' => OrderAdjustment::TYPE_EXTRA_DUE,
                'amount_cents' => $amount,
                'currency' => 'EUR',
                // Sin el ajuste, la línea diría que el cliente pagó ese importe ONLINE: los dos
                // canales reparten el valor de la línea, no lo amplían (§8.3, medido).
                'applied_by' => $actor->id,
                'reason' => 'mixed_party_surcharge',
                'context' => $this->context($typeId, $want, $want['name'] ?? null),
            ]);
        }

        foreach ($current as $typeId => $line) {
            if (isset($target[$typeId])) {
                continue;
            }
            // Cancelar y no borrar: la línea deja de contar en TODOS los desgloses (los financieros
            // saltan los ítems cancelados) y `Order::isVoidedLeftoverItem` la esconde por ser
            // net-cero. Borrarla destruiría el rastro de que existió.
            $line['item']->markCancelled($actor);
        }
    }

    /**
     * Lo que hay ESCRITO hoy de suplemento en esta reserva: importe y nº de invitados.
     *
     * Es lo que el cliente debe de verdad, y no siempre coincide con el veredicto derivado — por
     * diseño: lo escrito respeta lo que se le comunicó y no se recalcula porque el parque retoque
     * una tarifa o un tramo (`[DECIDIDO owner]`, §12). Por eso la ficha del pedido enseña los DOS
     * números cuando difieren, y el aviso al cliente enseña ESTE: prometerle un importe que no
     * está en su pedido sería peor que no decirle nada.
     *
     * @return array{cents:int, guests:int}
     */
    public function written(OrderItem $principal): array
    {
        $lines = $this->currentLines($principal);

        return [
            'cents' => $this->totalOf($lines),
            'guests' => array_sum(array_column($lines, 'count')),
        ];
    }

    /**
     * El `context` del ajuste: la MARCA que identifica la línea en la siguiente pasada y, a la vez,
     * lo que el cliente lee en su desglose (`OrderAdjustment::breakdownLabel`).
     *
     * @param  array{count:int, unit:int, name?:string}  $want
     * @return array<string, mixed>
     */
    private function context(int $targetTypeId, array $want, ?string $targetName = null): array
    {
        return ['mixed_party' => [
            'target_type_id' => $targetTypeId,
            // El NOMBRE del pack destino se guarda, no se resuelve al leer: es lo que se le dijo al
            // cliente, y así la línea de su desglose no necesita una consulta por fila ni cambia si
            // el producto se renombra después. Mismo criterio que el contexto de complementos.
            'target_name' => $targetName,
            'guests' => $want['count'],
            'unit_cents' => $want['unit'],
        ]];
    }

    /** @param  array<int, array{count:int, unit:int, item:OrderItem, adjustment:OrderAdjustment}>  $lines */
    private function totalOf(array $lines): int
    {
        return array_sum(array_map(static fn (array $l): int => $l['count'] * $l['unit'], $lines));
    }

    /**
     * Avisa al titular de que lo que debe en el parque ha cambiado `[DECIDIDO owner]`.
     *
     * ⚠️ **Solo cuando el IMPORTE cambia**, que es la condición que ya filtra `reconcile()`. El
     * post-form está pensado para editarse durante días; sin esa regla, un cliente que ajusta
     * nombres tres tardes seguidas recibiría tres correos idénticos.
     */
    private function notify(OrderItem $principal, array $change): void
    {
        // Por `Order::notifyCustomer` y no por `$user->notify`: es la puerta que ya comprueba que
        // el titular tiene email —una cuenta anonimizada (`RGPD-01`) no lo tiene— y la usan las
        // otras seis notificaciones de pedido. Una séptima con su propia condición sería una copia
        // que algún día diverge.
        $principal->order?->notifyCustomer(
            new MixedPartySurchargeChanged($principal->fresh(['ticketType', 'slot', 'order']) ?? $principal, $change['old'], $change['new']),
        );
    }
}
