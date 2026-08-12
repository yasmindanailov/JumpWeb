<?php

namespace App\Filament\Resources\Zones;

use App\Domain\Booking\Models\Zone;
use App\Filament\Resources\Zones\Pages\CreateZone;
use App\Filament\Resources\Zones\Pages\EditZone;
use App\Filament\Resources\Zones\Pages\ListZones;
use App\Filament\Resources\Zones\Schemas\ZoneForm;
use App\Filament\Resources\Zones\Tables\ZoneTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Fase 7.9 (adelanto) — Zonas del parque (`zones`): la espina dorsal de la que cuelgan
 * catálogo, franjas, plantillas y aforo. CRUD desde el panel para poder crear nuevas zonas
 * (p. ej. un nuevo servicio tipo pack en otra zona) con su propio aforo/condiciones.
 *
 * Incluye el **cupo de packs POR ZONA** (override del ajuste global): así una zona-pack nueva
 * puede tener sus propios topes de fiestas/niños por franja. El resto de la riqueza CMS de la
 * landing (atracciones, galería) se aborda en el grueso de 7.9.
 *
 * **Acceso solo admin** (`content.manage`; activa el permiso sembrado hasta ahora sin uso).
 * Vive en el cluster «Configuración».
 */
class ZoneResource extends Resource
{
    protected static ?string $model = Zone::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static ?string $slug = 'zones';

    // Tras Plantillas de franja (80), antes de Usuarios (100).
    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_groups.catalogo');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.zones.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.zones.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.zones.model_label_plural');
    }

    public static function form(Schema $schema): Schema
    {
        return ZoneForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ZoneTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListZones::route('/'),
            'create' => CreateZone::route('/create'),
            'edit' => EditZone::route('/{record}/edit'),
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
        return ($record instanceof Zone)
            && (auth()->user()?->hasPermission('content.manage') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasPermission('content.manage') ?? false;
    }
}
