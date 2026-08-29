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
 * ⚠️⚠️ **Esa regla estaba ESCRITA y no estaba CONSTRUIDA, y la corrección va antes que el texto de
 * arriba.** Hasta el 2026-08-29 se implementaba sola: «no hay disparador en los cambios de
 * configuración». Solo aguantaba hasta el siguiente disparo de hecho, porque la reconciliación
 * re-derivaba el importe entero del catálogo vigente — y el disparo siguiente es el cliente
 * corrigiendo un nombre, que ese formulario invita a hacer durante días. Medido sobre `R-BEEL3E`,
 * con las edades intactas: una subida de tarifa llevaba el cargo de 15,00 € a 30,00 €; estrechar un
 * tramo, a 0,00 €. ▶ Hoy la sostiene el RECIBO que cada línea lleva en su `context`
 * ({@see unitFor}): el unitario escrito manda mientras el pack reservado y el día de tarificación
 * sigan siendo los mismos.
 *
 * ## Una AUSENCIA no es una CORRECCIÓN
 *
 * Reconciliar hacia lo derivado solo vale mientras lo derivado DIGA algo. Con el veredicto a medias
 * —falta una edad, sobra una que ningún pack cubre, o el pack dejó de participar en su familia— lo
 * escrito puede CRECER pero nunca encoger ni retirarse ({@see derivationGoverns}). Sin esa puerta,
 * tres caminos distintos borraban un cargo real y ninguno era un cambio del hecho: el cliente
 * vaciando sus propias casillas de edad, `RGPD-01` al anonimizar, y un tramo tocado en el catálogo.
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
    /** El guardado del post-form por el CLIENTE, que es el único disparo que es suyo. */
    public const REASON_GUEST_FORM = 'guest_form';

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

            $mix = $this->mix->for($item);
            $target = $this->targetState($mix, $current, $item);
            $old = $this->totalOf($current);

            // ⚠️⚠️ **Una AUSENCIA no es una CORRECCIÓN** (§12.2.bis). Con el veredicto a medias, lo
            // escrito solo puede CRECER: nunca se encoge ni se retira. Sin esta puerta, tres formas
            // de que falte un dato borraban un cargo real y ninguna era un cambio del hecho.
            $new = $this->apply(
                $item, $carrier->id, $current, $target, $actor,
                mayShrink: $this->derivationGoverns($mix),
            );

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
            $this->notify($principal, $change, $reason);
        }

        return $change;
    }

    /**
     * ¿Puede el veredicto derivado MANDAR sobre lo ya escrito, hasta el punto de retirarlo?
     *
     * Solo cuando dice algo COMPLETO y COMPARABLE: que el pack sigue participando en una familia
     * por edad y que no falta ni sobra ninguna edad. En cualquier otro caso el veredicto no es una
     * afirmación («no hay suplemento»), es un silencio («todavía no consta»), y un silencio no
     * puede borrar un cargo que ya se le comunicó al cliente.
     *
     * ⚠️⚠️ **Las TRES formas de silencio están MEDIDAS, y las tres borraban dinero real** (§12.2.bis,
     * sondas sobre el pedido `R-BEEL3E` en transacción revertida):
     *  - el cliente **vacía las casillas de edad** y guarda → el cargo desaparecía. Es el peor,
     *    porque lo dispara el propio interesado y no exige más que borrar;
     *  - `User::anonymize()` (`RGPD-01`) pone `guest_data` a `null` → la siguiente pasada borraba el
     *    cargo. El derecho al olvido borra los datos del cliente, **no la contabilidad del parque**;
     *  - alguien **retira la familia o estrecha un tramo** en el catálogo → el veredicto pasa a «no
     *    aplica» o a «fuera de rango» y arrastraba el cargo con él.
     *
     * ▶ **Crecer sí puede**, y es asimétrico a propósito: declarar la edad que faltaba es un dato
     * nuevo y legítimo; borrarla no lo es. Así el importe sigue apareciendo mientras el cliente
     * rellena el formulario, y deja de poder desaparecer cuando lo vacía.
     */
    private function derivationGoverns(GuestAgeMix $mix): bool
    {
        return $mix->applies && $mix->isComplete();
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
     * ⚠️ Recibe el veredicto YA DERIVADO en vez de derivarlo: quien llama necesita el mismo objeto
     * para decidir si ese veredicto puede mandar sobre lo escrito ({@see derivationGoverns}), y dos
     * derivaciones de la misma reserva en la misma pasada podrían no coincidir.
     *
     * @param  array<int, array{item:OrderItem, adjustment:OrderAdjustment, count:int, unit:int, receipt:array<string,mixed>}>  $current
     * @return array<int, array{count:int, unit:int, name:string}>
     */
    private function targetState(GuestAgeMix $mix, array $current, OrderItem $item): array
    {
        $state = [];
        foreach ($mix->upgrades as $upgrade) {
            $typeId = (int) $upgrade['type_id'];
            $unit = $this->unitFor($typeId, $upgrade['unit_cents'], $current, $item);
            if ($unit === null || $unit <= 0 || $upgrade['count'] <= 0) {
                continue;
            }
            $state[$typeId] = [
                'count' => (int) $upgrade['count'],
                'unit' => $unit,
                'name' => (string) $upgrade['name'],
            ];
        }

        return $state;
    }

    /**
     * La diferencia POR CABEZA que se cobra por un destino: la que ya se le COMUNICÓ al cliente
     * mientras los hechos que la sostienen no se muevan, y solo entonces la del catálogo de hoy.
     *
     * ▶ **`[DECIDIDO owner, 2026-08-29]`, la regla que esto implementa** (§12.2): el importe sigue al
     * HECHO —las edades, cuántos son, el pack o el día— y **nunca** a la CONFIGURACIÓN. Estaba
     * escrita y no estaba construida: el reconciliador re-derivaba el importe entero del catálogo
     * vigente en cada pasada, así que la regla solo aguantaba hasta el siguiente guardado. Medido
     * sobre `R-BEEL3E`, con las edades intactas y el cliente corrigiendo un NOMBRE: una subida de
     * tarifa llevaba su cargo de 15,00 € a 30,00 €, y estrechar un tramo lo llevaba a 0,00 €.
     *
     * ▶ **El RECIBO son dos hechos, y viven en el `context` del propio ajuste** —nada de columna
     * nueva ni de migración—: bajo qué pack se reservó (`booked_type_id`) y con el catálogo de qué
     * día se tarificó (`priced_on`). Si los dos siguen siendo los de hoy, el unitario escrito manda.
     * Si alguno cambió, el hecho cambió y se re-tarifica desde el catálogo, que es exactamente lo
     * que `PAY-18` ya hace con el precio de la propia reserva al moverla de día.
     *
     * ⚠️ **La CANTIDAD nunca se hereda, solo el UNITARIO.** Es la respuesta del owner a la única
     * pregunta que esto abría: si el parque sube la tarifa y DESPUÉS el cliente declara otro
     * invitado mayor, ese invitado entra al precio que se le comunicó, no al de hoy. «14,00 €, no
     * 24,00 €.»
     *
     * ⚠️ **Sin recibo también se hereda**, y es deliberado: son las líneas escritas antes de esta
     * tanda. Preferir el catálogo de hoy para ellas movería justo el dinero que esto protege. Se
     * sellan en su primera pasada, sin tocar el importe ({@see apply}).
     *
     * @param  array<int, array{item:OrderItem, adjustment:OrderAdjustment, count:int, unit:int, receipt:array<string,mixed>}>  $current
     */
    private function unitFor(int $typeId, ?int $derived, array $current, OrderItem $item): ?int
    {
        $line = $current[$typeId] ?? null;
        if ($line === null) {
            return $derived; // destino nuevo: nunca se le comunicó nada, así que manda el catálogo.
        }

        $receipt = $line['receipt'];
        $bookedThen = $receipt['booked_type_id'] ?? null;
        $pricedOn = $receipt['priced_on'] ?? null;

        $sameProduct = $bookedThen === null || (int) $bookedThen === (int) $item->ticket_type_id;
        $samePricingDay = $pricedOn === null || (string) $pricedOn === (string) $item->slot?->date?->toDateString();

        return ($sameProduct && $samePricingDay) ? $line['unit'] : $derived;
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
                // El RECIBO de esa línea: bajo qué hechos se calculó su unitario ({@see unitFor}).
                'receipt' => $mark,
            ];
        }

        return $lines;
    }

    /**
     * Lleva lo escrito a lo derivado: actualiza, crea y cancela. Las tres operaciones en el mismo
     * sitio para que ninguna se olvide de su gemela — el modo de fallo de esto es dejar una línea
     * viva de un destino que ya no aplica, y eso cobra dinero que nadie debe.
     *
     * Devuelve el total que queda ESCRITO, que no siempre es el del objetivo: con `$mayShrink` en
     * `false` las bajadas y las retiradas se omiten, y quien llama necesita el importe real para
     * decidir si hay algo que auditar y que contarle al cliente. Calcularlo sumando `$target` —que
     * es lo que se hacía— anunciaría una bajada que no se ha escrito.
     *
     * @param  array<int, array{item:OrderItem, adjustment:OrderAdjustment, count:int, unit:int}>  $current
     * @param  array<int, array{count:int, unit:int, name:string}>  $target
     * @param  bool  $mayShrink  ¿el veredicto manda lo bastante como para RETIRAR dinero escrito?
     *                           ({@see derivationGoverns}); con `false`, esto solo puede crecer
     */
    private function apply(OrderItem $principal, int $carrierId, array $current, array $target, User $actor, bool $mayShrink): int
    {
        $written = 0;

        foreach ($target as $typeId => $want) {
            $amount = $want['count'] * $want['unit'];

            if (isset($current[$typeId])) {
                $line = $current[$typeId];
                $before = $line['count'] * $line['unit'];

                if ($line['count'] === $want['count'] && $line['unit'] === $want['unit']) {
                    // Nada que mover, pero puede faltar el RECIBO: es una línea escrita antes de que
                    // existiera. Se sella aquí, en su primera pasada, para que a partir de ahora se
                    // sepa bajo qué hechos se calculó — sin tocar un céntimo.
                    if (! isset($line['receipt']['booked_type_id'])) {
                        $line['adjustment']->forceFill([
                            'context' => $this->context($typeId, $want, $want['name'] ?? null, $principal),
                        ])->save();
                    }
                    $written += $before;

                    continue;
                }
                // La abstención, aplicada línea a línea y por IMPORTE: menos invitados con la misma
                // diferencia y los mismos invitados con menos diferencia son la misma pérdida.
                if ($amount < $before && ! $mayShrink) {
                    $written += $before;

                    continue;
                }
                $line['item']->forceFill([
                    'quantity' => $want['count'],
                    'unit_price' => $want['unit'],
                ])->save();
                $line['adjustment']->forceFill([
                    'amount_cents' => $amount,
                    'context' => $this->context($typeId, $want, $want['name'] ?? null, $principal),
                ])->save();
                $written += $amount;

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
                'context' => $this->context($typeId, $want, $want['name'] ?? null, $principal),
            ]);
            $written += $amount;
        }

        foreach ($current as $typeId => $line) {
            if (isset($target[$typeId])) {
                continue;
            }
            if (! $mayShrink) {
                $written += $line['count'] * $line['unit'];

                continue;
            }
            // Cancelar y no borrar: la línea deja de contar en TODOS los desgloses (los financieros
            // saltan los ítems cancelados) y `Order::isVoidedLeftoverItem` la esconde por ser
            // net-cero. Borrarla destruiría el rastro de que existió.
            $line['item']->markCancelled($actor);
        }

        return $written;
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
     * ⚠️ Devuelve también el DESGLOSE por destino, y no es un extra: es lo que permite explicarle al
     * cliente su propio cargo con los números que se le comunicaron. Componer esa frase con los
     * precios de hoy —que es lo que se hacía— la haría contradecir al importe en cuanto el parque
     * retocara una tarifa: «te corresponde Jump a 30,00 € en vez de Kids a 18,00 €» encima de un
     * suplemento de 7,00 €. La resta no le cuadraría y tendría razón.
     *
     * @return array{cents:int, guests:int, lines:list<array{name:string, count:int, unit:int}>}
     */
    public function written(OrderItem $principal): array
    {
        $lines = $this->currentLines($principal);

        return [
            'cents' => $this->totalOf($lines),
            'guests' => array_sum(array_column($lines, 'count')),
            'lines' => array_values(array_map(static fn (array $l): array => [
                // El nombre GUARDADO, no el resuelto: es el que se le dijo, y así la frase no
                // cambia si el producto se renombra después (mismo criterio que el desglose).
                'name' => (string) ($l['receipt']['target_name'] ?? '—'),
                'count' => $l['count'],
                'unit' => $l['unit'],
            ], $lines)),
        ];
    }

    /**
     * Los ids de las líneas hijas que ESTE servicio gobierna.
     *
     * ▶ Existe para que el PANEL pueda dejarlas fuera de la lista de complementos editables
     * (`ViewOrder`), y el criterio se pregunta aquí en vez de deducirse allí: una línea de
     * suplemento se reconoce por la MARCA de su ajuste, no por su producto. Mirar el producto
     * portador sería un segundo criterio, más débil, que envejecería en cuanto alguien cambiara el
     * ajuste `mixed_party.surcharge_product_id`.
     *
     * ⚠️⚠️ **Y no es cosmética.** Medido conduciendo el editor real: el operador ponía esa línea a 0
     * en «Gestionar producto» → Complementos, el editor lo aceptaba y la reconciliación POST-COMMIT
     * la volvía a crear **en la misma pulsación** — dejando al cliente dos correos que se
     * contradicen. Esa línea no es un complemento que el operador gobierne: es el reflejo de un dato
     * que declaró el cliente, y la única forma de moverla es mover el dato.
     *
     * @return list<int>
     */
    public function governedLineIds(OrderItem $principal): array
    {
        return array_values(array_map(
            static fn (array $l): int => (int) $l['item']->id,
            $this->currentLines($principal),
        ));
    }

    /**
     * El `context` del ajuste: la MARCA que identifica la línea en la siguiente pasada y, a la vez,
     * lo que el cliente lee en su desglose (`OrderAdjustment::breakdownLabel`).
     *
     * @param  array{count:int, unit:int, name?:string}  $want
     * @return array<string, mixed>
     */
    private function context(int $targetTypeId, array $want, ?string $targetName, OrderItem $item): array
    {
        return ['mixed_party' => [
            'target_type_id' => $targetTypeId,
            // El NOMBRE del pack destino se guarda, no se resuelve al leer: es lo que se le dijo al
            // cliente, y así la línea de su desglose no necesita una consulta por fila ni cambia si
            // el producto se renombra después. Mismo criterio que el contexto de complementos.
            'target_name' => $targetName,
            'guests' => $want['count'],
            'unit_cents' => $want['unit'],
            // ─── El RECIBO ({@see unitFor}): los dos HECHOS bajo los que se calculó ese unitario.
            // Mientras sigan siendo los de hoy, el importe escrito manda sobre el catálogo. No es
            // una copia por si acaso: es lo único que distingue «el parque cambió una tarifa» —que
            // no puede mover lo comunicado— de «esta reserva se movió de día», que sí.
            'booked_type_id' => (int) $item->ticket_type_id,
            'priced_on' => $item->slot?->date?->toDateString(),
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
    private function notify(OrderItem $principal, array $change, string $reason): void
    {
        // Por `Order::notifyCustomer` y no por `$user->notify`: es la puerta que ya comprueba que
        // el titular tiene email —una cuenta anonimizada (`RGPD-01`) no lo tiene— y la usan las
        // otras seis notificaciones de pedido. Una séptima con su propia condición sería una copia
        // que algún día diverge.
        $principal->order?->notifyCustomer(
            new MixedPartySurchargeChanged(
                $principal->fresh(['ticketType', 'slot', 'order']) ?? $principal,
                $change['old'],
                $change['new'],
                // El `reason` ya distingue quién disparó la pasada, y es el único sitio donde consta:
                // desde aquí no se puede mirar la sesión (esto corre también en cola y en consola).
                byCustomer: $reason === self::REASON_GUEST_FORM,
            ),
        );
    }
}
