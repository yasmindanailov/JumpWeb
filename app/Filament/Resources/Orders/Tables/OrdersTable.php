<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Services\OrderBook;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Platform\Services\DisplayTime;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fase 7.1b — Tabla de pedidos del panel admin (decisión #127).
 *
 * Columnas mínimas pero útiles para identificar el pedido y su estado operativo
 * de un vistazo. Filtros por status efectivo y rango de fechas.
 *
 * Pulidos #179: (a) el badge "Reembolsado" se fusiona en la columna ESTADO
 * (multi-badge), eliminando la columna propia; (b) la columna "Ver" se retira —
 * la fila entera enlaza al detalle (`recordUrl`). Sin fila-resumen al pie.
 *
 * T3·2 del LIBRO (`DECISIONES #305`): la columna **Total** es el Total del libro del pedido y
 * **Pagado** lo Pagado del libro (cobrado − devuelto + liquidado en el parque), las mismas dos
 * cifras que encabezan «Totales del pedido»: `Total − Pagado` es el saldo (D-T3·6: sin columna
 * «Saldo» aparte). Antes (`#200`) eran el «valor final» y el «pagado online que respalda
 * productos» de un modelo de dos ejes.
 */
class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eager-load de lo que el LIBRO lee por fila (`OrderBook::forOrder`: las líneas con su
            // franja y su producto, los ajustes, los cobros con sus devoluciones). Sin N+1 por fila.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['payments.refunds', 'adjustments', 'items.slot', 'items.ticketType']))
            ->columns([
                TextColumn::make('code')
                    ->label(__('admin.orders.col_code'))
                    ->searchable()
                    ->sortable(),

                // Cliente: email si lo hay; si no (cliente de agenda sin email, #263), su teléfono o,
                // en último término, el nombre — así la columna nunca queda en blanco. La búsqueda
                // cubre email, nombre Y teléfono (un cliente sin email SOLO se encuentra por nombre/tel).
                TextColumn::make('user.email')
                    ->label(__('admin.orders.col_customer'))
                    ->state(fn (Order $record): string => filled($record->user?->email)
                        ? (string) $record->user->email
                        : (filled($record->user?->phone)
                            ? (string) $record->user->phone
                            : (string) ($record->user?->name ?? '—')))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'user',
                        fn (Builder $q) => $q
                            ->where('email', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"),
                    ))
                    ->limit(36),

                // Estado + (si aplica) "Reembolsado" como badges que COEXISTEN en
                // la misma columna (#179). El estado devuelve un array → Filament
                // pinta un badge por elemento; cada uno con su color. El marcador
                // `is_refunded` solo se añade para refunds TOTALES (coherente con
                // el badge del heading y con la columna anterior).
                TextColumn::make('status')
                    ->label(__('admin.orders.col_status'))
                    ->badge()
                    ->getStateUsing(fn (Order $record): array => array_values(array_filter([
                        $record->displayStatus(),
                        // El marcador "Reembolsado" solo si el badge de estado NO
                        // lo dice ya (evita el doble badge en data legacy con
                        // status=refunded, cuyo propio badge ya es "Reembolsado").
                        $record->isFullyRefunded() && $record->displayStatus() !== Order::STATUS_REFUNDED
                            ? 'is_refunded'
                            : null,
                    ])))
                    ->formatStateUsing(fn (string $state): string => $state === 'is_refunded'
                        ? __('admin.orders.refunded_badge')
                        : __('admin.orders.status.'.$state))
                    ->color(fn (string $state): string => match (true) {
                        $state === 'is_refunded' => 'warning',
                        $state === Order::STATUS_PAID => 'success',
                        in_array($state, [Order::STATUS_REFUNDED, Order::STATUS_CANCELLED, Order::STATUS_EXPIRED], true) => 'danger',
                        default => 'warning',
                    }),

                // "Total" = el Total del LIBRO del pedido: lo que valen hoy sus líneas vivas, la
                // misma cifra que encabeza «Totales del pedido» (T3·2).
                TextColumn::make('total')
                    ->label(__('admin.orders.col_total'))
                    ->state(fn (Order $record): string => self::euros(OrderBook::forOrder($record)->totalCents))
                    ->alignEnd(),

                // "Pagado" = lo Pagado del libro (cobrado − devuelto + liquidado en el parque):
                // Total − Pagado es el saldo que el detalle nombra con su clase (D-T3·6).
                TextColumn::make('amount_collected')
                    ->label(__('admin.orders.col_collected'))
                    ->state(fn (Order $record): string => self::euros(OrderBook::forOrder($record)->paidCents))
                    ->alignEnd(),

                // Sub-fase 7.2a: la columna del listado usa `created_at` (todos los
                // pedidos tienen creación; solo los `paid` tienen `paid_at`).
                TextColumn::make('created_at')
                    ->label(__('admin.orders.col_created_at'))
                    ->formatStateUsing(fn (?string $state) => $state ? DisplayTime::format($state) : '—')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            // La fila entera abre el detalle del pedido (#179: sustituye a la
            // columna "Ver").
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.orders.filter_status'))
                    ->options([
                        Order::STATUS_PENDING => __('admin.orders.status.'.Order::STATUS_PENDING),
                        Order::STATUS_PAID => __('admin.orders.status.'.Order::STATUS_PAID),
                        Order::STATUS_CANCELLED => __('admin.orders.status.'.Order::STATUS_CANCELLED),
                        Order::STATUS_REFUNDED => __('admin.orders.status.'.Order::STATUS_REFUNDED),
                        Order::STATUS_EXPIRED => __('admin.orders.status.'.Order::STATUS_EXPIRED),
                    ])
                    ->query(fn (Builder $query, array $data) => $data['value']
                        ? $query->where('status', $data['value'])
                        : $query),

                // Filtro "Con reembolso" (#146): cubre refunds totales y parciales
                // por item. Combinable con el filtro de status.
                TernaryFilter::make('has_any_refund')
                    ->label(__('admin.orders.filter_refunded'))
                    ->placeholder(__('admin.orders.filter_refunded_all'))
                    ->trueLabel(__('admin.orders.filter_refunded_yes'))
                    ->falseLabel(__('admin.orders.filter_refunded_no'))
                    ->queries(
                        true: fn (Builder $q) => $q->whereHasAnyRefund(),
                        false: fn (Builder $q) => $q->whereNull('refunded_at')
                            ->where('status', '!=', Order::STATUS_REFUNDED)
                            ->whereDoesntHave('payments.refunds', fn ($r) => $r->where('status', PaymentRefund::STATUS_SUCCEEDED)),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->toolbarActions([
                // No bulk actions en 7.1b.
            ]);
    }

    /** Formatea céntimos como "1.234,56 €". */
    private static function euros(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' €';
    }
}
