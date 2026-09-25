<?php

namespace App\Filament\Resources\Promotions;

use App\Domain\Booking\Models\Promotion;
use App\Filament\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Resources\Promotions\Pages\EditPromotion;
use App\Filament\Resources\Promotions\Pages\ListPromotions;
use App\Filament\Resources\Promotions\Schemas\PromotionForm;
use App\Filament\Resources\Promotions\Tables\PromotionTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * **PROMOCIONES** (`docs/specs/promociones.md` §4.2, `DECISIONES #684` y `#770`): las ofertas con fecha y los regalos,
 * todas en UNA página —«el operador las gestiona todas en un sitio»—. Cada una es un texto en tres idiomas, su clase,
 * su objetivo (un producto, una zona o toda la instalación) y sus fechas; la web la pone sola donde toca.
 *
 * **Acceso**: `catalog.manage`, el mismo que el catálogo al que se vinculan. Vive en «Ajustes → Venta», junto al
 * catálogo y las zonas (`AdminSettingsHub`).
 */
class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $slug = 'promotions';

    public static function getNavigationLabel(): string
    {
        return __('admin.promotions.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.promotions.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.promotions.model_label_plural');
    }

    public static function form(Schema $schema): Schema
    {
        return PromotionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PromotionTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromotions::route('/'),
            'create' => CreatePromotion::route('/create'),
            'edit' => EditPromotion::route('/{record}/edit'),
        ];
    }

    // ─── Autorización: `catalog.manage`, como el catálogo ─────────────────────

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('catalog.manage') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->hasPermission('catalog.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermission('catalog.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasPermission('catalog.manage') ?? false;
    }

    public static function canDelete($record): bool
    {
        return ($record instanceof Promotion)
            && (auth()->user()?->hasPermission('catalog.manage') ?? false);
    }

    /**
     * Fuera del menú lateral (#223): se entra por «Ajustes → Venta». Ocultar NO es autorizar: quien decide el acceso
     * sigue siendo `canViewAny()`.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
