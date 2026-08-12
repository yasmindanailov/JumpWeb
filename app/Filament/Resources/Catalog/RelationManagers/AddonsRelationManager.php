<?php

namespace App\Filament\Resources\Catalog\RelationManagers;

use App\Domain\Platform\Services\AuditLogger;
use App\Models\ProductAddon;
use App\Models\TicketType;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Fase 7.6 iter. 2 — Complementos aplicables a un producto (pivote `product_addons`).
 *
 * En la ficha de una **entrada o pack** (no de un complemento), gestiona QUÉ complementos
 * se le pueden añadir y CÓMO se ofrecen: **enganchar** (attach) un `type=addon`, **configurar**
 * (incluido / obligatorio / por-invitado / grupo de elección excluyente) y **quitar** (detach)
 * el enlace, sin borrar el complemento en sí. Solo admin (`catalog.manage`).
 *
 * La config de CÓMO se ofrece vive en el PIVOTE (por enganche, no global): así la misma "Tarta"
 * puede venir gratis-incluida en el pack de cumpleaños y de pago en una entrada. El flujo de
 * compra (`AddonResolver`) lee exactamente estos campos.
 */
class AddonsRelationManager extends RelationManager
{
    protected static string $relationship = 'configurableAddons';

    // Relación inversa explícita (pivote autorreferencial): sin esto, la acción "Añadir"
    // intenta adivinarla y llama a un `TicketType::ticketTypes()` inexistente.
    protected static ?string $inverseRelationship = 'addonOfProducts';

