<?php

namespace App\Filament\Resources\Offers;

use App\Domain\Content\Models\Offer;
use App\Filament\Resources\Offers\Pages\CreateOffer;
use App\Filament\Resources\Offers\Pages\EditOffer;
use App\Filament\Resources\Offers\Pages\ListOffers;
use App\Filament\Resources\Offers\Schemas\OfferForm;
use App\Filament\Resources\Offers\Tables\OfferTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Ofertas promocionales INFORMATIVAS (#270, `docs/PLAN-OFERTAS-WIDGET.md`) — entidad CMS editorial:
 * título (i18n) + imagen. Gobiernan el widget flotante «caja de regalo» de la landing (solo aparece
 * si hay ofertas activas). Sin dinero.
 *
 * **Acceso solo admin** (`content.manage`, reutilizado). Vive en el grupo «Contenido».
 */
class OfferResource extends Resource
{
    protected static ?string $model = Offer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGift;

    protected static ?string $slug = 'offers';

    // Grupo Contenido: tras FAQ (20).
    protected static ?int $navigationSort = 25;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_groups.contenido');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.offers.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.offers.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.offers.model_label_plural');
    }

    public static function form(Schema $schema): Schema
    {
        return OfferForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OfferTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOffers::route('/'),
            'create' => CreateOffer::route('/create'),
            'edit' => EditOffer::route('/{record}/edit'),
        ];
    }

    // ─── Autorización: solo admin (Gate::before) vía `content.manage` ─────────

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('content.manage') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->hasPermission('content.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermission('content.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasPermission('content.manage') ?? false;
    }

    public static function canDelete($record): bool
    {
        return ($record instanceof Offer)
            && (auth()->user()?->hasPermission('content.manage') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasPermission('content.manage') ?? false;
    }
}
