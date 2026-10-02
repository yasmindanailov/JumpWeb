<?php

namespace App\Filament\Resources\Catalog\RelationManagers;

use App\Domain\Booking\Models\AddonChoiceGroup;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Platform\Services\AuditLogger;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/**
 * Los GRUPOS DE OPCIONES de un producto (`[DECIDIDO owner]` `DECISIONES #914`, `fiesta-sistema-nuevo.md` §4.21): lo que es de
 * cada grupo —su título («¿Qué merienda?»), si hay que elegir y su orden—, una vez. Sus OPCIONES se eligen en «Complementos»
 * («Configurar» → «Grupo de opciones»): esto es el grupo, no sus miembros.
 *
 * ⚠️ La CLAVE no se cambia una vez creada: es la unión con las opciones (`product_addons.choice_group`), y cambiarla aquí
 * las dejaría huérfanas. Para renombrar, otro grupo y se mueven las opciones. Y un grupo con opciones no se borra (lo
 * impide el dominio; aquí se dice en vez de romper).
 */
class ChoiceGroupsRelationManager extends RelationManager
{
    protected static string $relationship = 'choiceGroups';

    protected static ?string $recordTitleAttribute = 'key';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.catalog.choice_groups.title');
    }

    /** Como «Complementos»: sobre productos base (entrada/pack), nunca sobre un complemento, y con permiso. */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof TicketType
            && ! $ownerRecord->isAddon()
            && (auth()->user()?->hasPermission('catalog.manage') ?? false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')
                ->label(__('admin.catalog.choice_groups.key'))
                ->helperText(__('admin.catalog.choice_groups.key_hint'))
                ->required()
                ->alphaDash()
                ->maxLength(AddonChoiceGroup::KEY_MAX)
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('product_id', $this->getOwnerRecord()->getKey()))
                // Inmutable una vez creada: es la unión con sus opciones.
                ->disabled(fn (?AddonChoiceGroup $record): bool => $record !== null)
                ->dehydrated(fn (?AddonChoiceGroup $record): bool => $record === null),
            TextInput::make('title.es')
                ->label(__('admin.catalog.choice_groups.title_es'))
                ->helperText(__('admin.catalog.choice_groups.title_hint'))
                ->required()
                ->maxLength(80),
            TextInput::make('title.en')->label(__('admin.catalog.choice_groups.title_en'))->maxLength(80),
            TextInput::make('title.fr')->label(__('admin.catalog.choice_groups.title_fr'))->maxLength(80),
            Toggle::make('is_required')
                ->label(__('admin.catalog.choice_groups.required'))
                ->helperText(__('admin.catalog.choice_groups.required_hint'))
                ->default(false),
            TextInput::make('position')
                ->label(__('admin.catalog.choice_groups.position'))
                ->numeric()
                ->minValue(0)
                ->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('position')
            ->columns([
                TextColumn::make('title')
                    ->label(__('admin.catalog.choice_groups.col_title'))
                    ->getStateUsing(fn (AddonChoiceGroup $record): string => (string) ($record->tr('title') ?? '—')),
                TextColumn::make('key')
                    ->label(__('admin.catalog.choice_groups.key'))
                    ->fontFamily('mono'),
                IconColumn::make('is_required')
                    ->label(__('admin.catalog.choice_groups.required'))
                    ->boolean(),
                TextColumn::make('members')
                    ->label(__('admin.catalog.choice_groups.members'))
                    ->getStateUsing(fn (AddonChoiceGroup $record): int => $record->memberCount()),
                TextColumn::make('position')
                    ->label(__('admin.catalog.choice_groups.position')),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label(__('admin.catalog.choice_groups.create'))
                    ->after(fn (AddonChoiceGroup $record) => $this->audit('catalog.choice_group_created', $record)),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(fn (AddonChoiceGroup $record) => $this->audit('catalog.choice_group_updated', $record)),
                DeleteAction::make()
                    ->before(function (AddonChoiceGroup $record, DeleteAction $action): void {
                        if ($record->memberCount() > 0) {
                            Notification::make()
                                ->title(__('admin.catalog.choice_groups.delete_has_members'))
                                ->warning()
                                ->send();
                            $action->cancel();
                        }
                    })
                    ->after(fn (AddonChoiceGroup $record) => $this->audit('catalog.choice_group_deleted', $record)),
            ])
            ->toolbarActions([])
            ->emptyStateHeading(__('admin.catalog.choice_groups.empty'));
    }

    /** El rastro (`RGPD-02`: sin datos de personas), como el resto de mutaciones del catálogo (#185). */
    private function audit(string $action, AddonChoiceGroup $group): void
    {
        AuditLogger::log($action, $this->getOwnerRecord(), [
            'key' => $group->key,
            'is_required' => $group->is_required,
            'position' => $group->position,
        ]);
    }
}
