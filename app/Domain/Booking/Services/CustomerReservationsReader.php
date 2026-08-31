<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\CustomerReservations;
use App\Domain\Booking\Contracts\PendingGuestForm;
use App\Domain\Booking\Contracts\ReservationScope;
use App\Domain\Booking\Contracts\UpcomingReservation;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-model de BOOKING: las reservas de un cliente, para consumidores de otros módulos.
 *
 * Implementa `CustomerReservations`. El código es el que vivía en
 * `App\Domain\Identity\Services\CustomerAccountContext` (Identity), movido tal cual al módulo dueño de
 * los datos en el paso 1 de la modularización — Identity ya no consulta `Order`,
 * `OrderItem` ni `TicketType` (Fase 2, `docs/specs/modulos-dominio.md`).
 *
 * Vive en `app/Support` hasta el paso 6, cuando Booking mude a `app/Domain/Booking/Services`.
 * Solo lectura y sin estado: no memoiza (de eso se encarga el singleton que lo consume).
 */
class CustomerReservationsReader implements CustomerReservations
{
    /**
     * Reservas FUTURAS del usuario: ítems principales (no addon), no cancelados, con franja y aún
     * no finalizados, de pedidos PAGADOS, ordenados por fecha+hora. Consulta DIRIGIDA (solo franjas
     * recientes o futuras) para no materializar todo el histórico en cada página pública; el corte
     * fino de "ya finalizada" lo da `isFinishedInPractice` en PHP (frontera con hora + zona horaria).
     *
     * @return list<UpcomingReservation>
     */
    public function upcomingFor(int $userId): array
    {
        return $this->upcomingItems($userId)
            ->map(fn (OrderItem $item): UpcomingReservation => new UpcomingReservation(
                date: (string) $item->slot?->date?->format('Y-m-d'),
                timeWindow: $item->displayTimeWindow(),
                productName: $item->displayProductName(),
            ))
            ->all();
    }

    /**
     * ¿Tiene el cliente alguna reserva POR CELEBRAR? — la puerta de la supresión (T5 · D8,
     * `cumple-mixto.md` §25.4). El porqué del criterio —y de que NO sea el complemento de
     * `terminated()`— está en el contrato ({@see CustomerReservations::hasUpcomingFor}).
     *
     * Reutiliza {@see upcomingItems} entera a propósito: el corte fino de «ya finalizada» lo da
     * `isFinishedInPractice()` en PHP, y un `exists()` en SQL se quedaría con el colchón de un día
     * — bloquearía la baja un día de más. Una verdad, no dos; el coste (materializar las próximas)
     * es irrelevante en una baja de cuenta.
     */
    public function hasUpcomingFor(int $userId): bool
    {
        return $this->upcomingItems($userId)->isNotEmpty();
    }

    /**
     * Formularios de reserva (#217) pendientes: uno por pedido pagado con un pack que los pide y
     * cuya franja AÚN NO ha finalizado (simétrico a la próxima reserva — no avisamos por un
     * cumpleaños ya celebrado, que dejaría el puntito/aviso encendidos para siempre). La consulta
     * se acota a pedidos con algún ítem de tipo pack (los formularios solo existen en packs) para
     * no recorrer el histórico de entradas. Un pack sin franja (`isFinishedInPractice` → false)
     * sigue avisando.
     *
     * ⚠️⚠️ **La consulta está DIRIGIDA por fecha desde el 2026-08-23, y antes no lo estaba.** Traía
     * **todo el histórico** de pedidos con pack —con sus ítems, tipos y franjas eager-loaded— y hacía
     * el corte de «ya celebrado» **entero en PHP**, mientras que su hermana `upcomingFor()` sí acotaba
     * por SQL. Un cliente con cinco años de cumpleaños materializaba los cinco años **en cada página
     * pública**, porque el nav pide este contexto siempre que hay sesión.
     * ▶ Lo destapó la revisión adversarial de `specs/account-context-vue.md` §4.4 al preguntarse qué
     * pasaba si esto se publicaba como endpoint —repetible bajo `throttle:api`—; el arreglo, sin
     * embargo, **vale igual sin endpoint**: el coste ya se pagaba.
     *
     * ⚠️ **El suelo tiene que dejar pasar el pack SIN FRANJA**, o cambiaría la conducta: un pack sin
     * franja da `isFinishedInPractice() === false` y **sigue avisando** (lo dice el párrafo de
     * arriba, y hay caso que lo fija). De ahí el `whereNull('slot_id') OR fecha >= suelo`, sobre el
     * MISMO ítem — no sobre el pedido—, para que un pedido con un pack viejo y otro futuro siga
     * entrando y el corte fino lo siga dando PHP.
     *
     * @return list<PendingGuestForm>
     */
    public function pendingGuestFormsFor(int $userId): array
    {
        // Mismo colchón de un día y por el mismo motivo que `upcomingItems()`: la fecha de franja es
        // naive `Y-m-d` y el corte exacto lo hace PHP.
        $floor = Carbon::now()->subDay()->toDateString();

        $orders = Order::query()
            ->where('user_id', $userId)
            ->where('status', Order::STATUS_PAID)
            ->whereHas('items', fn ($q) => $q->whereNull('cancelled_at')
                ->whereHas('ticketType', fn ($t) => $t->where('type', TicketType::TYPE_PACK))
                ->where(fn ($i) => $i->whereNull('slot_id')
                    ->orWhereHas('slot', fn ($s) => $s->whereDate('date', '>=', $floor))))
            ->with(['items.ticketType', 'items.slot'])
            ->get();

        $pending = [];

        // Individualizado POR RESERVA (#217): un aviso por cada pack pendiente cuya franja aún no ha
        // finalizado (no avisamos por un cumpleaños ya celebrado). El aviso apunta a ESA reserva
        // (el `OrderItem`), no al pedido.
        foreach ($orders as $order) {
            $items = $order->guestFormItems()
                ->filter(fn (OrderItem $i): bool => $i->needsGuestForm() && ! $i->isFinishedInPractice());

            foreach ($items as $item) {
                $pending[] = new PendingGuestForm(
                    reservationId: (int) $item->id,
                    productName: $item->displayProductName(),
                );
            }
        }

        return $pending;
    }

