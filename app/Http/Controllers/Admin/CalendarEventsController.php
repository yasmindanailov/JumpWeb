<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\TicketType;
use App\Support\Duration;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Fase 7.4 — Feed JSON del calendario unificado del panel (decisión #14).
 *
 * GET /admin/calendario/eventos?start=…&end=…&type=all|entry|pack
 *
 * Cada evento es un PRODUCTO individual (`order_item` principal), NO un pedido:
 * la clienta organiza la jornada por producto y, al pulsar, ve su detalle + la
 * referencia del pedido (decisión de sesión 7.4).
 *
 * Reglas de inclusión (todo defensivo, fuente de verdad = pedidos):
 *  - Solo items PRINCIPALES con franja: `parent_item_id` null + `slot_id` no null
 *    (los complementos no son eventos sueltos; cuelgan de su principal).
 *  - Solo de pedidos PAGADOS y items NO cancelados (`scopeActive`).
 *  - Acotado al rango visible que pide FullCalendar (`start`/`end`).
 *
 * Apariencia (decisión clienta 7.4 iter2): el FONDO de la píldora indica el
 * ESTADO operativo (verde preparado / gris sin preparar / atenuado finalizado)
 * vía `classNames`; el color de la ZONA (identidad de marca, #148) se pinta como
 * una línea vertical en el lateral, que el CSS consume desde `zoneColor`. Sin
 * escritura: la autorización es `calendar.view` + el middleware.
 */
class CalendarEventsController extends Controller
{
    /** Tope defensivo del rango consultado (evita queries desbocadas). */
    private const MAX_RANGE_DAYS = 120;

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('calendar.view') ?? false, 403);

        [$start, $end] = $this->range($request);
        $type = $request->string('type')->toString();

        // Regla compartida "qué es un evento de agenda" (principal + con franja +
        // pedido pagado + no cancelado) centralizada en el scope, reutilizada por
        // los widgets del dashboard (7.4 iter2). Aquí solo se le añade el rango
        // visible que pide FullCalendar y el eager-load de presentación.
        $query = OrderItem::query()
            ->paidScheduledPrincipal()
            ->whereHas('slot', fn ($q) => $q->whereBetween('date', [$start->toDateString(), $end->toDateString()]))
            ->with(['slot', 'ticketType.zone', 'order:id,code,user_id', 'order.user:id,name']);

        if (in_array($type, [TicketType::TYPE_ENTRY, TicketType::TYPE_PACK], true)) {
            $query->whereHas('ticketType', fn ($q) => $q->where('type', $type));
        }

        $events = $query->get()
            ->map(fn (OrderItem $item) => $this->toEvent($item))
            ->filter()
            ->values();

        return response()->json($events);
    }

    /**
     * Rango [start, end] saneado. FullCalendar envía ISO; si falta o es absurdo,
     * cae al mes actual y se acota a MAX_RANGE_DAYS.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(Request $request): array
    {
        $start = $this->parseDate($request->query('start')) ?? CarbonImmutable::now()->startOfMonth();
        $end = $this->parseDate($request->query('end')) ?? CarbonImmutable::now()->endOfMonth();

        if ($end->lessThan($start)) {
            $end = $start;
        }

        if ($start->diffInDays($end) > self::MAX_RANGE_DAYS) {
            $end = $start->addDays(self::MAX_RANGE_DAYS);
        }

        return [$start, $end];
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Mapea un OrderItem a un evento de FullCalendar.
     *
     * Contenido del card (decisión clienta 7.4 iter2): nombre del producto
     * (`title`) + primer nombre del cliente + meta (`meta`: nº de invitados en
     * packs; cantidad + duración en entradas). El homenajeado de los packs NO va
     * en el título (vive en el modal de detalle). El marcado de "preparado" se
     * hace SOLO desde el modal: el card ya no lleva checkbox.
     *
     * @return array<string, mixed>|null
     */
    private function toEvent(OrderItem $item): ?array
    {
        $slot = $item->slot;
        $type = $item->ticketType;
        if ($slot === null || $type === null) {
            return null;
        }

        $date = $slot->date instanceof Carbon
            ? $slot->date->format('Y-m-d')
            : (string) $slot->date;
        $startTime = substr((string) $slot->start_time, 0, 8) ?: '00:00:00';
        $start = $date.'T'.$startTime;

        // Fin = inicio + duración del producto; si es ilimitada (entrada sin
        // duración), cae a la hora de cierre de la franja.
        if (! empty($type->duration_min)) {
            $end = CarbonImmutable::parse($start)->addMinutes((int) $type->duration_min)->format('Y-m-d\TH:i:s');
        } else {
            $endTime = substr((string) $slot->end_time, 0, 8) ?: $startTime;
            $end = $date.'T'.$endTime;
        }

        $status = $item->displayStatusForCustomer(); // active | finished
        $zoneColor = $type->zone?->color ?: '#9CA3AF';

        // Estado del post-form por-niño (#217): SOLO packs que lo piden → 'ok' | 'pending' | null.
        // Es un eje ORTOGONAL al estado operativo (que ocupa el fondo de la píldora): viaja como un
        // dato aparte y el front lo pinta como un badge separado, no como otra clase de fondo.
        $formStatus = $item->guestFormStatus();

        return [
            'id' => (string) $item->id,
            'title' => (string) ($type->tr('name') ?? ''),
            'start' => $start,
            'end' => $end,
            // El fondo de la píldora lo decide el ESTADO operativo (neutro
            // activo / atenuado finalizado) vía estas `classNames`; el color de
            // la ZONA va aparte (línea lateral, abajo).
            'classNames' => ['fc-event--'.$status],
            'extendedProps' => [
                'orderCode' => $item->order?->code,
                'operativeStatus' => $status,
                // P8: tipo de producto → el front pinta el icono (entrada → ticket, cumpleaños → tarta)
                // tintado con el color de zona, EN LUGAR del antiguo borde lateral de color.
                'isPack' => $type->isPack(),
                // Color de la zona = línea vertical lateral del card (lo pinta el
                // CSS desde la variable `--jj-zone-color` que fija el front).
                'zoneColor' => $zoneColor,
                'customerFirstName' => $this->firstName($item->order?->user?->name),
                'meta' => $this->metaLabel($item, $type),
                'formStatus' => $formStatus,
                'formStatusLabel' => $formStatus !== null ? __('admin.calendar.guest_form_'.$formStatus) : null,
            ],
        ];
    }

    /**
     * Etiqueta secundaria del card (decisión clienta 7.4 iter2, punto 3): se
     * muestra cuando el evento tiene espacio (semana/día). Packs (cumpleaños) →
     * nº de invitados; entradas → cantidad + duración.
     */
    private function metaLabel(OrderItem $item, TicketType $type): ?string
    {
        if ($type->isPack()) {
            return __('tickets.guests_count', ['count' => $item->quantity]);
        }

        $label = $item->quantity.' '.__('admin.orders.item_detail.unit_entries');
        $duration = Duration::formatHumane($type->duration_min);

        return $duration !== null ? $label.' · '.$duration : $label;
    }

    /** Primer nombre del cliente (solo la primera palabra). */
    private function firstName(?string $name): ?string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return explode(' ', $name)[0];
    }
}
