<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Platform\Services\Duration;
use App\Domain\Platform\Services\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Presenter de la "Hoja de reserva" (PDF A4 imprimible para la operativa física
 * del parque). Envuelve un OrderItem PRINCIPAL + su Order y expone datos limpios
 * y formateados para la vista del PDF (`resources/views/pdf/reservation-slip.blade.php`).
 *
 * Por qué un presenter y no lógica en el blade:
 *  - Testeable sin renderizar PDF (los tests asertan sobre estos métodos).
 *  - Vista delgada (dompdf solo soporta un subconjunto de CSS; cuanto menos
 *    `@php` en la plantilla, mejor).
 *  - Centraliza la regla "qué dato SÍ y qué dato NO" — la hoja es operativa:
 *    NUNCA incluye datos de cobro sensibles (tarjeta, `gateway_order`, `auth_code`,
 *    `Ds_Response`); solo el estado de pago a alto nivel (Pagado/Pendiente/…).
 *
 * El presenter NO decide permisos ni idioma: eso lo hace el controlador
 * (`ReservationSlipController`, que fuerza `App::setLocale('es')` antes de
 * renderizar — la hoja siempre va en español, decisión de diseño 2026-06-05).
 */
final class ReservationSlip
{
    /** Fallback gris para zonas sin color (coincide con `.zone-card` e items-list). */
    public const ZONE_COLOR_FALLBACK = '#9CA3AF';

    private function __construct(
        public readonly Order $order,
        public readonly OrderItem $item,
    ) {}

    /**
     * Construye la hoja desde el Order (ya resuelto y validado por el controlador)
     * y uno de sus items PRINCIPALES. Eager-load idempotente para evitar N+1 y
     * nulls al recorrer relaciones en la vista.
     */
    public static function make(Order $order, OrderItem $item): self
    {
        $item->loadMissing([
            'ticketType.zone',
            'slot',
            'children.ticketType',
            // `*.orderItem.ticketType` evita el N+1 de `OrderAdjustment::breakdownLabel()`
            // (lee `orderItem->ticketType->tr('name')`) al desglosar "a cobrar en puerta".
            'adjustments.orderItem.ticketType',
            'children.adjustments.orderItem.ticketType',
        ]);

        // `payments.refunds` para el desglose "Totales del producto" (línea Devuelto):
        // `Order::itemRefundedCents()` recorre los reembolsos confirmados.
        // `adjustments` para el bloque «Fiesta mixta» ({@see mixedParty()}): las líneas escritas
        // del suplemento se reconocen por la marca del ajuste (`MixedPartySurcharge::written()`).
        $order->loadMissing(['user', 'payments.refunds', 'adjustments']);

        return new self($order, $item);
    }

    // ─── Identificación del pedido ────────────────────────────────────────────

    public function code(): string
    {
        return (string) $this->order->code;
    }

    /** Estado de pago efectivo (resuelve caducidad): paid/pending/cancelled/refunded/expired. */
    public function orderStatus(): string
    {
        return $this->order->displayStatus();
    }

    public function isPaid(): bool
    {
        return $this->orderStatus() === Order::STATUS_PAID;
    }

    public function createdAt(): ?Carbon
    {
        return $this->order->created_at;
    }

    // ─── Producto / zona ──────────────────────────────────────────────────────

    public function productName(): string
    {
        // Con la etiqueta MIXTA si lo es (`specs/cumple-mixto.md` §13): la hoja se lleva a la
        // fiesta y es donde el operador tiene delante lo que va a cobrar.
        return $this->item->displayProductName();
    }

    public function isPack(): bool
    {
        return $this->item->ticketType?->isPack() ?? false;
    }

    /** Color de la zona (= color de pulsera). Fallback gris si la zona no tiene color. */
    public function zoneColor(): string
    {
        return $this->item->ticketType?->zone?->color ?? self::ZONE_COLOR_FALLBACK;
    }

    public function isCancelled(): bool
    {
        return $this->item->isCancelled();
    }

    // ─── Franja / sesión ──────────────────────────────────────────────────────

    public function hasSlot(): bool
    {
        return $this->item->slot !== null;
    }

    public function slotDate(): ?Carbon
    {
        $slot = $this->item->slot;

        return $slot && $slot->date ? Carbon::parse($slot->date) : null;
    }

