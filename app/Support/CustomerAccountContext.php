<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Contexto de cuenta del cliente para la web pública (#221): saludo, próxima reserva, número de
 * reservas próximas y formularios de reserva (#217) pendientes. Lo consumen el icono de cuenta
 * del nav (puntito de aviso) y el bloque del sidebar de compra (avatar + sub-línea + contador).
 * Se registra como SINGLETON con memoización por usuario → una sola pasada aunque varias vistas
 * lo pidan en la misma petición.
 *
 * Defensivo por diseño (convención de helpers del panel): ante cualquier fallo devuelve un
 * contexto vacío seguro. Una cortesía de UI nunca debe tumbar una página.
 */
class CustomerAccountContext
{
    /** @var array<int, array<string, mixed>> */
    private array $cache = [];

    /**
     * @return array{firstName: string, upcomingCount: int, nextReservation: ?array{dateLabel: string, timeWindow: ?string, productName: string}, pendingForms: list<array{productName: string, url: string}>, pendingFormsCount: int, hasPendingForm: bool}
     */
    public function for(User $user): array
    {
        return $this->cache[$user->id] ??= $this->build($user);
    }

    /** @return array<string, mixed> */
    private function build(User $user): array
    {
        $context = [
            'firstName' => $user->firstName(),
            'upcomingCount' => 0,
            'nextReservation' => null,
            'pendingForms' => [],
            'pendingFormsCount' => 0,
            'hasPendingForm' => false,
        ];

        try {
            $upcoming = $this->upcomingReservations($user);
            $context['upcomingCount'] = $upcoming->count();
            $context['nextReservation'] = $this->formatReservation($upcoming->first());

            $pending = $this->resolvePendingForms($user);
            $context['pendingForms'] = $pending;
            $context['pendingFormsCount'] = count($pending);
            $context['hasPendingForm'] = $pending !== [];
        } catch (\Throwable $e) {
            // Cortesía de UI: nunca rompemos la página por el contexto de cuenta.
            report($e);
        }

        return $context;
    }

    /**
     * Reservas FUTURAS del usuario: ítems principales (no addon), no cancelados, con franja y aún
     * no finalizados, de pedidos PAGADOS, ordenados por fecha+hora. Consulta DIRIGIDA (solo franjas
     * recientes o futuras) para no materializar todo el histórico en cada página pública; el corte
     * fino de "ya finalizada" lo da `isFinishedInPractice` en PHP (frontera con hora + zona horaria).
     *
     * @return Collection<int, OrderItem>
     */
    private function upcomingReservations(User $user): Collection
    {
        // Margen de 1 día en el filtro SQL: la convención de fechas es naive `Y-m-d` con
        // APP_TIMEZONE=UTC mientras el parque opera en Europe/Madrid; el colchón evita descartar
        // por error una reserva de hoy cerca de la medianoche (el corte exacto lo hace PHP).
        $floor = Carbon::now()->subDay()->toDateString();

        return OrderItem::query()
            ->active()
            ->whereNull('parent_item_id')
            ->whereNotNull('slot_id')
            ->whereHas('order', fn ($q) => $q->where('user_id', $user->id)->where('status', Order::STATUS_PAID))
            ->whereHas('slot', fn ($q) => $q->whereDate('date', '>=', $floor))
            ->with(['ticketType', 'slot'])
            ->get()
            ->reject(fn (OrderItem $item): bool => $item->isFinishedInPractice())
            ->sortBy(fn (OrderItem $item): string => $this->slotSortKey($item))
            ->values();
    }

    /**
     * @return ?array{dateLabel: string, timeWindow: ?string, productName: string}
     */
    private function formatReservation(?OrderItem $next): ?array
    {
        if ($next === null || $next->slot === null) {
            return null;
        }

        return [
            'dateLabel' => Str::ucfirst(
                Carbon::parse($next->slot->date)->locale(app()->getLocale())->isoFormat('ddd D MMM')
            ),
            'timeWindow' => $next->displayTimeWindow(),
            'productName' => (string) ($next->ticketType?->tr('name') ?? ''),
        ];
    }

    /**
     * Formularios de reserva (#217) pendientes: uno por pedido pagado con un pack que los pide y
     * cuya franja AÚN NO ha finalizado (simétrico a la próxima reserva — no avisamos por un
     * cumpleaños ya celebrado, que dejaría el puntito/aviso encendidos para siempre). La url
     * apunta a la ruta autenticada `reservation.guests`. La consulta se acota a pedidos con algún
     * ítem de tipo pack (los formularios solo existen en packs) para no recorrer el histórico de
     * entradas. Un pack sin franja (`isFinishedInPractice` → false) sigue avisando.
     *
     * @return list<array{productName: string, url: string}>
     */
    private function resolvePendingForms(User $user): array
    {
        $orders = $user->orders()
            ->where('status', Order::STATUS_PAID)
            ->whereHas('items', fn ($q) => $q->whereNull('cancelled_at')
                ->whereHas('ticketType', fn ($t) => $t->where('type', TicketType::TYPE_PACK)))
            ->with(['items.ticketType', 'items.slot'])
            ->get();

        $pending = [];

        // Individualizado POR RESERVA (#217): un aviso por cada pack pendiente cuya franja aún no ha
        // finalizado (no avisamos por un cumpleaños ya celebrado). La url apunta al post-form de ESA
        // reserva (`reservation.guests` con el `OrderItem`), no al pedido.
        foreach ($orders as $order) {
            $items = $order->guestFormItems()
                ->filter(fn (OrderItem $i): bool => $i->needsGuestForm() && ! $i->isFinishedInPractice());

            foreach ($items as $item) {
                $pending[] = [
                    'productName' => (string) ($item->ticketType?->tr('name') ?? ''),
                    'url' => route('reservation.guests', $item),
                ];
            }
        }

        return $pending;
    }

    /** Clave de orden estable (fecha + hora de inicio) para elegir la franja más próxima. */
    private function slotSortKey(OrderItem $item): string
    {
        $date = $item->slot?->date?->format('Y-m-d') ?? '9999-12-31';
        $time = substr((string) ($item->slot?->start_time ?? '00:00:00'), 0, 8);

        return $date.' '.$time;
    }
}
