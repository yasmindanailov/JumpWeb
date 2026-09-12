<?php

namespace App\Filament\Resources\BarImages;

use App\Domain\Content\Models\BarImage;
use App\Filament\Concerns\ProvidesGlobalSearch;
use App\Filament\Resources\BarImages\Pages\CreateBarImage;
use App\Filament\Resources\BarImages\Pages\EditBarImage;
use App\Filament\Resources\BarImages\Pages\ListBarImages;
use App\Filament\Resources\BarImages\Schemas\BarImageForm;
use App\Filament\Resources\BarImages\Tables\BarImageTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * **Las imágenes del bar** (`DECISIONES #536`, carril de diseño Fase 3 · T3b).
 *
 * `[DECIDIDO owner, 2026-09-12]`: la carta de `/bar` **se publica como IMAGEN**, no tecleando los
 * platos. Éste es el sitio ÚNICO donde se suben las dos cosas que la página enseña — las caras de la
 * CARTA y la FOTO del local—, porque el owner pidió una sola pantalla para las imágenes del bar.
 *
 * **Acceso solo admin** (`content.manage`, el mismo que ofertas y el resto del CMS).
 */
class BarImageResource extends Resource
{
    use ProvidesGlobalSearch;

    protected static ?string $model = BarImage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $slug = 'bar-images';

    // Grupo Contenido, junto a las ofertas (25).
    protected static ?int $navigationSort = 26;

    public static function getNavigationLabel(): string
    {
        return __('admin.bar_images.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.bar_images.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.bar_images.model_label_plural');
    }

    public static function form(Schema $schema): Schema
    {
        return BarImageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BarImageTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBarImages::route('/'),
            'create' => CreateBarImage::route('/create'),
            'edit' => EditBarImage::route('/{record}/edit'),
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
        return ($record instanceof BarImage)
            && (auth()->user()?->hasPermission('content.manage') ?? false);
    }

    /**
     * `#223` — fuera del menú lateral: es pantalla de puesta en marcha, no del día a día, y se entra
     * por «Ajustes» (`AdminSettingsHub`). ⚠️ Ocultar NO es autorizar: el acceso lo siguen decidiendo
     * `canAccess()`/`canViewAny()`. ⚠️ Y si esto devuelve `false`, la pantalla **tiene que estar en
     * `AdminSettingsHub::areas()`** o queda alcanzable solo tecleando la URL — lo impone
     * `AdminNavigationTest`.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    // ─── Buscador del panel (`#224`) ─────────────────────────────────────────

    /** @return array<int, string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['alt->es'];
    }

    protected static function globalSearchTitleAttribute(): string
    {
        return 'alt';
    }
}
