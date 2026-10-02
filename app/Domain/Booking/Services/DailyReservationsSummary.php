<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\TicketType;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Presenter del "Resumen" imprimible (PDF A4 horizontal) para la operativa física: el listado de las reservas/entradas
 * de UN día o —desde la L5 de `#876` (el owner: «semanal y mensual», «en horizontal, con los datos que quepan»)— de su
 * SEMANA (de lunes a domingo) o de su MES natural, por días y por hora, con filtro de tipo (todas / cumpleaños /
 * excursiones / entradas).
 *
 * Reutiliza la regla canónica "qué es una reserva de agenda"
 * ({@see OrderItem::scopePaidScheduledPrincipal()} — principal + con franja +
 * pedido pagado + no cancelado), la misma que alimentan el calendario y los
 * widgets del dashboard, para que el resumen NO derive de lo que se ve en
 * pantalla.
 *
 * ▶ **Cumpleaños y excursiones, separados** (`#879`): las dos son packs, y «Solo cumpleaños» las mezclaba. Un CUMPLEAÑOS es
 * el pack cuya reserva pregunta la EDAD de quien cumple (`TicketType::celebrantAgeFieldKey()`, la regla con la que la compra
 * decide qué es una fiesta); una EXCURSIÓN, el pack que no la pregunta.
 * ▶ **La merienda y la tarta, en su columna** (`#879`; el owner, al verla: fuera «tipo», «lista» y «por cobrar»; dentro «la
 * merienda que será» y la tarta): por DATOS del enganche, nunca por nombre —la merienda es lo elegido de un GRUPO de elección
 * (el menú) o lo marcado como el menú de la invitación; la tarta, lo pedido del bloque «tarta» de la lista de invitados
 * (`ProductAddon::BLOCK_CAKE`), o «Sin tarta» si lo contestó así—. El resto de complementos, en la línea del producto.
 * Como la hoja de cada reserva: sin datos de cobro. El controlador fuerza español. El presenter solo prepara datos.
 */
final class DailyReservationsSummary
{
    public const TYPE_ALL = 'all';

    public const TYPE_ENTRY = 'entry';

    /** Los CUMPLEAÑOS: los packs que preguntan la edad de quien cumple. */
    public const TYPE_PACK = 'pack';

    /** Las EXCURSIONES: los packs que no la preguntan (`#879`). */
    public const TYPE_TRIP = 'trip';

    public const PERIOD_DAY = 'day';

    public const PERIOD_WEEK = 'week';

    public const PERIOD_MONTH = 'month';

    /**
     * @param  Collection<int, OrderItem>  $items  items principales del periodo (por fecha y hora)
     */
    private function __construct(
        public readonly Carbon $date,
        public readonly string $type,
        public readonly string $period,
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly Collection $items,
    ) {}