    /** Hora de inicio en formato HH:MM (la BD guarda 'HH:MM:SS'). */
    public function slotStart(): ?string
    {
        $slot = $this->item->slot;

        return $slot && $slot->start_time ? substr((string) $slot->start_time, 0, 5) : null;
    }

    public function slotEnd(): ?string
    {
        $slot = $this->item->slot;

        return $slot && $slot->end_time ? substr((string) $slot->end_time, 0, 5) : null;
    }

    /**
     * Ventana horaria a mostrar: hora de entrada → entrada + duración del producto
     * (la rejilla de aforo es de 60 min, así que `slotEnd()` no refleja la duración
     * real). Fuente única: `OrderItem::displayTimeWindow()`.
     */
    public function slotWindow(): ?string
    {
        return $this->item->displayTimeWindow();
    }

    /** Duración humanizada del producto (p. ej. "1h 30min"), o null si ilimitada. */
    public function durationLabel(): ?string
    {
        return Duration::formatHumane($this->item->ticketType?->duration_min);
    }

    public function quantity(): int
    {
        return (int) $this->item->quantity;
    }

    /** Etiqueta de cantidad: "N invitados" (packs) o "N entradas" (entradas). */
    public function quantityLabel(): string
    {
        if ($this->isPack()) {
            return __('tickets.guests_count', ['count' => $this->quantity()]);
        }

        return trans_choice('admin.orders.slip.entries_count', $this->quantity(), ['count' => $this->quantity()]);
    }

    /** Etiqueta i18n de la cabecera de la cantidad ("Invitados" vs "Entradas"). */
    public function quantityHeading(): string
    {
        return $this->isPack()
            ? __('admin.orders.slip.guests_heading')
            : __('admin.orders.slip.entries_heading');
    }

    // ─── Datos del evento (packs) ─────────────────────────────────────────────

    /**
     * Filas "etiqueta → valor" de los datos del evento, ordenadas por el ESQUEMA
     * del pack (mismas reglas que la sub-card del panel, items-list.blade.php):
     * primero los campos del esquema con valor, luego claves huérfanas al final.
     *
     * @return list<array{label:string,value:string}>
     */
    public function eventDataRows(): array
    {
        $data = $this->item->event_data;
        if (! is_array($data) || $data === []) {
            return [];
        }

        $ticketType = $this->item->ticketType;
        $eventFields = $ticketType?->eventFields() ?? [];
        $schemaKeys = array_column($eventFields, 'key');

        $rows = [];
        foreach ($eventFields as $field) {
            $value = $data[$field['key']] ?? null;
            if ($value !== null && $value !== '') {
                $rows[] = [
                    'label' => $ticketType->eventFieldLabel($field),
                    'value' => $this->stringifyValue($value),
                ];
            }
        }

        foreach ($data as $key => $value) {
            if (! in_array($key, $schemaKeys, true) && $value !== null && $value !== '') {
                $rows[] = [
                    'label' => ucfirst(str_replace('_', ' ', (string) $key)),
                    'value' => $this->stringifyValue($value),
                ];
            }
        }

        return $rows;
    }

    // ─── Datos por invitado (post-form #217) ──────────────────────────────────

    /** ¿Esta reserva (pack) pide los datos por-niño? (define el bloque de la hoja). */
    public function hasGuestForm(): bool
    {
        return $this->item->guestFormStatus() !== null;
    }

    /** ¿El cliente ya rellenó (completo) los datos de todos los niños? */
    public function guestFormComplete(): bool
    {
        return $this->item->isGuestFormComplete();
    }

    /**
     * Columnas de la tabla por-niño (etiquetas del esquema `guest_fields` del pack, en ES — el
     * controlador fuerza el locale). Vacío si la reserva no pide datos por-niño.
     *
     * @return list<array{key:string,label:string}>
     */
    public function guestColumns(): array
    {
        $type = $this->item->ticketType;
        if ($type === null || ! $type->isPack()) {
            return [];
        }

        return array_map(
            fn (array $field): array => ['key' => $field['key'], 'label' => $type->guestFieldLabel($field)],
            $type->guestFields(),
        );
    }

