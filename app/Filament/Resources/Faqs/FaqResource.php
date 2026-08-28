<?php

namespace App\Filament\Resources\Faqs;

use App\Domain\Content\Models\Faq;
use App\Filament\Resources\Faqs\Pages\CreateFaq;
use App\Filament\Resources\Faqs\Pages\EditFaq;
use App\Filament\Resources\Faqs\Pages\ListFaqs;
use App\Filament\Resources\Faqs\Schemas\FaqForm;
use App\Filament\Resources\Faqs\Tables\FaqTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Fase 7.9 (iter. 1) — Preguntas frecuentes (`faqs`) de la landing: CRUD desde el panel para
 * que el contenido deje de estar quemado en el seeder. Texto i18n (es/en/fr), orden y
 * activar/desactivar; borrado libre (una FAQ no tiene dependientes).
 *
 * **Acceso solo admin** (`content.manage`, mismo permiso que zonas). Vive en el cluster
 * «Configuración».
 */
class FaqResource extends Resource
{
    protected static ?string $model = Faq::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?string $slug = 'faqs';

    // Detrás de Atracciones (91), antes de Normas (93).
    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('admin.faqs.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.faqs.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.faqs.model_label_plural');
    }

    public static function form(Schema $schema): Schema
    {
        return FaqForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FaqTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFaqs::route('/'),
            'create' => CreateFaq::route('/create'),
            'edit' => EditFaq::route('/{record}/edit'),
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
        return ($record instanceof Faq)
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
}