    /**
     * Construye el resumen de un día (YYYY-MM-DD o Carbon), su semana o su mes, y un tipo. El tipo y el periodo se
     * normalizan (no destructivo): un tipo que no existe, todas; un periodo que no existe, el día.
     */
    public static function for(string|Carbon $date, string $type = self::TYPE_ALL, string $period = self::PERIOD_DAY): self
    {
        $date = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);
        $type = in_array($type, [self::TYPE_ENTRY, self::TYPE_PACK, self::TYPE_TRIP], true) ? $type : self::TYPE_ALL;
        $period = in_array($period, [self::PERIOD_WEEK, self::PERIOD_MONTH], true) ? $period : self::PERIOD_DAY;
        [$from, $to] = match ($period) {
            self::PERIOD_WEEK => [$date->copy()->startOfWeek(Carbon::MONDAY), $date->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay()],
            self::PERIOD_MONTH => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()->startOfDay()],
            default => [$date->copy()->startOfDay(), $date->copy()->startOfDay()],
        };

        $query = OrderItem::query()
            ->paidScheduledPrincipal()
            ->slotDateBetween($from->toDateString(), $to->toDateString())
            // `children.ticketType` para la línea de COMPLEMENTOS de cada fila (ojo del owner,
            // `specs/hora-extra.md` §8.6): sin ella, la hora extra —que es inventario operativo del
            // día— era invisible justo en la hoja con la que se abre la jornada. Y los ENGANCHES del pack
            // (`configurableAddons`, también los que ya no se venden): dicen qué complemento es la merienda y
            // cuál la tarta (`#879`).
            // Y sus GRUPOS DE OPCIONES (`#914`, del SPA): uno con «hay que elegir» sin contestar sale «sin elegir».
            ->with(['ticketType.zone', 'ticketType.configurableAddons', 'ticketType.choiceGroups', 'slot', 'order.user', 'children.ticketType']);

        if ($type === self::TYPE_ENTRY) {
            $query->whereHas('ticketType', fn ($q) => $q->where('type', TicketType::TYPE_ENTRY));
        } elseif ($type === self::TYPE_PACK || $type === self::TYPE_TRIP) {
            $query->whereHas('ticketType', fn ($q) => $q->where('type', TicketType::TYPE_PACK));
        }

        // Orden estable por fecha y hora de inicio (las dos ordenan lexicográficamente),
        // desempatado por id para un orden determinista (sin flakes).
        $items = $query->get()
            ->filter(fn (OrderItem $i): bool => match ($type) {
                self::TYPE_PACK => self::isBirthday($i),
                self::TYPE_TRIP => ! self::isBirthday($i),
                default => true,
            })
            ->sortBy(fn (OrderItem $i): string => ($i->slot?->date?->toDateString() ?? '9999-99-99')
                .($i->slot?->start_time ?? '99:99:99').str_pad((string) $i->id, 12, '0', STR_PAD_LEFT))
            ->values();

        return new self($date, $type, $period, $from, $to, $items);
    }

    /** ¿Es un CUMPLEAÑOS? El pack cuya reserva pregunta la edad de quien cumple (`#879`). */
    private static function isBirthday(OrderItem $item): bool
    {
        return ($item->ticketType?->isPack() ?? false) && $item->ticketType->celebrantAgeFieldKey() !== null;
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
            self::TYPE_TRIP => __('admin.calendar.day_summary.type_trips'),
            self::TYPE_ENTRY => __('admin.calendar.day_summary.type_entries'),
            default => __('admin.calendar.day_summary.type_all'),
        };
    }

    /**
     * Las filas por DÍA, en orden (un día sin reservas no sale): lo que pinta el PDF con un título por día en la semana y el
     * mes.
     *
     * @return list<array{date: CarbonInterface, rows: list<array<string, mixed>>}>
     */
    public function days(): array
    {
        return collect($this->rows())
            ->groupBy(fn (array $row): string => $row['date']?->toDateString() ?? '')
            ->map(fn (Collection $filas): array => ['date' => $filas->first()['date'], 'rows' => $filas->values()->all()])
            ->values()
            ->all();
    }

    /**
     * Filas del listado (una por reserva), ya formateadas para la tabla.
     *
     * @return list<array{
     *     date:?CarbonInterface, time:?string, isPack:bool, product:string, zoneColor:string, customer:string, phone:?string,
     *     quantityLabel:string, celebrant:?string, age:?string, snack:?string, cake:?string,
     *     addons:list<array{name:string, quantity:int}>
     * }>
     */
    public function rows(): array
    {
        return $this->items->map(function (OrderItem $item): array {
            $tt = $item->ticketType;
            $isPack = $tt?->isPack() ?? false;
            // Los complementos VIVOS, repartidos por su enganche: la merienda, la tarta y el resto. Un complemento
            // cancelado no es operativa (la hoja individual sí los enseña tachados, porque allí el dinero tiene que cuadrar).
            $vivos = $item->children->reject(fn (OrderItem $child): bool => $child->isCancelled());
            $merienda = $vivos->filter(fn (OrderItem $child): bool => self::isSnack($item, $child));
            $tarta = $vivos->filter(fn (OrderItem $child): bool => self::isCake($item, $child) && (int) $child->quantity > 0);
            $linea = fn (OrderItem $child): array => ['name' => $child->ticketType?->tr('name') ?? '—', 'quantity' => (int) $child->quantity];

            return [
                'date' => $item->slot?->date,
                // Ventana real del producto (entrada → entrada + duración); la rejilla de
                // aforo es de 60 min, así que no se usa slot->end_time. Fuente única.
                'time' => $item->displayTimeWindow(),
                'isPack' => $isPack,
                'product' => $item->displayProductName(),
                'zoneColor' => $tt?->zone?->color ?? ReservationSlip::ZONE_COLOR_FALLBACK,
                'customer' => $item->order?->user?->name ?? '—',
                'phone' => $item->order?->user?->phone,
                'quantityLabel' => $isPack
                    ? __('tickets.guests_count', ['count' => (int) $item->quantity])
                    : trans_choice('admin.orders.slip.entries_count', (int) $item->quantity, ['count' => (int) $item->quantity]),
                'celebrant' => $isPack ? self::celebrantOf($item) : null,
                'age' => $isPack ? self::ageOf($item) : null,
                // La merienda que será: lo elegido del menú (`#879`); y lo que falta por elegir (`#914`).
                'snack' => self::snackOf($merienda, $item),
                // La tarta: lo pedido de su bloque de la lista de invitados («2 × …» si son varias), o «Sin tarta» si el
                // titular lo contestó así (`cake_declined_at`); si no ha contestado, nada.
                'cake' => $tarta->isNotEmpty()
                    ? $tarta->map(fn (OrderItem $c): string => ((int) $c->quantity > 1 ? $c->quantity.' × ' : '').($c->ticketType?->tr('name') ?? '—'))->implode(' · ')
                    : ($isPack && $item->cake_declined_at !== null ? __('admin.calendar.day_summary.cake_none') : null),
                // El RESTO de complementos (ojo del owner, `specs/hora-extra.md` §8.6): el
                // resumen es la hoja con la que se abre el día y una hora extra vendida es
                // inventario operativo — sin esta línea el operador no sabía que alguien se queda.
                'addons' => $vivos
                    ->reject(fn (OrderItem $child): bool => $merienda->contains($child) || self::isCake($item, $child))
                    ->map($linea)
                    ->values()
                    ->all(),
            ];
        })->all();
    }

    /**
     * La columna MERIENDA: lo elegido (`#879`) y, del SPA (`[DECIDIDO owner]` `#914`), cada GRUPO DE OPCIONES con «hay que
     * elegir» que la fiesta no ha contestado, con su título: «¿Qué merienda?: sin elegir» (`#913`: «el parque la ve "sin
     * elegir"», y decide). Lo pendiente lo dice el dominio (`PostFormAddons::unansweredRequiredGroups()`), con lo ya cargado.
     *
     * @param  Collection<int, OrderItem>  $merienda
     */
    private static function snackOf(Collection $merienda, OrderItem $item): ?string
    {
        $partes = $merienda->map(fn (OrderItem $c): string => (string) ($c->ticketType?->tr('name') ?? '—'))->values()->all();
        foreach (PostFormAddons::unansweredRequiredGroups($item) as $grupo) {
            $partes[] = __('admin.orders.choice_unanswered', ['group' => $grupo->displayTitle()]);
        }

        return $partes === [] ? null : implode(' · ', $partes);
    }

    /** El ENGANCHE de un complemento de la reserva con su pack (`product_addons`), o `null` si ya no está enganchado. */
    private static function pivotOf(OrderItem $item, OrderItem $child): ?ProductAddon
    {
        return $item->ticketType?->configurableAddons
            ->first(fn (TicketType $addon): bool => (int) $addon->getKey() === (int) $child->ticket_type_id)
            ?->addonPivot();
    }

    /** ¿Es la MERIENDA? Lo elegido de un grupo de elección (el menú) o lo marcado como el menú de la invitación (D12). */
    private static function isSnack(OrderItem $item, OrderItem $child): bool
    {
        $pivot = self::pivotOf($item, $child);

        return $pivot !== null && ($pivot->choiceGroup() !== null || $pivot->showsInInvitation());
    }

    /** ¿Es la TARTA? Lo del bloque «tarta» de la lista de invitados (`ProductAddon::BLOCK_CAKE`, F5 de la fiesta, `#749`). */
    private static function isCake(OrderItem $item, OrderItem $child): bool
    {
        return self::pivotOf($item, $child)?->postformBlock() === ProductAddon::BLOCK_CAKE;
    }

    /**
     * Nombre del homenajeado de un pack, por la regla única de {@see TicketType::celebrantNameFieldKey()}.
     * null si el pack no lo declara o aún no está contestado.
     *
     * ⚠️ **Antes era «el primer campo del esquema con valor»**, y eso ponía la EDAD en la columna
     * «Homenajeado» en cuanto el nombre se pedía en el formulario de invitados y aún no estaba
     * rellenado (`DECISIONES #692`): el primer campo con valor pasaba a ser otro.
     */
    private static function celebrantOf(OrderItem $item): ?string
    {
        $key = $item->ticketType?->celebrantNameFieldKey();
        $value = $key === null ? null : ($item->event_data[$key] ?? null);

        return is_scalar($value) && trim((string) $value) !== '' ? (string) $value : null;
    }

    /** La EDAD que cumple, si el pack la pregunta y está contestada (`celebrantAgeFieldKey()`): «6 años». */
    private static function ageOf(OrderItem $item): ?string
    {
        $key = $item->ticketType?->celebrantAgeFieldKey();
        $value = $key === null ? null : ($item->event_data[$key] ?? null);

        return is_numeric($value) ? trans_choice('admin.calendar.day_summary.age_value', (int) $value, ['count' => (int) $value]) : null;
    }
}
