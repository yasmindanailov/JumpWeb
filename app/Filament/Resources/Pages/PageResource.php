<?php

namespace App\Filament\Resources\Pages;

use App\Domain\Content\Models\Page;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Filament\Resources\Pages\Schemas\PageForm;
use App\Filament\Resources\Pages\Tables\PageTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Fase 7.9 (iter. 2) — Páginas legales (`pages`): editar desde el panel el TÍTULO y el CUERPO
 * (por secciones encabezado/párrafo × idioma) de privacidad, condiciones, waiver, cookies y
 * aviso legal. El render público interpola los datos fiscales del titular (#206) — los tokens
 * `:legal_name`/`:legal_nif`/`:legal_address`/`:legal_email` se sustituyen al mostrar la página.
 *
 * **EDIT-ONLY a propósito:** cada página está atada a una ruta fija (`legal.{slug}`) y a enlaces
 * del pie; no se crean ni se borran desde aquí (dejaría rutas/enlaces huérfanos). El `slug` es
 * inmutable. **Acceso solo admin** (`content.manage`). Vive en el cluster «Configuración».
 */
class PageResource extends Resource
{
    protected static ?string $model = Page::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $slug = 'pages';

    // Detrás de Normas (93), antes de Usuarios (100).
    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return __('admin.pages.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.pages.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.pages.model_label_plural');
    }

    public static function form(Schema $schema): Schema
    {
        return PageForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PageTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPages::route('/'),
            'edit' => EditPage::route('/{record}/edit'),
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
        return false; // las páginas legales están atadas a rutas fijas; no se crean a mano
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasPermission('content.manage') ?? false;
    }

    public static function canDelete($record): bool
    {
        return false; // borrarlas dejaría su ruta (legal.{slug}) y los enlaces del pie en 404
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
