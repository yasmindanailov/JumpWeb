<?php

namespace App\Filament\Resources\ParkRules;

use App\Filament\Resources\ParkRules\Pages\CreateParkRule;
use App\Filament\Resources\ParkRules\Pages\EditParkRule;
use App\Filament\Resources\ParkRules\Pages\ListParkRules;
use App\Filament\Resources\ParkRules\Schemas\ParkRuleForm;
use App\Filament\Resources\ParkRules\Tables\ParkRuleTable;
use App\Models\ParkRule;
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
    protected static ?string $model = ParkRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $slug = 'park-rules';

    // Detrás de FAQ (92), antes de Usuarios (100).
    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_groups.contenido');
    }

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
        return ($record instanceof ParkRule)
            && (auth()->user()?->hasPermission('content.manage') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasPermission('content.manage') ?? false;
    }
}
