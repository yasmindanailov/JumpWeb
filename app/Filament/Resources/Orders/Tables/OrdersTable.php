<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Platform\Services\DisplayTime;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
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
 * #200 (fidedigno con el bloque valor-primero del detalle): la columna **Total**
 * = **Valor final del pedido** (`OrderFinancialSummary::totalFinalNeto`, lo que el
 * cliente acaba pagando) y **Pagado** = **Pagado online que respalda productos**
 * (`Order::onlineBackingProductsCents`). Así la lista cuadra con el detalle:
 * `Total − Pagado` = lo que queda a cobrar en el parque.
 */
class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eager-load para las columnas "Total" (`OrderFinancialSummary::totalFinalNeto`
            // → items.slot + adjustments) y "Pagado" (`Order::onlineBackingProductsCents`
            // → payments.refunds + adjustments + items.slot). Sin N+1 al construir el
            // resumen por fila (#200: las columnas reflejan el bloque valor-primero).
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['payments.refunds', 'adjustments', 'items.slot']))
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

                // "Total" = **Valor final del pedido** (#200): lo que el cliente acaba
                // pagando = valor de los productos actuales (`totalFinalNeto`), igual
                // que el headline del bloque del detalle. NO el "Total con cambios"
                // bruto (que no descontaba la devolución debida → divergía del detalle).
                TextColumn::make('total')
                    ->label(__('admin.orders.col_total'))
                    ->state(fn (Order $record): string => self::euros($record->financialSummary()->totalFinalNeto()))
                    ->alignEnd(),

                // "Pagado" = lo cobrado online que RESPALDA los productos (#200):
                // `onlineBackingProductsCents` = "Pagado online" del bloque del detalle
                // (caja real − pendiente de devolución; 0 si no hay cobro). Fidedigno
                // con el detalle: Total − Pagado = lo que queda a cobrar en el parque.
                TextColumn::make('amount_collected')
                    ->label(__('admin.orders.col_collected'))
                    ->state(fn (Order $record): string => self::euros($record->onlineBackingProductsCents()))
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
