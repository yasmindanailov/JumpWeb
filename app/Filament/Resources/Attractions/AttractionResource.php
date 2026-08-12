<?php

namespace App\Filament\Resources\Attractions;

use App\Domain\Content\Models\Attraction;
use App\Filament\Resources\Attractions\Pages\CreateAttraction;
use App\Filament\Resources\Attractions\Pages\EditAttraction;
use App\Filament\Resources\Attractions\Pages\ListAttractions;
use App\Filament\Resources\Attractions\Schemas\AttractionForm;
use App\Filament\Resources\Attractions\Tables\AttractionTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fase 7.9 (iter. 1) — Atracciones (`attractions`) de la landing: CRUD desde el panel. Cada
 * atracción cuelga de una zona y tiene textos i18n (nombre/descripción/edad/badge), una imagen
 * (ruta relativa a `public/` por ahora; la subida de ficheros llegará con la galería), orden y
 * activar/desactivar. Borrado libre (ninguna FK referencia una atracción).
 *
 * **Acceso solo admin** (`content.manage`). Vive en el cluster «Configuración».
 */
class AttractionResource extends Resource
{
    protected static ?string $model = Attraction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static ?string $slug = 'attractions';

    // Detrás de Zonas (90), antes de FAQ (92): la atracción cuelga de una zona.
    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_groups.contenido');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.attractions.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.attractions.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.attractions.model_label_plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['zone', 'ticketType']);
    }

    public static function form(Schema $schema): Schema
    {
        return AttractionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttractionTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttractions::route('/'),
            'create' => CreateAttraction::route('/create'),
            'edit' => EditAttraction::route('/{record}/edit'),
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
        return ($record instanceof Attraction)
            && (auth()->user()?->hasPermission('content.manage') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasPermission('content.manage') ?? false;
    }
}