    /**
     * Una fila por niño (= cantidad de invitados), con los valores en el orden de `guestColumns()`.
     * Si un niño aún no tiene datos (form incompleto), su fila va EN BLANCO para rellenarla a mano
     * en el parque — coherente con cómo el parque trabaja en papel. Siempre exactamente `quantity`
     * filas (ni más ni menos), para que la hoja sirva tanto si el form está completo como si no.
     *
     * @return list<list<string>>
     */
    public function guestRows(): array
    {
        $columns = $this->guestColumns();
        if ($columns === []) {
            return [];
        }

        $data = $this->item->guestData();
        $rows = [];
        for ($i = 0; $i < $this->quantity(); $i++) {
            $row = [];
            foreach ($columns as $col) {
                $row[] = $this->stringifyValue($data[$i][$col['key']] ?? '');
            }
            $rows[] = $row;
        }

        return $rows;
    }

    // ─── Fiesta mixta (T3 · E, `specs/cumple-mixto.md` §23.2) ─────────────────

    /**
     * El bloque «Fiesta mixta» de la hoja: lo ESCRITO del suplemento —las líneas por pack de
     * destino y el total, que es lo que se cobra en el parque (`PAY-19`)—, el aviso del caso
     * barato (§14: informativo, NO es dinero) y cuántos invitados tienen una edad sin producto
     * en las condiciones selladas de la reserva.
     *
     * `null` = el bloque no existe: la fiesta no tiene cargo escrito, no saldría más barata y
     * ninguna edad se queda sin producto. Una fiesta mixta con los dos packs al mismo precio cae
     * aquí a propósito — la etiqueta MIXTA ya viaja en el nombre del producto (§13) y una caja de
     * 0,00 € no le dice nada al operador.
     *
     * ⚠️ Se enseña lo ESCRITO, nunca el veredicto derivado: es lo que se le comunicó al cliente y
     * lo que se cobra. El veredicto solo aporta lo que el dinero no dice — el caso barato y las
     * edades sin producto — y por eso son los dos únicos datos que salen de él.
     *
     * @return array{lines: list<array{name:string, count:int, unit:int}>, totalCents:int, savingsCents:int, withoutProduct:int}|null
     */
    public function mixedParty(): ?array
    {
        $written = app(MixedPartySurcharge::class)->written($this->item);
        $mix = app(GuestAgeMixReader::class)->for($this->item);
        $withoutProduct = count($this->item->guestAgesWithoutProduct());

        // El aviso del caso barato sigue el criterio de la ficha del pedido (items-list): solo
        // cuando NO hay cargo escrito — con cargo, el dato que manda es el importe aplicado.
        $savings = $written['cents'] === 0 && $mix->hasSavings() ? (int) $mix->savingsCents : 0;

        if ($written['cents'] === 0 && $savings === 0 && $withoutProduct === 0) {
            return null;
        }

        return [
            'lines' => $written['lines'],
            'totalCents' => $written['cents'],
            'savingsCents' => $savings,
            'withoutProduct' => $withoutProduct,
        ];
    }

    // ─── Complementos ─────────────────────────────────────────────────────────

    /**
     * Complementos (children) de la reserva, para el inventario operativo.
     *
     * Oculta los complementos "fantasma" net-cero (cancelados, nunca cobrados ni
     * reembolsados) — misma autoridad única que `addonBreakdown()` y las 3
     * superficies de desglose (#F3): de lo contrario el inventario del PDF
     * pintaría una línea tachada de un complemento que el resto de vistas no
     * muestra.
     *
     * @return list<array{name:string,quantity:int,badge:?string,cancelled:bool}>
     */
    public function addons(): array
    {
        return $this->item->children
            ->reject(fn (OrderItem $child) => $this->isVoidedLeftover($child))
            ->map(fn (OrderItem $child) => [
                'name' => $child->ticketType?->tr('name') ?? '—',
                'quantity' => (int) $child->quantity,
                'badge' => $child->addonBadgeKey(),
                'cancelled' => $child->isCancelled(),
            ])
            ->values()
            ->all();
    }

    // ─── Datos de la reserva (cliente + datos del evento) ─────────────────────

    public function customerName(): ?string
    {
        return $this->order->user?->name;
    }

    public function customerPhone(): ?string
    {
        return $this->order->user?->phone;
    }

