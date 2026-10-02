<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\GateReservation;
use App\Domain\Booking\Contracts\GateReservations;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Platform\Services\PersonNameKey;

/**
 * Fase 6 · subsistema A — la implementación de `GateReservations` (`docs/specs/identidad-qr-puerta.md`
 * §9.2 A·3): una consulta DIRIGIDA por fecha y el dinero por **el LIBRO de la reserva**
 * (`OrderBook::forReservation()`, `DECISIONES #305`; T3·2): lo pagado y el saldo con su clase.
 *
 * ⚠️ Es una SUPERFICIE del desglose (`LedgerSingleSourceTest::SURFACES`): pinta lo que el ledger dice,
 * no deriva ningún canal restando otros. Y el aviso de §4.7 vale aquí más que en ningún sitio: un
 * dato ROTO en la base de datos (`R-L6UTIA`) saldrá tal cual y lo verá un empleado con el cliente
 * delante — *comprueba si el dato es real antes de buscar el fallo en el código*.
 */
class GateReservationsReader implements GateReservations
{
    public function __construct(private readonly MixedPartySurcharge $mixedParty) {}

    public function forHolder(int $userId, string $fromDate, string $toDate): array
    {
        // La rueda de las pulseras (la P2, D11): dos ajustes y una consulta para TODA la ficha, nunca una por fila.
        $wheel = WristbandWheel::fromSettings();

        return OrderItem::query()
            ->active()
            ->whereNull('parent_item_id')
            ->whereNotNull('slot_id')
            ->whereHas('order', fn ($q) => $q->where('user_id', $userId)->where('status', Order::STATUS_PAID))
            ->whereHas('slot', fn ($q) => $q->whereDate('date', '>=', $fromDate)->whereDate('date', '<=', $toDate))
            // ⚠️ Los eager anidados de `order.*` no son adorno: el libro recorre TODAS las líneas del
            // pedido (`OrderBook::compose`: `isFinishedInPractice()` camina `slot`, y en una línea
            // HIJA `parent->slot`; las etiquetas caminan `ticketType`; las devoluciones,
            // `payments.refunds`). Sin ellos, cada fila costaba consultas POR FILA en la pantalla
            // de puerta; lo vigilan los presupuestos de `GateProfileTest` y `MixedPartyParkSurfacesTest`.
            // `ticketType.zone`: la zona de la fila de la Puerta nueva (`specs/puerta-nueva.md` §4.4, la P1), UN lote; y desde
            // la P2, el color fijo y la zona de salto de un pack, un lote cada uno.
            ->with([
                'ticketType.zone', 'ticketType.wristbandColor', 'ticketType.gateZone', 'slot', 'children.ticketType',
                'order.adjustments', 'order.payments.refunds',
                'order.items.slot', 'order.items.ticketType', 'order.items.parent.slot',
            ])
            ->get()
            ->sortBy(fn (OrderItem $item): string => ($item->slot?->date?->format('Y-m-d') ?? '9999-12-31').' '.substr((string) ($item->slot?->start_time ?? '00:00:00'), 0, 8))
            ->values()
            ->map(function (OrderItem $item) use ($wheel): GateReservation {
                /** @var Order $order */
                $order = $item->order;
                $book = OrderBook::forReservation($order, $item);
                $children = $item->children->reject(fn (OrderItem $child): bool => $child->isCancelled());
                $zone = $this->gateZone($item);

                // T3 · E (`specs/cumple-mixto.md` §23.2): lo ESCRITO del suplemento de fiesta mixta,
                // para que el empleado vea la diferencia por cabeza con el cliente delante en vez de
                // hacer la cuenta de memoria. `written()` lee `order.adjustments` y `children`, que
                // este reader ya carga — cero consultas nuevas por fila.
                $written = $this->mixedParty->written($item);

                return new GateReservation(
                    orderId: (int) $order->getKey(),
                    orderCode: (string) $order->code,
                    orderItemId: (int) $item->getKey(),
                    date: (string) $item->slot?->date?->format('Y-m-d'),
                    timeWindow: $item->displayTimeWindow(),
                    productName: $item->displayProductName(),
                    isEntry: $item->ticketType?->type === TicketType::TYPE_ENTRY,
                    quantity: (int) $item->quantity,
                    addons: $children
                        ->reject(fn (OrderItem $child): bool => (bool) $child->ticketType?->handed_at_gate)
                        ->map(fn (OrderItem $child): string => $child->quantity.' × '.($child->ticketType?->tr('name') ?? ''))
                        ->values()
                        ->all(),
                    paidCents: $book->paidCents,
                    balanceKind: $book->balance->kind,
                    balanceCents: $book->balance->cents,
                    chargeMethod: $book->paymentMethod(),
                    paidAt: $order->paid_at?->toIso8601String(),
                    createdAt: (string) $order->created_at?->toIso8601String(),
                    mixedPartyLines: array_map(static fn (array $l): array => [
                        'name' => $l['name'],
                        'count' => $l['count'],
                        'unit_cents' => $l['unit'],
                    ], $written['lines']),
                    mixedPartySurchargeCents: $written['cents'],
                    mixedPartyCredit: $written['credit'] === null ? null : [
                        'label' => $written['credit']['label'],
                        'cents' => $written['credit']['cents'],
                    ],
                    // Las fichas con nombre de la fiesta (T6·4): salen de `guest_data`, que ya está en
                    // la línea cargada, así que no cuestan ni una consulta.
                    partyGuests: $this->partyGuests($item),
                    honoreeName: $item->honoreeName(),
                    invitationOffered: $item->ticketType?->offersGuestInvitation() ?? false,
                    waiverOffered: ($item->ticketType?->guardianMode() ?? TicketType::GUARDIAN_NONE) !== TicketType::GUARDIAN_NONE,
                    zoneName: $zone === null ? null : (string) $zone->tr('name'),
                    zoneSlug: $zone?->slug,
                    // La ilimitada es «sin duración» (`TicketType::isUnlimited()`: vacío, también un 0).
                    durationMinutes: $item->ticketType === null || $item->ticketType->isUnlimited() ? null : (int) $item->ticketType->duration_min,
                    startTime: $item->slot?->start_time === null ? null : substr((string) $item->slot->start_time, 0, 5),
                    isParty: $item->ticketType?->type === TicketType::TYPE_PACK,
                    guestAgeMax: $item->ticketType?->guest_age_max === null ? null : (int) $item->ticketType->guest_age_max,
                    wristband: $this->wristband($item, $wheel),
                    handedAtGate: $children
                        ->filter(fn (OrderItem $child): bool => (bool) $child->ticketType?->handed_at_gate)
                        ->map(fn (OrderItem $child): array => $this->handedAtGate($child))
                        ->values()
                        ->all(),
                );
            })
            ->all();
    }

