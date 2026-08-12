<?php

namespace App\Filament\Resources\RateTypes;

use App\Domain\Booking\Models\RateType;
use App\Filament\Resources\RateTypes\Pages\CreateRateType;
use App\Filament\Resources\RateTypes\Pages\EditRateType;
use App\Filament\Resources\RateTypes\Pages\ListRateTypes;
use App\Filament\Resources\RateTypes\Schemas\RateTypeForm;
use App\Filament\Resources\RateTypes\Tables\RateTypeTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fase 7.8 — Gestión de tarifas (`rate_types`).
 *
 * Completa la sub-fase 7.8: #189 hizo editable el PRECIO por tarifa en la ficha del
 * producto; aquí se gestionan las TARIFAS en sí (la dimensión "día" de la matriz de
 * precios): su etiqueta, los días de la semana que las activan, su prioridad y si están
 * activas. Qué tarifa aplica a una fecha lo resuelve `App\Domain\Booking\Services\RateResolver`
 * (festivo/víspera vía `special_dates` → día de la semana de mayor prioridad → `normal`).
 *
 * Alcance de esta entrega:
 *  - **CRUD** de `rate_types` (listar / crear / editar / borrar con guardas).
 *  - Las **fechas especiales** (`special_dates`) NO entran aquí: pertenecen a 7.7
 *    (aforo y calendario), donde se marca el calendario día a día.
 *
 * Invariantes que el recurso protege (la tarifa es la espina dorsal del precio):
 *  - La tarifa base **`normal`** es load-bearing (RateResolver hace `firstOrFail` por su
 *    `key` y la web lee su precio para el "desde X €"): su `key` es **inmutable** y su
 *    **borrado está prohibido**.
 *  - **Borrado seguro**: bloqueado si la tarifa tiene precios (`prices` es
 *    `cascadeOnDelete` → los borraría) o si la referencian fechas especiales. Para
 *    retirar una tarifa en uso se **desactiva** (`is_active = false`).
 *  - Cualquier cambio invalida la **caché del CTA** "desde X €" de la landing.
 *
 * Acceso **solo admin** (`prices.manage`, ya sembrado y hasta ahora sin uso): el staff
 * no ve este recurso ni el grupo "Administración".
 */
class RateTypeResource extends Resource
{
    protected static ?string $model = RateType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyEuro;

    protected static ?string $slug = 'rate-types';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_groups.catalogo');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.rate_types.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.rate_types.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.rate_types.model_label_plural');
    }

    /**
     * Conteo de precios que dependen de cada tarifa: lo usa la tabla (columna informativa
     * que adelanta por qué una tarifa con precios no se podrá borrar) → evita N+1.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('prices');
    }

    public static function form(Schema $schema): Schema
    {
        return RateTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RateTypeTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRateTypes::route('/'),
            'create' => CreateRateType::route('/create'),
            'edit' => EditRateType::route('/{record}/edit'),
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

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasPermission('prices.manage') ?? false;
    }

    /**
     * Borrado permitido solo a quien gestiona tarifas Y solo si la tarifa se puede borrar
     * sin riesgo (no es la base `normal`, no tiene precios ni la referencian fechas
     * especiales). El handler de `EditRateType` re-verifica con datos frescos.
     */
    public static function canDelete($record): bool
    {
        return ($record instanceof RateType)
            && (auth()->user()?->hasPermission('prices.manage') ?? false)
            && $record->canBeDeleted();
    }
}
