<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Presenter del "Resumen del día" (PDF A4 horizontal imprimible) para la
 * operativa física: el listado de las reservas/entradas de UN día, ordenado por
 * hora, con filtro de tipo (todas / cumpleaños / entradas).
 *
 * Reutiliza la regla canónica "qué es una reserva de agenda"
 * ({@see OrderItem::scopePaidScheduledPrincipal()} — principal + con franja +
 * pedido pagado + no cancelado), la misma que alimentan el calendario y los
 * widgets del dashboard, para que el resumen NO derive de lo que se ve en
 * pantalla.
 *
 * Como la hoja individual ({@see ReservationSlip}): NUNCA incluye datos de cobro
 * sensibles; el controlador fuerza español. El presenter solo prepara datos.
 */
final class DailyReservationsSummary
{
    public const TYPE_ALL = 'all';

    public const TYPE_ENTRY = 'entry';

    public const TYPE_PACK = 'pack';

    /**
     * @param  Collection<int, OrderItem>  $items  items principales del día (ordenados por hora)
     */
    private function __construct(
        public readonly Carbon $date,
        public readonly string $type,
        public readonly Collection $items,
    ) {}

    /**
     * Construye el resumen de un día (YYYY-MM-DD o Carbon) y un tipo. El tipo se
     * normaliza a `all` si no es válido (no destructivo).
     */
    public static function for(string|Carbon $date, string $type = self::TYPE_ALL): self
    {
        $date = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $type = in_array($type, [self::TYPE_ENTRY, self::TYPE_PACK], true) ? $type : self::TYPE_ALL;
        $day = $date->toDateString();

        $query = OrderItem::query()
            ->paidScheduledPrincipal()
            ->slotDateBetween($day, $day)
            ->with(['ticketType.zone', 'slot', 'order.user']);

        if ($type === self::TYPE_ENTRY) {
            $query->whereHas('ticketType', fn ($q) => $q->where('type', TicketType::TYPE_ENTRY));
        } elseif ($type === self::TYPE_PACK) {
            $query->whereHas('ticketType', fn ($q) => $q->where('type', TicketType::TYPE_PACK));
        }

        // Orden estable por hora de inicio (HH:MM:SS ordena lexicográficamente),
        // desempatado por id para un orden determinista (sin flakes).
        $items = $query->get()
            ->sortBy(fn (OrderItem $i): string => ($i->slot?->start_time ?? '99:99:99')
                .str_pad((string) $i->id, 12, '0', STR_PAD_LEFT))
            ->values();

        return new self($date, $type, $items);
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    public function count(): int
    {
        return $this->items->count();
    }

    /** Total de invitados (suma de cantidades de los packs del listado). */
    public function guestsTotal(): int
    {
        return (int) $this->items
            ->filter(fn (OrderItem $i) => $i->ticketType?->isPack() ?? false)
            ->sum(fn (OrderItem $i) => (int) $i->quantity);
    }

    /** Total de entradas (suma de cantidades de las entradas del listado). */
    public function entriesTotal(): int
    {
        return (int) $this->items
            ->filter(fn (OrderItem $i) => ($i->ticketType?->isPack() ?? false) === false)
            ->sum(fn (OrderItem $i) => (int) $i->quantity);
    }

    /** Etiqueta del filtro aplicado (para la cabecera del PDF). */
    public function filterLabel(): string
    {
        return match ($this->type) {
            self::TYPE_PACK => __('admin.calendar.day_summary.type_packs'),
            self::TYPE_ENTRY => __('admin.calendar.day_summary.type_entries'),
            default => __('admin.calendar.day_summary.type_all'),
        };
    }

    /**
     * Filas del listado (una por reserva), ya formateadas para la tabla.
     *
     * @return list<array{
     *     time:?string, isPack:bool, typeLabel:string, product:string,
     *     zoneColor:string, customer:string, phone:?string, quantityLabel:string, celebrant:?string
     * }>
     */
    public function rows(): array
    {
        return $this->items->map(function (OrderItem $item): array {
            $tt = $item->ticketType;
            $isPack = $tt?->isPack() ?? false;

            return [
                // Ventana real del producto (entrada → entrada + duración); la rejilla de
                // aforo es de 60 min, así que no se usa slot->end_time. Fuente única.
                'time' => $item->displayTimeWindow(),
                'isPack' => $isPack,
                'typeLabel' => $isPack
                    ? __('admin.calendar.day_summary.type_pack')
                    : __('admin.calendar.day_summary.type_entry'),
                'product' => $tt?->tr('name') ?? '—',
                'zoneColor' => $tt?->zone?->color ?? ReservationSlip::ZONE_COLOR_FALLBACK,
                'customer' => $item->order?->user?->name ?? '—',
                'phone' => $item->order?->user?->phone,
                'quantityLabel' => $isPack
                    ? __('tickets.guests_count', ['count' => (int) $item->quantity])
                    : trans_choice('admin.orders.slip.entries_count', (int) $item->quantity, ['count' => (int) $item->quantity]),
                'celebrant' => $isPack ? $this->celebrantOf($item) : null,
            ];
        })->all();
    }

    /**
     * Nombre del homenajeado de un pack: el PRIMER campo del esquema del evento
     * con valor (por convención, el cumpleañero). null si no hay datos del evento.
     */
    private function celebrantOf(OrderItem $item): ?string
    {
        $data = $item->event_data;
        if (! is_array($data) || $data === []) {
            return null;
        }

        foreach ($item->ticketType?->eventFields() ?? [] as $field) {
            $value = $data[$field['key']] ?? null;
            if ($value !== null && $value !== '' && is_scalar($value)) {
                return (string) $value;
            }
        }

        return null;
    }
}
