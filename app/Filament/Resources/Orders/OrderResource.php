<?php

namespace App\Filament\Resources\Orders;

use App\Domain\Booking\Models\Order;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Schemas\OrderInfolist;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Fase 7.1b — Recurso Filament para gestión de pedidos en el panel admin
 * (decisión #127). En esta sub-fase solo lectura + acción "Marcar preparado"
 * por item desde la vista de detalle. Cancelar/reembolsar entran en 7.2
 * ampliando esta misma Resource.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 30;   // #223 · menú plano: 3.ª de cinco

    public static function getNavigationLabel(): string
    {
        return __('admin.orders.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.orders.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.orders.model_label_plural');
    }

    public static function infolist(Schema $schema): Schema
    {
        return OrderInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }

    /**
     * Route key del panel = `orders.code` (`JJ-XXXX`), implementado vía
     * `Order::getRouteKeyName()` global (sub-fase 7.2a refinada, decisión #130).
     *
     * El override aquí (Resource-level) NO es suficiente: Filament 5 usa
     * `getRecordRouteKeyName()` solo para la RESOLUCIÓN del binding, pero la
     * generación de URLs cae en `Laravel\route()` → `$model->getRouteKey()`. Por eso
     * lo movimos al modelo. Documentado aquí para que un futuro lector entienda dónde
     * está la fuente de verdad.
     */

    // ─── Autorización: alineada con nuestro sistema de permisos ──────────

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('orders.view') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->hasPermission('orders.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;  // creación manual = sub-fase 7.3
    }

    public static function canEdit($record): bool
    {
        return false;  // edición de campos NO en 7.1b
    }

    public static function canDelete($record): bool
    {
        return false;  // borrado NO contemplado (anonimización RGPD vive en User)
    }
}
