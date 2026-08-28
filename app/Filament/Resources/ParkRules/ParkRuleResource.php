<?php

namespace App\Filament\Resources\ParkRules;

use App\Domain\Content\Models\VenueRule;
use App\Filament\Concerns\ProvidesGlobalSearch;
use App\Filament\Resources\ParkRules\Pages\CreateParkRule;
use App\Filament\Resources\ParkRules\Pages\EditParkRule;
use App\Filament\Resources\ParkRules\Pages\ListParkRules;
use App\Filament\Resources\ParkRules\Schemas\ParkRuleForm;
use App\Filament\Resources\ParkRules\Tables\ParkRuleTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Fase 7.9 (iter. 1) — Normas del parque (`park_rules`): CRUD desde el panel (landing + página
 * `/normas`). Texto i18n (es/en/fr), orden y activar/desactivar; borrado libre (una norma no
 * tiene dependientes).
 *
 * **Acceso solo admin** (`content.manage`). Vive en el cluster «Configuración».
 */
class ParkRuleResource extends Resource
{
    use ProvidesGlobalSearch;

    protected static ?string $model = VenueRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $slug = 'park-rules';

    // Detrás de FAQ (92), antes de Usuarios (100).
    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('admin.park_rules.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.park_rules.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.park_rules.model_label_plural');
    }

    public static function form(Schema $schema): Schema
    {
        return ParkRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ParkRuleTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListParkRules::route('/'),
            'create' => CreateParkRule::route('/create'),
            'edit' => EditParkRule::route('/{record}/edit'),
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
        return ($record instanceof VenueRule)
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
    // El enunciado de la norma. El rótulo lo resuelve `ProvidesGlobalSearch`.

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