    /**
     * La zona de la FILA en la Puerta (la P2, D13): la de salto de un pack si la tiene —sus invitados saltan en Kids o en
     * Jump, aunque el pack viva en la zona de su sala— y, si no, la del producto.
     */
    private function gateZone(OrderItem $item): ?Zone
    {
        $type = $item->ticketType;
        if ($type?->type === TicketType::TYPE_PACK && $type->gateZone !== null) {
            return $type->gateZone;
        }

        return $type?->zone;
    }

    /**
     * La pulsera de una reserva (la P2, D11/D12): la FIJA del producto gana a la de la RUEDA por su hora de inicio. El hex,
     * solo si es `#rrggbb` (D15: va en un `style`); la frase se queda aunque no lo sea.
     *
     * @return array{one: string, other: string, hex: ?string}|null
     */
    private function wristband(OrderItem $item, WristbandWheel $wheel): ?array
    {
        $color = $item->ticketType->wristbandColor ?? $wheel->colorFor($item->slot?->start_time === null ? null : (string) $item->slot->start_time);
        if ($color === null) {
            return null;
        }

        return [
            'one' => (string) $color->name_one,
            'other' => (string) $color->name_other,
            'hex' => $color->hasValidHex() ? strtolower((string) $color->hex) : null,
        ];
    }

    /**
     * Un complemento que se ENTREGA en la puerta (la P2, D14): su cantidad, su rótulo en singular y en plural —sin rótulo,
     * su nombre; con uno solo, ese para los dos— y su icono (el del catálogo, `ProductIcon`).
     *
     * @return array{key: int, quantity: int, one: string, other: string, icon: string}
     */
    private function handedAtGate(OrderItem $child): array
    {
        $type = $child->ticketType;
        $name = (string) ($type?->tr('name') ?? '');
        $one = trim((string) $type?->gate_label_one);
        $other = trim((string) $type?->gate_label_other);

        return [
            'key' => (int) $child->ticket_type_id,
            'quantity' => (int) $child->quantity,
            'one' => $one !== '' ? $one : ($other !== '' ? $other : $name),
            'other' => $other !== '' ? $other : ($one !== '' ? $one : $name),
            'icon' => ProductIcon::forProduct($type?->icon, false),
        ];
    }

    /**
     * **Las fichas CON NOMBRE de una fiesta**, acotadas a la cantidad y normalizadas (T6·4, §4.8).
     *
     * ⚠️ La columna de nombre es **la primera `text`** del esquema por invitado, no la clave `name`:
     * una instalación puede renombrarla desde su panel (§7.2·R2).
     *
     * ⚠️ Solo si el producto OFRECE la invitación: sin ella, la puerta no tiene nada que cruzar y
     * publicar los nombres de los niños sería repartir datos de menores sin motivo.
     *
     * ⚠️ Sin la ficha de quien cumple (`invitedGuestRows()`, `#752`): viaja aparte (`OrderItem::honoreeName()`).
     *
     * @return list<array{name: string, key: string}>
     */
    private function partyGuests(OrderItem $item): array
    {
        $type = $item->ticketType;
        $nameKey = $type?->guestNameFieldKey();

        if ($type === null || $nameKey === null || ! $type->offersGuestInvitation()) {
            return [];
        }

        $guests = [];

        foreach ($item->invitedGuestRows() as $row) {
            $name = trim((string) ($row[$nameKey] ?? ''));
            if ($name !== '') {
                $guests[] = ['name' => $name, 'key' => PersonNameKey::for($name)];
            }
        }

        return $guests;
    }
}