    /**
     * Filas "Datos de la reserva" en el orden pedido por la clienta (pulido de #183):
     *  1. Cumpleañero (1.ª fila del esquema del evento, con su etiqueta del pack).
     *  2. **Padre/madre o tutor legal** (= nombre del cliente) — o **"Nombre del
     *     cliente"** si NO es un pack (una entrada no tiene homenajeado/tutor).
     *  3. **Teléfono** del cliente.
     *  4. El resto de datos del evento (los datos "custom" que pide el pack).
     *
     * Sin datos del evento (p. ej. una entrada): solo nombre del cliente + teléfono.
     * Las etiquetas de los datos del evento son **data-driven** (las define el pack);
     * solo las del cliente/teléfono son fijas.
     *
     * @return list<array{label:string,value:string}>
     */
    public function reservationDataRows(): array
    {
        $eventRows = $this->eventDataRows();

        $clientRows = [
            [
                'label' => $this->isPack()
                    ? __('admin.orders.slip.guardian_label')
                    : __('admin.orders.slip.client_label'),
                'value' => $this->customerName() ?? '—',
            ],
            [
                'label' => __('admin.orders.customer_phone'),
                'value' => $this->customerPhone() ?? '—',
            ],
        ];

        if ($eventRows === []) {
            return $clientRows;
        }

        // Cumpleañero primero, luego el contacto, luego el resto de datos del evento.
        $celebrant = array_shift($eventRows);

        return array_merge([$celebrant], $clientRows, $eventRows);
    }

    // ─── Totales del producto (desglose como en la sub-card del pedido) ────────

    /**
     * Desglose del producto principal: "{cantidad} × {precio unitario}". El
     * `quantityLabel` ya da "8 invitados" (packs) / "4 entradas" (entradas).
     *
     * @return array{quantityLabel:string,unitPriceCents:int,totalCents:int,cancelled:bool}
     */
    public function principalBreakdown(): array
    {
        return [
            'quantityLabel' => $this->quantityLabel(),
            'unitPriceCents' => (int) $this->item->unit_price,
            'totalCents' => $this->principalTotalCents(),
            'cancelled' => $this->item->isCancelled(),
        ];
    }

    /**
     * Desglose de cada complemento: nombre + "{cantidad} × {precio unitario}".
     *
     * @return list<array{name:string,quantity:int,freeQuantity:int,unitPriceCents:int,totalCents:int,badge:?string,partialFreeNote:?string,cancelled:bool}>
     */
    public function addonBreakdown(): array
    {
        return $this->item->children
            ->reject(fn (OrderItem $child) => $this->isVoidedLeftover($child))
            ->map(fn (OrderItem $child) => [
                'name' => $child->ticketType?->tr('name') ?? '—',
                'quantity' => (int) $child->quantity,
                'freeQuantity' => (int) $child->free_quantity,
                'unitPriceCents' => (int) $child->unit_price,
                'totalCents' => $child->chargedSubtotalCents(),
                'badge' => $child->addonBadgeKey(),
                'partialFreeNote' => $child->partialFreeNote(),
                'cancelled' => $child->isCancelled(),
            ])
            ->values()
            ->all();
    }

    public function principalTotalCents(): int
    {
        return $this->item->chargedSubtotalCents();
    }

    public function addonsTotalCents(): int
    {
        // Excluye complementos CANCELADOS: ya no forman parte del producto.
        return (int) $this->item->children
            ->reject(fn (OrderItem $child) => $child->isCancelled())
            ->sum(fn (OrderItem $child) => $child->chargedSubtotalCents());
    }

    public function grandTotalCents(): int
    {
        return $this->principalTotalCents() + $this->addonsTotalCents();
    }

    /**
     * Desglose financiero de la reserva desde la fuente ÚNICA compartida (#196):
     * pagado online / a cobrar en puerta / cobrado en puerta / devuelto / pendiente.
     * Mismas cifras que la sub-card del pedido y el modal del calendario.
     */
    public function financials(): ReservationFinancials
    {
        return ReservationFinancials::make($this->order, $this->item);
    }

    /** Total devuelto sobre esta reserva (principal + complementos). */
    public function refundedCents(): int
    {
        $sum = $this->order->itemRefundedCents($this->item);
        foreach ($this->item->children as $child) {
            $sum += $this->order->itemRefundedCents($child);
        }

        return $sum;
    }

    /**
     * Importe cancelado aún NO reembolsado (principal o complementos cancelados):
     * recordatorio operativo de que queda cerrar el reembolso. Misma fórmula que
     * la sub-card del pedido (items-list.blade.php).
     */
    public function pendingRefundCents(): int
    {
        // Fuente ÚNICA (robustez #198): incluye tanto los CANCELADOS (todo su cobrado
        // online no devuelto) como las REDUCCIONES de cantidad por debajo de lo pagado
        // online. Igual que la card del panel y "Mis pedidos".
        return $this->financials()->pendienteReembolso;
    }

