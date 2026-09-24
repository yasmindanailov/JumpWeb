<?php

namespace App\Filament\Resources\Surveys;

use App\Domain\Platform\Models\Survey;
use App\Filament\Concerns\ProvidesGlobalSearch;
use App\Filament\Resources\Surveys\Pages\CreateSurvey;
use App\Filament\Resources\Surveys\Pages\EditSurvey;
use App\Filament\Resources\Surveys\Pages\ListSurveys;
use App\Filament\Resources\Surveys\Schemas\SurveyForm;
use App\Filament\Resources\Surveys\Tables\SurveyTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * **Las encuestas desde el panel** (`docs/specs/encuestas.md` §4.4, T1; `DECISIONES #740`): alta, textos en tres
 * idiomas, preguntas, encendido y ventana de cada encuesta, interna (la puerta) o externa (el correo del día
 * siguiente). Las RESPUESTAS no se editan aquí —las escriben la puerta y la página del correo— y los RESULTADOS
 * van en «Analítica → Encuestas», porque una encuesta se configura una vez y se mira muchas.
 *
 * **Acceso con `settings.manage`**, como los experimentos: es configuración del producto. Vive en «Ajustes →
 * Sistema» (`AdminSettingsHub`), fuera del menú plano (#223).
 *
 * ⚠️ Con respuestas guardadas no se cambian ni la clave, ni la clase, ni las claves y los tipos de las preguntas:
 * mezclarían lo medido antes con lo de después (`SurveyForm`). Y **una viva por clase**: encender una segunda
 * interna o externa se rechaza (`Survey::anotherRunning()`).
 */
class SurveyResource extends Resource
{
    use ProvidesGlobalSearch;

    public const PERMISSION = 'settings.manage';

    protected static ?string $model = Survey::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $slug = 'encuestas';

    public static function getNavigationLabel(): string
    {
        return __('admin.surveys.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.surveys.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.surveys.model_label_plural');
    }

    public static function form(Schema $schema): Schema
    {
        return SurveyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SurveyTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSurveys::route('/'),
            'create' => CreateSurvey::route('/create'),
            'edit' => EditSurvey::route('/{record}/edit'),
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

    /** Con respuestas, una encuesta no se borra: se apaga (las respuestas son de los clientes y del cuadro). */
    public static function canDelete($record): bool
    {
        return $record instanceof Survey && self::canViewAny() && ! $record->hasResponses();
    }

    /** #223 — fuera del menú lateral: se entra por «Ajustes». Ocultar NO es autorizar (`canViewAny()`). */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    // ─── Buscador del panel (#224): por clave (el nombre es json y se busca por su español) ──

    /** @return array<int, string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['key', 'name'];
    }

    protected static function globalSearchTitleAttribute(): string
    {
        return 'key';
    }
}