    // Título legible del registro (en/para los modales de attach/detach): el `name` es JSON.
    protected static ?string $recordTitleAttribute = 'name->es';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('admin.catalog.addons.title');
    }

    /** Solo sobre productos base (entrada/pack), nunca sobre un complemento, y con permiso. */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof TicketType
            && ! $ownerRecord->isAddon()
            && (auth()->user()?->hasPermission('catalog.manage') ?? false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('prices.rateType'))
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.catalog.addons.col_name'))
                    ->getStateUsing(fn (TicketType $record): string => (string) ($record->tr('name') ?? '—')),

                TextColumn::make('visibility')
                    ->label(__('admin.catalog.addons.col_status'))
                    ->badge()
                    // Avisa si el addon enganchado NO se ofrecerá al cliente (inactivo o fuera de venta):
                    // el flujo de compra usa `addons()`, que filtra is_active + is_sellable.
                    ->state(fn (TicketType $record): string => ($record->is_active && $record->is_sellable) ? 'visible' : 'hidden')
                    ->formatStateUsing(fn (string $state): string => __('admin.catalog.addons.status_'.$state))
                    ->color(fn (string $state): string => $state === 'visible' ? 'success' : 'gray'),

                // Config del pivote como conjunto de badges (Incluido / Obligatorio / Por invitado / Grupo).
                TextColumn::make('config')
                    ->label(__('admin.catalog.addons.col_config'))
                    ->badge()
                    ->state(fn (TicketType $record): array => $this->pivotBadges($record))
                    ->placeholder('—'),

                TextColumn::make('price')
                    ->label(__('admin.catalog.addons.col_price'))
                    ->getStateUsing(fn (TicketType $record): string => $record->prices->isEmpty()
                        ? '—'
                        : number_format($record->displayPriceCents() / 100, 2, ',', '.').' €')
                    ->alignEnd(),

                TextColumn::make('position')
                    ->label(__('admin.catalog.addons.col_position'))
                    ->getStateUsing(fn (TicketType $record): ?int => $record->pivot?->position),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label(__('admin.catalog.addons.attach'))
                    ->modalHeading(__('admin.catalog.addons.attach_heading'))
                    // Solo se pueden enganchar complementos (type=addon); el filtro también
                    // blinda la resolución del attach (no se puede colar un no-addon).
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query->where('type', TicketType::TYPE_ADDON))
                    // Opciones EXPLÍCITAS con el nombre traducido: el preload del AttachAction no
                    // resuelve el título cuando `name` es JSON (mostraba "ticket type"). Excluye los
                    // ya enganchados (como hacía el dropdown nativo) y ordena por catálogo.
                    ->recordSelect(fn (Select $select): Select => $select
                        ->label(__('admin.catalog.addons.attach_select'))
                        ->options(fn (): array => TicketType::query()
                            ->where('type', TicketType::TYPE_ADDON)
                            ->whereNotIn('id', $this->getOwnerRecord()->configurableAddons()->pluck('ticket_types.id'))
                            ->orderBy('position')
                            ->get()
                            ->mapWithKeys(fn (TicketType $addon): array => [$addon->getKey() => (string) ($addon->tr('name') ?? '')])
                            ->all())
                        ->searchable())
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        ...$this->pivotConfigFields(),
                    ])
                    // Rechazo limpio del attach duplicado: el dropdown ya excluye los enganchados,
                    // pero una petición Livewire directa (doble submit / "añadir otro") llamaría a
                    // attach() y violaría el unique(product_id, addon_id) → 500. Aquí se avisa y se corta.
                    ->before(function (array $data): void {
                        $addonId = (int) ($data['recordId'] ?? 0);
                        if ($this->getOwnerRecord()->configurableAddons()->wherePivot('addon_id', $addonId)->exists()) {
                            Notification::make()
                                ->title(__('admin.catalog.addons.already_attached'))
                                ->warning()
                                ->send();

                            throw new Halt;
                        }
                    })
                    // Auditoría coherente con el resto de mutaciones del catálogo (#185).
                    ->after(function (array $data): void {
                        AuditLogger::log('catalog.addon_attached', $this->getOwnerRecord(), [
                            'addon_id' => (int) ($data['recordId'] ?? 0),
                        ] + $this->sanitizePivotData($data));
                    }),
            ])
            ->recordActions([
                // Configurar el enganche (cambiar incluido/obligatorio/grupo… después de enganchar).
                Action::make('configure')
                    ->label(__('admin.catalog.addons.configure'))
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->modalHeading(__('admin.catalog.addons.configure_heading'))
                    ->fillForm(fn (TicketType $record): array => [
                        'is_included' => (bool) $record->pivot?->is_included,
                        'included_quantity' => (int) ($record->pivot?->included_quantity ?? 1),
                        'is_mandatory' => (bool) $record->pivot?->is_mandatory,
                        'quantity_mode' => $record->pivot?->quantity_mode ?? ProductAddon::MODE_FIXED,
                        'allow_extra' => (bool) ($record->pivot?->allow_extra ?? true),
                        'choice_group' => $record->pivot?->choice_group,
                        'max_qty' => $record->pivot?->max_qty,
                        'requires_addon_id' => $record->pivot?->requires_addon_id,
                        'position' => (int) ($record->pivot?->position ?? 0),
                    ])
                    ->schema(fn (TicketType $record): array => $this->pivotConfigFields((int) $record->getKey()))
                    ->action(function (TicketType $record, array $data): void {
                        $clean = $this->sanitizePivotData($data);
                        $this->getOwnerRecord()->configurableAddons()->updateExistingPivot($record->getKey(), $clean);
                        AuditLogger::log('catalog.addon_configured', $this->getOwnerRecord(), [
                            'addon_id' => $record->getKey(),
                        ] + $clean);
                        Notification::make()
                            ->title(__('admin.catalog.addons.configured'))
                            ->success()
                            ->send();
                    }),

                DetachAction::make()
                    // Al desenganchar un complemento, limpia las dependencias «requiere» de OTROS
                    // enganches de este producto que lo señalaban: si no, quedarían apuntando a un
                    // complemento ya no ofrecible → el dependiente nunca sería seleccionable (dead-end).
                    // (El FK `nullOnDelete` solo cubre el BORRADO del complemento, no el desenganche.)
                    ->before(function (TicketType $record): void {
                        $owner = $this->getOwnerRecord();
                        $dependents = $owner->configurableAddons()
                            ->wherePivot('requires_addon_id', $record->getKey())->get();
                        foreach ($dependents as $dependent) {
                            $owner->configurableAddons()->updateExistingPivot($dependent->getKey(), ['requires_addon_id' => null]);
                        }
                        if ($dependents->isNotEmpty()) {
                            Notification::make()
                                ->title(__('admin.catalog.addons.requires_cleared', ['count' => $dependents->count()]))
                                ->warning()
                                ->send();
                        }
                    })
                    ->after(function (TicketType $record): void {
                        AuditLogger::log('catalog.addon_detached', $this->getOwnerRecord(), [
                            'addon_id' => $record->getKey(),
                        ]);
                    }),
            ])
            ->toolbarActions([])
            ->emptyStateHeading(__('admin.catalog.addons.empty'));
    }

    /**
     * Campos del formulario que configuran el ENGANCHE (pivote `product_addons`). Compartidos por
     * la acción de enganchar y la de configurar, para que ambas escriban exactamente lo mismo.
     * `$selfAddonId` (solo en «Configurar») excluye el propio complemento de la lista de «Requiere»
     * para que no pueda depender de sí mismo.
     *
     * @return array<int, Field>
     */
    private function pivotConfigFields(?int $selfAddonId = null): array
    {
        return [
            Toggle::make('is_included')
                ->label(__('admin.catalog.addons.is_included'))
                ->helperText(__('admin.catalog.addons.is_included_hint'))
                ->default(false)
                ->live(),

            Select::make('quantity_mode')
                ->label(__('admin.catalog.addons.quantity_mode'))
                ->helperText(__('admin.catalog.addons.quantity_mode_hint'))
                ->options([
                    ProductAddon::MODE_FIXED => __('admin.catalog.addons.mode_fixed'),
                    ProductAddon::MODE_PER_GUEST => __('admin.catalog.addons.mode_per_guest'),
                ])
                ->default(ProductAddon::MODE_FIXED)
                ->selectablePlaceholder(false)
                ->live(),

            TextInput::make('included_quantity')
                ->label(__('admin.catalog.addons.included_quantity'))
                ->helperText(__('admin.catalog.addons.included_quantity_hint'))
                ->numeric()
                ->minValue(1)
                ->default(1)
                ->visible(fn (Get $get): bool => (bool) $get('is_included') && $get('quantity_mode') === ProductAddon::MODE_FIXED),

            Toggle::make('allow_extra')
                ->label(__('admin.catalog.addons.allow_extra'))
                ->helperText(__('admin.catalog.addons.allow_extra_hint'))
                ->default(true)
                ->visible(fn (Get $get): bool => $get('quantity_mode') === ProductAddon::MODE_FIXED),

            // P9: tope opcional de cantidad (solo cantidad fija; en por-invitado la marca el aforo).
            TextInput::make('max_qty')
                ->label(__('admin.catalog.addons.max_qty'))
                ->helperText(__('admin.catalog.addons.max_qty_hint'))
                ->numeric()
                ->minValue(1)
                ->visible(fn (Get $get): bool => $get('quantity_mode') === ProductAddon::MODE_FIXED),

            Toggle::make('is_mandatory')
                ->label(__('admin.catalog.addons.is_mandatory'))
                ->helperText(__('admin.catalog.addons.is_mandatory_hint'))
                ->default(false),

            TextInput::make('choice_group')
                ->label(__('admin.catalog.addons.choice_group'))
                ->helperText(__('admin.catalog.addons.choice_group_hint'))
                ->maxLength(50)
                // Reactivo: al marcar el complemento como miembro de grupo se oculta «Requiere».
                ->live(onBlur: true)
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? trim($state) : null),

            // Dependencia «requiere»: este complemento solo es seleccionable si el indicado ya está
            // elegido (p. ej. «Segunda tarta» requiere «Tarta»). Solo cantidad fija; opciones = los
            // OTROS complementos ya enganchados a este producto (excluido él mismo).
            Select::make('requires_addon_id')
                ->label(__('admin.catalog.addons.requires_addon'))
                ->helperText(__('admin.catalog.addons.requires_addon_hint'))
                // El requisito NO puede ser un miembro de un grupo de elección (su exclusividad es su
                // propio mecanismo; requerir uno dejaría al dependiente huérfano al cambiar de menú).
                ->options(fn (): array => $this->getOwnerRecord()->configurableAddons()
                    ->whereNull('product_addons.choice_group')
                    ->when($selfAddonId !== null, fn (Builder $query): Builder => $query->where('ticket_types.id', '!=', $selfAddonId))
                    ->orderBy('product_addons.position')
                    ->get()
                    ->mapWithKeys(fn (TicketType $addon): array => [$addon->getKey() => (string) ($addon->tr('name') ?? '')])
                    ->all())
                ->searchable()
                ->placeholder(__('admin.catalog.addons.requires_addon_none'))
                // Solo cantidad fija Y no-miembro-de-grupo: un complemento de grupo no «requiere» otro.
                ->visible(fn (Get $get): bool => $get('quantity_mode') === ProductAddon::MODE_FIXED && blank($get('choice_group'))),

            TextInput::make('position')
                ->label(__('admin.catalog.addons.position'))
                ->numeric()
                ->minValue(0)
                // Autoasigna la siguiente posición libre del pivote de ESTE producto.
                ->default(fn (): int => ((int) $this->getOwnerRecord()->configurableAddons()->max('product_addons.position')) + 1),
        ];
    }

    /**
     * Normaliza los datos del formulario del pivote a tipos correctos (defensa: no se confía en el
     * cliente). `included_quantity` solo aplica en modo fija; `choice_group` vacío → null.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizePivotData(array $data): array
    {
        $mode = in_array($data['quantity_mode'] ?? null, [ProductAddon::MODE_FIXED, ProductAddon::MODE_PER_GUEST], true)
            ? $data['quantity_mode']
            : ProductAddon::MODE_FIXED;
        $group = isset($data['choice_group']) && trim((string) $data['choice_group']) !== ''
            ? trim((string) $data['choice_group'])
            : null;

        return [
            'is_included' => (bool) ($data['is_included'] ?? false),
            'included_quantity' => max(1, (int) ($data['included_quantity'] ?? 1)),
            'is_mandatory' => (bool) ($data['is_mandatory'] ?? false),
            'quantity_mode' => $mode,
            'allow_extra' => (bool) ($data['allow_extra'] ?? true),
            'choice_group' => $group,
            // P9: el tope solo aplica a cantidad fija; al pasar a por-invitado se limpia (null).
            'max_qty' => ($mode === ProductAddon::MODE_FIXED && isset($data['max_qty']) && (int) $data['max_qty'] >= 1)
                ? (int) $data['max_qty']
                : null,
            // Dependencia «requiere»: solo cantidad fija y NO miembro de grupo (un complemento de
            // grupo no requiere otro); al pasar a por-invitado o a grupo se limpia (null).
            'requires_addon_id' => ($mode === ProductAddon::MODE_FIXED && $group === null && isset($data['requires_addon_id']) && (int) $data['requires_addon_id'] > 0)
                ? (int) $data['requires_addon_id']
                : null,
            'position' => max(0, (int) ($data['position'] ?? 0)),
        ];
    }

    /**
     * Badges resumen de la config del pivote para el listado (Incluido / Obligatorio / Por
     * invitado / Grupo). Array vacío → la columna muestra "—".
     *
     * @return array<int, string>
     */
    private function pivotBadges(TicketType $record): array
    {
        $pivot = $record->pivot;
        if (! $pivot) {
            return [];
        }

        $badges = [];
        if ($pivot->is_included) {
            $badges[] = __('admin.catalog.addons.badge_included');
        }
        if ($pivot->is_mandatory) {
            $badges[] = __('admin.catalog.addons.badge_mandatory');
        }
        if ($pivot->isPerGuest()) {
            $badges[] = __('admin.catalog.addons.badge_per_guest');
        }
        if ($pivot->choiceGroup() !== null) {
            $badges[] = __('admin.catalog.addons.badge_group', ['group' => $pivot->choiceGroup()]);
        }
        if ($pivot->requiresAddonId() !== null) {
            $reqName = TicketType::find($pivot->requiresAddonId())?->tr('name') ?? ('#'.$pivot->requiresAddonId());
            $badges[] = __('admin.catalog.addons.badge_requires', ['name' => $reqName]);
        }

        return $badges;
    }
}