    /**
     * Una página del historial de reservas, ordenada para presentación
     * (`docs/specs/mis-reservas-por-reserva.md` §4.1).
     *
     * ⚠️⚠️ **Los dos ámbitos salen del MISMO predicado** ({@see terminated}), aplicado con `where` en
     * un lado y `whereNot` en el otro. No son dos consultas que se complementan de casualidad: son
     * una partición por construcción, y eso es lo que impide que una reserva no salga en ninguna de
     * las dos pantallas que consumen esto. Lo asevera `MeReservationScopeTest`.
     *
     * ⚠️ **El `leftJoin` a `slots` no es una optimización: es lo que permite ORDENAR en SQL.** Sin él
     * habría que traer el histórico entero y ordenarlo en PHP para poder paginarlo — el coste que
     * `pendingGuestFormsFor()` acaba de dejar de pagar. `left` y no `join` porque una reserva **sin
     * franja** tiene que seguir apareciendo.
     *
     * @return LengthAwarePaginator<int, OrderItem>
     */
    public function pageFor(int $userId, ReservationScope $scope, int $perPage, int $page): LengthAwarePaginator
    {
        $query = OrderItem::query()
            // ⚠️ Sin este `select` explícito, el `join` mezcla columnas de `orders` y `slots` con las
            // del ítem y Eloquent hidrata un `OrderItem` con el `id` equivocado.
            ->select('order_items.*')
            ->whereNull('order_items.parent_item_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('slots', 'slots.id', '=', 'order_items.slot_id')
            ->where('orders.user_id', $userId)
            // Mismo eager-load que `MeOrdersController`, y por el mismo motivo: sin él, pintar la
            // tarjeta con su ledger y su post-form dispara N+1 por cada fila de la página.
            ->with([
                'ticketType', 'slot', 'children.ticketType',
                'order.items.ticketType', 'order.items.slot', 'order.payments.refunds', 'order.adjustments',
            ]);

        $query = $scope === ReservationScope::PAST
            ? $query->where(fn (Builder $q) => $this->terminated($q))
            : $query->whereNot(fn (Builder $q) => $this->terminated($q));

        return $this->ordered($query, $scope)->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * **El predicado ÚNICO: qué es una reserva terminada.** Se escribe aquí y en ningún otro sitio.
     *
     * ⚠️⚠️ **Cada rama es NULL-SAFE, y no es pulcritud: es la condición para que `whereNot()` sea el
     * complemento de verdad.** En SQL, `slots.date < '2026-08-23'` con `slots.date` a NULL no vale
     * *false* sino *unknown*, y `NOT unknown` sigue siendo *unknown* → la fila se cae de los DOS
     * lados. **Medido**: sin la guarda, una reserva sin franja desaparece de la aplicación entera y la
     * partición pasa de 9 a 8 sin que falle nada más.
     *
     * ⚠️ **Y la guarda es UNA, no dos, a propósito.** La primera versión ponía
     * `whereNotNull('slots.date')` **y** `whereNotNull('slots.end_time')`, que suena más defensivo y
     * en realidad es peor: como `slots.date` y `slots.end_time` son `NOT NULL` en el esquema, las dos
     * solo valen NULL a la vez —cuando el `leftJoin` no encuentra franja—, así que **cada una tapa a
     * la otra y ninguna se puede medir mutándola**. Comprobado: quitar cualquiera de las dos dejaba
     * el test en verde. Es literalmente `DECISIONES #112` —«una guarda con dos fuentes redundantes no
     * se puede medir mutando una sola»—, y por eso se conserva la que espeja el `end_time === null`
     * de `OrderItem::isFinishedInPractice()`, que es la que tiene significado.
     *
     * ⚠️ **El corte de «disfrutada» es EXACTO, no por día.** Espeja `OrderItem::isFinishedInPractice()`
     * —«hay hora de fin y ya pasó»— con una comparación de dos columnas en vez de concatenar fecha y
     * hora, que es lo que la haría depender del dialecto de la BD (la suite corre en SQLite y
     * producción en MySQL). Un slot **sin `end_time` nunca está finalizado**, igual que allí, y por
     * eso la primera guarda de esa rama es `whereNotNull('slots.end_time')`.
     * ▶ Comparar por día habría hecho que esta partición y `upcomingFor()` **discreparan**: una franja
     * de ayer sin hora de fin es «próxima» para el bloque de cuenta y sería «pasada» aquí.
     *
     * ⚠️ **La caducidad de un pedido también es de hecho, no de columna**: `orders:expire` corre por
     * cron y puede ir por detrás, así que se espeja `Order::isExpiredInPractice()`.
     */
    private function terminated(Builder $query): Builder
    {
        $now = Carbon::now();

        return $query
            ->whereNotNull('order_items.cancelled_at')
            ->orWhereIn('orders.status', [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED, Order::STATUS_EXPIRED])
            ->orWhere(fn (Builder $q) => $q
                ->where('orders.status', Order::STATUS_PENDING)
                ->whereNotNull('orders.expires_at')
                ->where('orders.expires_at', '<', $now))
            ->orWhere(fn (Builder $q) => $q
                ->whereNotNull('slots.end_time')
                ->where(fn (Builder $e) => $e
                    ->whereDate('slots.date', '<', $now->toDateString())
                    ->orWhere(fn (Builder $sameDay) => $sameDay
                        ->whereDate('slots.date', '=', $now->toDateString())
                        ->where('slots.end_time', '<', $now->format('H:i:s')))));
    }

    /**
     * El orden de cada ámbito.
     *
     * ⚠️ **El desempate por `id` no es cosmético**: sin un orden total, dos reservas del mismo día y
     * hora pueden intercambiarse entre dos peticiones y hacer que una **desaparezca al pasar de
     * página** mientras otra sale dos veces. Es el fallo clásico de paginar con orden no determinista.
     *
     * ⚠️ **`CASE WHEN … IS NULL` en vez de `NULLS FIRST/LAST`**: eso último no existe en MySQL, y la
     * suite corre en SQLite. El `CASE` funciona igual en los dos.
     *
     * @param  Builder<OrderItem>  $query
     * @return Builder<OrderItem>
     */
    private function ordered(Builder $query, ReservationScope $scope): Builder
    {
        // Las que NO tienen franja van primero en «próximas» —normalmente esperan algo del cliente— y
        // al final en el historial, donde no hay fecha por la que colocarlas.
        $noSlotFirst = $scope === ReservationScope::UPCOMING;
        $direction = $scope === ReservationScope::UPCOMING ? 'asc' : 'desc';

        return $query
            ->orderByRaw('CASE WHEN slots.date IS NULL THEN '.($noSlotFirst ? '0 ELSE 1' : '1 ELSE 0').' END')
            ->orderBy('slots.date', $direction)
            ->orderBy('slots.start_time', $direction)
            ->orderBy('order_items.id', $direction);
    }

    /** @return Collection<int, OrderItem> */
    private function upcomingItems(int $userId): Collection
    {
        // Margen de 1 día en el filtro SQL: la convención de fechas es naive `Y-m-d` con
        // APP_TIMEZONE=UTC mientras el negocio opera en su zona local; el colchón evita descartar
        // por error una reserva de hoy cerca de la medianoche (el corte exacto lo hace PHP).
        $floor = Carbon::now()->subDay()->toDateString();

        return OrderItem::query()
            ->active()
            ->whereNull('parent_item_id')
            ->whereNotNull('slot_id')
            ->whereHas('order', fn ($q) => $q->where('user_id', $userId)->where('status', Order::STATUS_PAID))
            ->whereHas('slot', fn ($q) => $q->whereDate('date', '>=', $floor))
            ->with(['ticketType', 'slot'])
            ->get()
            ->reject(fn (OrderItem $item): bool => $item->isFinishedInPractice())
            ->sortBy(fn (OrderItem $item): string => $this->slotSortKey($item))
            ->values();
    }

    /** Clave de orden estable (fecha + hora de inicio) para elegir la franja más próxima. */
    private function slotSortKey(OrderItem $item): string
    {
        $date = $item->slot?->date?->format('Y-m-d') ?? '9999-12-31';
        $time = substr((string) ($item->slot?->start_time ?? '00:00:00'), 0, 8);

        return $date.' '.$time;
    }
}
