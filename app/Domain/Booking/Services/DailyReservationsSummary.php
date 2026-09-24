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
            // `children.ticketType` para la línea de COMPLEMENTOS de cada fila (ojo del owner,
            // `specs/hora-extra.md` §8.6): sin ella, la hora extra —que es inventario operativo del
            // día— era invisible justo en la hoja con la que se abre la jornada.
            ->with(['ticketType.zone', 'slot', 'order.user', 'children.ticketType']);

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
     *     zoneColor:string, customer:string, phone:?string, quantityLabel:string, celebrant:?string,
     *     addons:list<array{name:string, quantity:int}>
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
                'product' => $item->displayProductName(),
                'zoneColor' => $tt?->zone?->color ?? ReservationSlip::ZONE_COLOR_FALLBACK,
                'customer' => $item->order?->user?->name ?? '—',
                'phone' => $item->order?->user?->phone,
                'quantityLabel' => $isPack
                    ? __('tickets.guests_count', ['count' => (int) $item->quantity])
                    : trans_choice('admin.orders.slip.entries_count', (int) $item->quantity, ['count' => (int) $item->quantity]),
                'celebrant' => $isPack ? $this->celebrantOf($item) : null,
                // Los COMPLEMENTOS de la reserva (ojo del owner, `specs/hora-extra.md` §8.6): el
                // resumen es la hoja con la que se abre el día y una hora extra vendida es
                // inventario operativo — sin esta línea el operador no sabía que alguien se queda.
                // Solo los VIVOS: un complemento cancelado no es operativa (la hoja individual sí
                // los enseña tachados, porque allí el dinero tiene que cuadrar).
                'addons' => $item->children
                    ->reject(fn (OrderItem $child): bool => $child->isCancelled())
                    ->map(fn (OrderItem $child): array => [
                        'name' => $child->ticketType?->tr('name') ?? '—',
                        'quantity' => (int) $child->quantity,
                    ])
                    ->values()
                    ->all(),
            ];
        })->all();
    }

    /**
     * Nombre del homenajeado de un pack, por la regla única de {@see TicketType::celebrantNameFieldKey()}.
     * null si el pack no lo declara o aún no está contestado.
     *
     * ⚠️ **Antes era «el primer campo del esquema con valor»**, y eso ponía la EDAD en la columna
     * «Homenajeado» en cuanto el nombre se pedía en el formulario de invitados y aún no estaba
     * rellenado (`DECISIONES #692`): el primer campo con valor pasaba a ser otro.
     */
    private function celebrantOf(OrderItem $item): ?string
    {
        $key = $item->ticketType?->celebrantNameFieldKey();
        $value = $key === null ? null : ($item->event_data[$key] ?? null);

        return is_scalar($value) && trim((string) $value) !== '' ? (string) $value : null;
    }
}
