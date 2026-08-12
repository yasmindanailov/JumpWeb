<?php

namespace App\Filament\Resources\Seasons;

use App\Domain\Booking\Models\Season;
use App\Filament\Resources\Seasons\Pages\CreateSeason;
use App\Filament\Resources\Seasons\Pages\EditSeason;
use App\Filament\Resources\Seasons\Pages\ListSeasons;
use App\Filament\Resources\Seasons\Schemas\SeasonForm;
use App\Filament\Resources\Seasons\Tables\SeasonTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Fase 7.7 — Temporadas (`seasons`): tramos de fechas con un horario propio que SUSTITUYE
 * al horario semanal mientras están vigentes (p. ej. «Horario de verano»). Una temporada
 * activa abre todos los días de su rango con su ventana (#207).
 *
 * Las consume en vivo `App\Domain\Booking\Services\OperatingSchedule` (reservas + generador de franjas) y la
 * landing. Orden de resolución: fecha especial → TEMPORADA → horario semanal.
 *
 * **Acceso solo admin** (`slots.manage`, igual que el horario semanal). Borrado siempre
 * seguro: nada referencia a una temporada por FK (al borrarla, su rango vuelve al horario
 * semanal).
 */
class SeasonResource extends Resource
{
    protected static ?string $model = Season::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSun;

    protected static ?string $slug = 'seasons';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_groups.programacion');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.seasons.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.seasons.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.seasons.model_label_plural');
    }

    public static function form(Schema $schema): Schema
    {
        return SeasonForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SeasonTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSeasons::route('/'),
            'create' => CreateSeason::route('/create'),
            'edit' => EditSeason::route('/{record}/edit'),
        ];
    }

    // ─── Autorización: solo admin (Gate::before) vía `slots.manage` ─────────

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('slots.manage') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->hasPermission('slots.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermission('slots.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasPermission('slots.manage') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasPermission('slots.manage') ?? false;
    }

    public static function canDelete($record): bool
    {
        return ($record instanceof Season)
            && (auth()->user()?->hasPermission('slots.manage') ?? false);
    }
}
