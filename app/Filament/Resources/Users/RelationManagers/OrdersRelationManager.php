<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Domain\Platform\Services\DisplayTime;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Fase 7.5 (#182) — TODOS los pedidos del cliente en su ficha, con paginación/orden
 * nativos de Filament (sustituye al resumen "8 recientes" del infolist).
 *
 * Solo lectura: la gestión del pedido vive en `OrderResource` (fila → detalle del
 * pedido si el operador tiene `orders.view`).
 */
class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.users.section_orders');
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            // Eager-load para "Total con cambios" (adjustments + items, para anular el
            // extra_due de items cancelados) y "Pagado" (payments.refunds).
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['payments.refunds', 'adjustments', 'items']))
            ->columns([
                TextColumn::make('code')
                    ->label(__('admin.orders.col_code'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('admin.orders.col_status'))
                    ->badge()
                    ->state(fn (Order $record): string => $record->displayStatus())
                    ->formatStateUsing(fn (string $state): string => __('admin.orders.status.'.$state))
                    ->color(fn (string $state): string => match (true) {
                        $state === Order::STATUS_PAID => 'success',
                        in_array($state, [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED, Order::STATUS_EXPIRED], true) => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('total')
                    ->label(__('admin.orders.col_total'))
                    ->state(fn (Order $record): string => self::euros($record->totalWithChangesCents()))
                    ->alignEnd(),

                TextColumn::make('amount_collected')
                    ->label(__('admin.orders.col_collected'))
                    ->state(fn (Order $record): string => self::euros($record->amountCollectedCents()))
                    ->alignEnd(),

                TextColumn::make('created_at')
                    ->label(__('admin.orders.col_created_at'))
                    ->formatStateUsing(fn (?string $state) => $state ? DisplayTime::format($state) : '—')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Order $record): ?string => (auth()->user()?->hasPermission('orders.view') ?? false)
                ? OrderResource::getUrl('view', ['record' => $record])
                : null)
            ->paginated([10, 25, 50])
            ->emptyStateHeading(__('admin.users.orders_summary.empty'));
    }

    /** Formatea céntimos como "1.234,56 €". */
    private static function euros(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' €';
    }
}
