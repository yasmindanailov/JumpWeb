<?php

namespace App\Filament\Resources\SpecialDates;

use App\Domain\Booking\Models\SpecialDate;
use App\Filament\Resources\SpecialDates\Pages\CreateSpecialDate;
use App\Filament\Resources\SpecialDates\Pages\EditSpecialDate;
use App\Filament\Resources\SpecialDates\Pages\ListSpecialDates;
use App\Filament\Resources\SpecialDates\Schemas\SpecialDateForm;
use App\Filament\Resources\SpecialDates\Tables\SpecialDateTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fase 7.7 (iter. 1) — Fechas especiales (`special_dates`): excepciones del calendario.
 *
 * Una fila por fecha concreta que se sale de la norma semanal: un **cierre** (festivo,
 * mantenimiento), un **horario especial** (abre distinto ese día) y/o una **tarifa
 * especial** (festivo que cobra como finde). El backend ya las consume en vivo (no hacía
 * falta UI hasta ahora):
 *  - `App\Domain\Booking\Services\OperatingSchedule::effectiveFor()` (una excepción manda sobre `opening_hours`):
 *    si `is_closed`, el día no se vende; si no, usa su ventana `open_time`/`close_time`.
 *    Lo consultan el generador de franjas (`slots:generate`), la compra pública
 *    (`ProductAvailability::allowsStart`) y la gestión de pedidos → marcar un día cerrado
 *    **bloquea reservas también en franjas ya generadas** (no hace falta regenerar).
 *  - `App\Domain\Booking\Services\RateResolver::for()` aplica su `rate_type_id` ese día (precio).
 *
 * Empareja con la gestión de tarifas (7.8, #203): aquí se asigna a un día concreto cuál de
 * esas tarifas aplica. Por eso comparte permiso y grupo.
 *
 * Acceso **solo admin** vía **`prices.manage`** (el plan asigna a este permiso
 * `rate_types`/`prices`/`special_dates`, §4). El staff no ve el recurso ni el grupo.
 *
 * Alcance: SOLO `special_dates`. Las franjas/aforo en sí (`slot_templates`, `slots`,
 * `online_sales_open`, regenerar) son las siguientes iteraciones de 7.7.
 */
class SpecialDateResource extends Resource
{
    protected static ?string $model = SpecialDate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $slug = 'special-dates';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('admin.special_dates.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.special_dates.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.special_dates.model_label_plural');
    }

    /** Eager-load de la tarifa: la usa la tabla (columna) → evita N+1. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('rateType');
    }

    public static function form(Schema $schema): Schema
    {
        return SpecialDateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SpecialDateTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpecialDates::route('/'),
            'create' => CreateSpecialDate::route('/create'),
            'edit' => EditSpecialDate::route('/{record}/edit'),
        ];
    }

    // ─── Autorización: solo admin (Gate::before) vía `prices.manage` ─────────

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('prices.manage') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->hasPermission('prices.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasPermission('prices.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasPermission('prices.manage') ?? false;
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

    /**
     * Borrado siempre permitido a quien gestiona tarifas: nada referencia a una
     * `special_date` por FK (no hay dependientes), así que borrarla solo elimina la
     * excepción y el día vuelve a regirse por el horario semanal.
     */
    public static function canDelete($record): bool
    {
        return ($record instanceof SpecialDate)
            && (auth()->user()?->hasPermission('prices.manage') ?? false);
    }
}
