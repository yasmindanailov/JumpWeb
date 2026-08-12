<?php

namespace App\Filament\Resources\SlotTemplates;

use App\Domain\Booking\Models\SlotTemplate;
use App\Filament\Resources\SlotTemplates\Pages\CreateSlotTemplate;
use App\Filament\Resources\SlotTemplates\Pages\EditSlotTemplate;
use App\Filament\Resources\SlotTemplates\Pages\ListSlotTemplates;
use App\Filament\Resources\SlotTemplates\Schemas\SlotTemplateForm;
use App\Filament\Resources\SlotTemplates\Tables\SlotTemplateTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fase 7.7 iter.3 — Plantillas de franja (`slot_templates`): la rejilla semanal de aforo por
 * zona y día de la semana de la que el generador (`slots:generate` / «Regenerar franjas») crea
 * las franjas concretas. Editar una plantilla NO cambia las franjas ya generadas: hay que
 * regenerar para aplicarlo (se avisa en el formulario).
 *
 * **Acceso solo admin** (`slots.manage`). Las plantillas no tienen dependientes por FK (las
 * franjas no apuntan a su plantilla), así que el borrado es seguro. Unicidad (zona, día, hora)
 * blindada en BD y validada en el form.
 */
class SlotTemplateResource extends Resource
{
    protected static ?string $model = SlotTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $slug = 'slot-templates';

    protected static ?int $navigationSort = 50;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_groups.programacion');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.slot_templates.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.slot_templates.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.slot_templates.model_label_plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('zone');
    }

    public static function form(Schema $schema): Schema
    {
        return SlotTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SlotTemplateTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSlotTemplates::route('/'),
            'create' => CreateSlotTemplate::route('/create'),
            'edit' => EditSlotTemplate::route('/{record}/edit'),
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

    public static function canDelete($record): bool
    {
        return ($record instanceof SlotTemplate)
            && (auth()->user()?->hasPermission('slots.manage') ?? false);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasPermission('slots.manage') ?? false;
    }
}
