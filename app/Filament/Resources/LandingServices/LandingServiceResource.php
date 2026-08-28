<?php

namespace App\Filament\Resources\LandingServices;

use App\Domain\Content\Models\LandingService;
use App\Filament\Concerns\ProvidesGlobalSearch;
use App\Filament\Resources\LandingServices\Pages\CreateLandingService;
use App\Filament\Resources\LandingServices\Pages\EditLandingService;
use App\Filament\Resources\LandingServices\Pages\ListLandingServices;
use App\Filament\Resources\LandingServices\Schemas\LandingServiceForm;
use App\Filament\Resources\LandingServices\Tables\LandingServiceTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Servicios de la landing (`landing_services`) — entidad CMS editorial de la página /servicios y
 * del selector «Servicios» del nav (#256, modelo A). Cada servicio es una fila editorial (slug =
 * anchor estable, textos i18n, imagen, orden, toggles) que OPCIONALMENTE referencia un pack
 * comprable (`ticket_type_id`): con pack → precio + CTA «Reservar» en la landing; sin pack → CTA
 * «Pedir información». Lo comercial vive en el `TicketType` + su `Zone` (fuente única, sin drift).
 *
 * La existencia de un LandingService que referencie un pack lo SACA de la sección Cumpleaños.
 *
 * **Acceso solo admin** (`content.manage`). Vive en el grupo «Contenido».
 */
class LandingServiceResource extends Resource
{
    use ProvidesGlobalSearch;

    protected static ?string $model = LandingService::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $slug = 'landing-services';

    // Grupo Contenido: tras Atracciones (10), antes de FAQ (20).
    protected static ?int $navigationSort = 15;

    public static function getNavigationLabel(): string
    {
        return __('admin.landing_services.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.landing_services.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.landing_services.model_label_plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('ticketType');
    }

    public static function form(Schema $schema): Schema
    {
        return LandingServiceForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LandingServiceTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLandingServices::route('/'),
            'create' => CreateLandingService::route('/create'),
            'edit' => EditLandingService::route('/{record}/edit'),
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
        return ($record instanceof LandingService)
            && (auth()->user()?->hasPermission('content.manage') ?? false);
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

    // ─── Buscador del panel (#224) ───────────────────────────────────────────
    // El título del servicio. El rótulo lo resuelve `ProvidesGlobalSearch`.

    /** @return array<int, string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['title->es', 'slug'];
    }

    protected static function globalSearchTitleAttribute(): string
    {
        return 'title';
    }
}
