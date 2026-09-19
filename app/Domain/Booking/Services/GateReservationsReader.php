<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Contracts\GateReservation;
use App\Domain\Booking\Contracts\GateReservations;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
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
            ->with([
                'ticketType', 'slot', 'children.ticketType',
                'order.adjustments', 'order.payments.refunds',
                'order.items.slot', 'order.items.ticketType', 'order.items.parent.slot',
            ])
            ->get()
            ->sortBy(fn (OrderItem $item): string => ($item->slot?->date?->format('Y-m-d') ?? '9999-12-31').' '.substr((string) ($item->slot?->start_time ?? '00:00:00'), 0, 8))
            ->values()
            ->map(function (OrderItem $item): GateReservation {
                /** @var Order $order */
                $order = $item->order;
                $book = OrderBook::forReservation($order, $item);

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
                    addons: $item->children
                        ->reject(fn (OrderItem $child): bool => $child->isCancelled())
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
                    invitationOffered: $item->ticketType?->offersGuestInvitation() ?? false,
                    waiverOffered: ($item->ticketType?->guardianMode() ?? TicketType::GUARDIAN_NONE) !== TicketType::GUARDIAN_NONE,
                );
            })
            ->all();
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

        foreach (array_slice($item->guestData(), 0, max(0, (int) $item->quantity)) as $row) {
            $name = trim((string) ($row[$nameKey] ?? ''));
            if ($name !== '') {
                $guests[] = ['name' => $name, 'key' => PersonNameKey::for($name)];
            }
        }

        return $guests;
    }
}
