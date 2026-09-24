<?php

namespace App\Filament\Resources\Experiments;

use App\Domain\Platform\Models\Experiment;
use App\Filament\Concerns\ProvidesGlobalSearch;
use App\Filament\Resources\Experiments\Pages\CreateExperiment;
use App\Filament\Resources\Experiments\Pages\EditExperiment;
use App\Filament\Resources\Experiments\Pages\ListExperiments;
use App\Filament\Resources\Experiments\Schemas\ExperimentForm;
use App\Filament\Resources\Experiments\Tables\ExperimentTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * **Los experimentos desde el panel** (`docs/specs/analitica.md` §4.4, T5b): alta, encendido, ventana y cierre de
 * cada prueba A/B. La ASIGNACIÓN no vive aquí —la calcula el servidor con el hash en cada petición
 * (`Platform\Services\Analytics\Experiments`)— y los RESULTADOS tampoco: van en «Analítica → Conversión»
 * (`ExperimentsWidget`), porque un experimento se configura una vez y se mira muchas.
 *
 * **Acceso con `settings.manage`**: es configuración del producto, como «Configuración». Vive en «Ajustes →
 * Sistema» (`AdminSettingsHub`), fuera del menú plano (#223).
 *
 * ⚠️ Con el experimento VIVO no se editan ni la clave ni las variantes: cambiar pesos u orden rebaraja a todos
 * los visitantes y la clave es lo que lleva el libro. Se apaga y se crea otro (`ExperimentForm`).
 */
class ExperimentResource extends Resource
{
    use ProvidesGlobalSearch;

    public const PERMISSION = 'settings.manage';

    protected static ?string $model = Experiment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?string $slug = 'experimentos';

    public static function getNavigationLabel(): string
    {
        return __('admin.experiments.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.experiments.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.experiments.model_label_plural');
    }

    public static function form(Schema $schema): Schema
    {
        return ExperimentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExperimentTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExperiments::route('/'),
            'create' => CreateExperiment::route('/create'),
            'edit' => EditExperiment::route('/{record}/edit'),
        ];
    }

    // ─── Autorización: `settings.manage` ─────────────────────────────────────

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission(self::PERMISSION) ?? false;
    }

    public static function canView($record): bool
    {
        return self::canViewAny();
    }

    public static function canCreate(): bool
    {
        return self::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return self::canViewAny();
    }

    public static function canDelete($record): bool
    {
        return $record instanceof Experiment && self::canViewAny();
    }

    /** #223 — fuera del menú lateral: se entra por «Ajustes». Ocultar NO es autorizar (`canViewAny()`). */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    // ─── Buscador del panel (#224): por nombre y por clave ───────────────────

    /** @return array<int, string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'key'];
    }

    protected static function globalSearchTitleAttribute(): string
    {
        return 'name';
    }
}
