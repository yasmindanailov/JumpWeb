<?php

namespace App\Filament\Resources\Catalog;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use App\Filament\Concerns\ProvidesGlobalSearch;
use App\Filament\Resources\Catalog\Pages\CreateCatalog;
use App\Filament\Resources\Catalog\Pages\EditCatalog;
use App\Filament\Resources\Catalog\Pages\ListCatalog;
use App\Filament\Resources\Catalog\RelationManagers\AddonsRelationManager;
use App\Filament\Resources\Catalog\Schemas\CatalogForm;
use App\Filament\Resources\Catalog\Tables\CatalogTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fase 7.6 — Catálogo de productos (listado + edición + alta).
 *
 * El catálogo es la tabla unificada `ticket_types` discriminada por `type`
 * (entrada / pack / complemento). Cumple el principio data-driven del proyecto:
 * lo que hoy se siembra en `LandingContentSeeder` se vuelve editable desde aquí.
 *
 * Alcance (decisiones de la clienta, 2026-06-05):
 *  - **Listado** completo (los 3 tipos) con filtros y fila clicable.
 *  - **Edición** de un producto existente (textos i18n, clasificación, operativa,
 *    bloque de pack con editor de `event_fields`).
 *  - **Alta** de un producto nuevo (iter. 2, #192): el `type` se elige al crear y
 *    gobierna qué campos se piden; tras crear se va a la ficha de edición para
 *    enganchar complementos. La gestión del pivote de complementos ya existe (#187).
 *  - **Precio editable** (#189): un campo € por tarifa activa (la gestión de las
 *    tarifas en sí —`rate_types`— sigue pendiente en 7.8).
 *  - **Sin hard-delete de productos vendidos** (la FK `order_items.ticket_type_id`
 *    es `cascadeOnDelete` → borrar un producto vendido borraría sus pedidos y
 *    rompería el aforo). Solo se permite borrado físico si NUNCA se ha vendido;
 *    para el resto se "desactiva" (`is_active` / `is_sellable`). Ver `EditCatalog`.
 *
 * Acceso **solo admin** (`catalog.manage`, ya sembrado; no está en los permisos por
 * defecto del staff): el staff no ve este recurso ni el grupo "Administración".
 */
class CatalogResource extends Resource
{
    use ProvidesGlobalSearch;

    protected static ?string $model = TicketType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    // URL limpia `/admin/catalog` (sin el slug fijo, Filament generaría `catalog/catalogs`
    // al no coincidir el sub-namespace `Catalog` con el plural `catalogs`).
    protected static ?string $slug = 'catalog';

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('admin.catalog.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.catalog.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.catalog.model_label_plural');
    }

    /**
     * Eager-load de `zone` y `prices.rateType`: los usa la tabla (zona + precio de
     * referencia) y el formulario (bloque de precio editable, #189) → evita N+1.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['zone', 'prices.rateType']);
    }

    public static function form(Schema $schema): Schema
    {
        return CatalogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CatalogTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // Pivote `product_addons`: qué complementos aplican a este producto (solo entry/pack).
            AddonsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCatalog::route('/'),
            'create' => CreateCatalog::route('/create'),
            'edit' => EditCatalog::route('/{record}/edit'),
        ];
    }

    /**
     * ¿El producto tiene ventas (líneas de pedido que lo referencian)? Es la condición
     * que decide si se puede borrar físicamente. Punto único reutilizado por la tabla,
     * `canDelete()` y la acción de borrado de `EditCatalog` (defensa en profundidad).
     */
    public static function hasSales(TicketType $record): bool
    {
        return OrderItem::where('ticket_type_id', $record->getKey())->exists();
    }

    // ─── Autorización: solo admin (Gate::before) vía `catalog.manage` ─────────

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('catalog.manage') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->hasPermission('catalog.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasPermission('catalog.manage') ?? false;
    }

    /**
     * #223 — fuera del menú lateral: esta pantalla es de puesta en marcha, no del día a
     * día, y se entra por «Ajustes» (`AdminSettingsHub`, menú del avatar). Ocultar NO es
     * autorizar: quien decide el acceso sigue siendo `canAccess()`/`canViewAny()`.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        // 7.6 iter. 2: alta de producto nuevo (desbloqueada por #189, precios editables).
        // Gateada por el mismo permiso de admin que el resto del catálogo; Filament la aplica
        // a la página `/create` y a la `CreateAction` del listado.
        return auth()->user()?->hasPermission('catalog.manage') ?? false;
    }

    /**
     * Borrado físico permitido solo a quien gestiona el catálogo Y solo si el producto
     * NUNCA se ha vendido (si tiene ventas se desactiva, no se borra). El handler de
     * `EditCatalog::deleteProductAction()` re-verifica esto con datos frescos.
     */
    public static function canDelete($record): bool
    {
        return ($record instanceof TicketType)
            && (auth()->user()?->hasPermission('catalog.manage') ?? false)
            && ! self::hasSales($record);
    }

    // ─── Buscador del panel (#224) ───────────────────────────────────────────
    // Entradas, packs y complementos. El rótulo lo resuelve `ProvidesGlobalSearch`.

    /** @return array<int, string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name->es'];
    }

    protected static function globalSearchTitleAttribute(): string
    {
        return 'name';
    }
}