    // ─── A cobrar en puerta (por reserva) ─────────────────────────────────────

    /**
     * Importe pendiente de cobrar en puerta por ESTA reserva (principal + sus
     * complementos): suma de ajustes `extra_due` cuyos items NO están cerrados
     * (ni finalizados ni cancelados). Misma semántica que
     * {@see OrderFinancialSummary::pendingAtGate()} pero acotada a la reserva.
     */
    public function pendingAtGateCents(): int
    {
        return $this->pendingAdjustments()
            ->sum(fn (OrderAdjustment $adj) => (int) $adj->amount_cents);
    }

    public function hasPendingAtGate(): bool
    {
        return $this->pendingAtGateCents() > 0;
    }

    /**
     * Etiquetas compactas NETAS del cargo de puerta de ESTA reserva (principal +
     * sus complementos), p. ej. "+2 Calcetines". Delega en
     * {@see Order::pendingAtGateLines()} acotado a los items de la reserva: los
     * créditos de una bajada netean los cargos de una subida → sin renglones
     * fantasma al subir y bajar la misma cantidad (bug JJ-WIMWJW). El total que
     * pinta el PDF ({@see pendingAtGateCents}) cuadra con la Σ de estas líneas.
     *
     * @return list<string>
     */
    public function pendingAtGateBreakdown(): array
    {
        $itemIds = collect([$this->item->id])
            ->merge($this->item->children->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->all();

        $labels = array_map(
            fn (array $line) => $line['label'],
            $this->order->pendingAtGateLines($itemIds),
        );

        // #225: el resto de la señal pendiente NO sale en pendingAtGateLines (que solo netea
        // extra_due de ediciones); se añade como una entrada propia para que la lista de
        // etiquetas cuadre con el total `pendingAtGateCents()`.
        $depositRemainder = $this->pendingAdjustments()
            ->where('type', OrderAdjustment::TYPE_DEPOSIT_REMAINDER)
            ->sum(fn (OrderAdjustment $adj) => (int) $adj->amount_cents);
        if ($depositRemainder > 0) {
            // #225 (feedback clienta): la línea «Resto de la señal» nombra su producto («de X»).
            // La hoja es de una reserva → el producto es el principal.
            $labels[] = __('admin.orders.slip.deposit_remainder_line')
                .' '.__('admin.orders.deposit_for_product', ['product' => $this->productName()]);
        }

        return $labels;
    }

    /** @return Collection<int,OrderAdjustment> */
    private function pendingAdjustments(): Collection
    {
        // Los complementos HEREDAN el estado "finalizado" del principal: lo
        // calculamos UNA vez aquí en lugar de llamar `isFinishedInPractice()`
        // sobre cada child (que haría un lazy-load de su `parent` + `slot`).
        $principalFinished = $this->item->isFinishedInPractice();

        $reservationItems = collect([$this->item])->merge($this->item->children);

        return $reservationItems
            // Item cerrado (finalizado o cancelado) → su extra_due se da por
            // resuelto (cobrado en puerta o anulado). Coherente con la regla
            // del resumen financiero del pedido.
            ->reject(function (OrderItem $i) use ($principalFinished) {
                $finished = $i->parent_item_id === null
                    ? $i->isFinishedInPractice()
                    : $principalFinished;

                return $finished || $i->isCancelled();
            })
            ->flatMap(fn (OrderItem $i) => $i->adjustments
                // #225: ambos buckets de puerta — extra_due de ediciones + resto de la señal.
                ->whereIn('type', [OrderAdjustment::TYPE_EXTRA_DUE, OrderAdjustment::TYPE_DEPOSIT_REMAINDER]))
            ->values();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /** Importe en céntimos → "12,00 €" (formato ES, símbolo euro). Fuente única: {@see Money}. */
    public static function money(int $cents): string
    {
        return Money::format($cents);
    }

    /**
     * ¿Complemento "fantasma" a ocultar de la hoja? Delega en la autoridad única
     * {@see Order::isVoidedLeftoverItem()} (cancelado, nunca cobrado ni reembolsado).
     */
    private function isVoidedLeftover(OrderItem $i): bool
    {
        return $this->order->isVoidedLeftoverItem($i);
    }

    private function stringifyValue(mixed $value): string
    {
        return is_scalar($value)
            ? (string) $value
            : (string) json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
