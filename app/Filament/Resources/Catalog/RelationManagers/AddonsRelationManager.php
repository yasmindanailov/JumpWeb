<?php

namespace App\Filament\Resources\Catalog\RelationManagers;

use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Platform\Services\AuditLogger;
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
                    // blinda la resolución del attach (no se puede colar un no-addon). Y un PACK no
                    // puede enganchar complementos que OCUPAN (hora extra, `specs/hora-extra.md`
                    // §7·D2): allí «quedarse más» es que la fiesta dura más — otro mecanismo.
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query
                        ->where('type', TicketType::TYPE_ADDON)
                        ->when($this->getOwnerRecord()->isPack(), fn (Builder $q): Builder => $q->where('occupies_after_parent', false)))
                    // Opciones EXPLÍCITAS con el nombre traducido: el preload del AttachAction no
                    // resuelve el título cuando `name` es JSON (mostraba "ticket type"). Excluye los
                    // ya enganchados (como hacía el dropdown nativo) y ordena por catálogo.
                    ->recordSelect(fn (Select $select): Select => $select
                        ->label(__('admin.catalog.addons.attach_select'))
                        ->options(fn (): array => TicketType::query()
                            ->where('type', TicketType::TYPE_ADDON)
                            ->when($this->getOwnerRecord()->isPack(), fn (Builder $q): Builder => $q->where('occupies_after_parent', false))
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
                        $this->warnIfPostFormWithoutForm($data);
                    }),
            ])
            ->recordActions([
                // Configurar el enganche (cambiar incluido/obligatorio/grupo… después de enganchar).
                Action::make('configure')
                    ->label(__('admin.catalog.addons.configure'))
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->modalHeading(__('admin.catalog.addons.configure_heading'))
                    // ⚠️⚠️ **Tercera lista blanca, y la que peor falla** (`#413` §4.7·ter): enumera las
                    // claves a mano y una ausente **no recibe su `->default()`, queda `null` y se
                    // sobrescribe al guardar**. Traducido: un complemento bien configurado como venta
                    // posterior volvería a `booking` al tocar su POSICIÓN en la lista, semanas
                    // después y sin que nada falle. La guarda de simetría
                    // (`sanitizePivotData` ⊆ `fillForm`) cierra la familia entera, no solo este campo.
                    ->fillForm(fn (TicketType $record): array => [
                        'stage' => $record->pivot?->saleStage() ?? ProductAddon::STAGE_BOOKING,
                        'postform_cutoff_hours' => $record->pivot?->postformCutoffHours(),
                        // Tercera lista blanca: sin esta línea, tocar la POSICIÓN de un complemento
                        // apagaría su casilla de la invitación semanas después y sin que nada falle.
                        // ⚠️ Por método y no por propiedad: un acceso dinámico al pivote suma una
                        // entrada al trinquete de Larastan, y esa línea base solo encoge.
                        'show_in_invitation' => $record->pivot?->showsInInvitation() ?? false,
                        // F5 (`#749`): el bloque de la lista. La misma trampa: sin esta línea, tocar la posición lo borraría.
                        'postform_block' => $record->addonPivot()?->postformBlock(),
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
                        $this->warnIfPostFormWithoutForm($data);
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
        // La HORA EXTRA (`specs/hora-extra.md` §4.4·5): a un complemento que OCUPA no se le ofrecen
        // ni «por invitado» (impondría la hora extra a todo el grupo) ni «obligatorio» (la
        // auto-inyección haría invendible al padre cuando la franja siguiente no cabe). En el alta
        // el complemento se elige en el MISMO modal (`recordId`); en «Configurar» viene fijado
        // (`$selfAddonId`). La autoridad es el guard de `ProductAddon::booted()` — esto es la cara
        // amable que evita llegar a él.
        $occupyingAddon = function (Get $get) use ($selfAddonId): bool {
            $id = $selfAddonId ?? (int) $get('recordId');

            return $id > 0 && (bool) TicketType::query()->find($id)?->occupiesAfterParent();
        };

        // Su espejo (`#443`, §11.5.3): un EXTENSOR sí admite «por invitado» —es el encargo—, pero la
        // opción significa allí otra cosa, así que necesita su propio rótulo.
        $extendingAddon = function (Get $get) use ($selfAddonId): bool {
            $id = $selfAddonId ?? (int) $get('recordId');

            return $id > 0 && (bool) TicketType::query()->find($id)?->extendsParentStay();
        };

        // El candado del MODO con reservas vivas todavía editables (§11.11·A1). En el ALTA no puede
        // haber ninguna (el enganche aún no existe), así que solo mira en «Configurar».
        $modeLocked = function () use ($selfAddonId): bool {
            if ($selfAddonId === null) {
                return false;
            }

            return (bool) ProductAddon::query()
                ->where('product_id', $this->getOwnerRecord()->getKey())
                ->where('addon_id', $selfAddonId)
                ->first()?->hasEditableSoldLines();
        };

        // La FASE de venta (`specs/complementos-post-reserva.md` §4.1, `#413`). ⚠️ `->live()` no es
        // opcional: de él dependen las `visible()` de abajo, que son la CARA AMABLE de los guards del
        // pivote — sin reactividad el operador marcaría una combinación prohibida y se llevaría una
        // `InvalidArgumentException` sin capturar, o sea una pantalla de error.
        $postForm = fn (Get $get): bool => $get('stage') === ProductAddon::STAGE_POSTFORM;

        return [
            Select::make('stage')
                ->label(__('admin.catalog.addons.stage'))
                ->helperText(__('admin.catalog.addons.stage_hint'))
                ->options([
                    ProductAddon::STAGE_BOOKING => __('admin.catalog.addons.stage_booking'),
                    ProductAddon::STAGE_POSTFORM => __('admin.catalog.addons.stage_postform'),
                ])
                ->default(ProductAddon::STAGE_BOOKING)
                ->selectablePlaceholder(false)
                ->live(),

            // El plazo de corte, OBLIGATORIO en venta posterior (D10): `null` significaría «hereda el
            // cierre del post-form», que es el predicado con el reloj torcido de §4.9 — y dejaría
            // quitar un extra ya consumido. `0` es válido: «hasta que empiece la fiesta».
            TextInput::make('postform_cutoff_hours')
                ->label(__('admin.catalog.addons.cutoff'))
                ->helperText(__('admin.catalog.addons.cutoff_hint'))
                ->numeric()
                ->minValue(0)
                ->default(0)
                ->required($postForm)
                ->visible($postForm),

            // F5 de `fiesta-sistema-nuevo.md` (`#749`): en qué bloque de la LISTA DE INVITADOS va (la pregunta de la
            // tarta, lo de los padres o la rejilla de siempre). Solo en venta posterior: es donde se elige.
            Select::make('postform_block')
                ->label(__('admin.catalog.addons.postform_block'))
                ->helperText(__('admin.catalog.addons.postform_block_hint'))
                ->options([
                    ProductAddon::BLOCK_CAKE => __('admin.catalog.addons.postform_block_cake'),
                    ProductAddon::BLOCK_ADULTS => __('admin.catalog.addons.postform_block_adults'),
                ])
                ->placeholder(__('admin.catalog.addons.postform_block_none'))
                ->visible($postForm),

            // D12 · «el menú» de la invitación digital. Es una CASILLA del enganche y no una deducción
            // del grupo excluyente: deducirlo sería la trampa de los calcetines (`#485`), presentación
            // usada como identidad. El mismo complemento puede ser el menú en un pack y no en otro.
            Toggle::make('show_in_invitation')
                ->label(__('admin.catalog.addons.show_in_invitation'))
                ->helperText(__('admin.catalog.addons.show_in_invitation_hint'))
                ->default(false),

            Toggle::make('is_included')
                ->label(__('admin.catalog.addons.is_included'))
                ->helperText(__('admin.catalog.addons.is_included_hint'))
                ->default(false)
                ->live()
                // §4.3·2: un incluido da unidades gratis, y `free_quantity > 0` rompe la igualdad
                // `chargedSubtotalCents == Δ` de la que vive la propiedad de §1.3.
                ->visible(fn (Get $get): bool => ! $postForm($get)),

            Select::make('quantity_mode')
                ->label(__('admin.catalog.addons.quantity_mode'))
                // ⚠️ El rótulo de la opción cambia con el complemento (`#443`, §11.5.3): en un
                // extensor «por invitado» no significa «una unidad por invitado» —una hora es una
                // hora— sino **«se cobra por invitado»**. La misma casilla, dos lecturas, y el
                // catálogo tiene que decir la correcta o el operador configura a ciegas.
                ->helperText(fn (Get $get): string => match (true) {
                    $modeLocked() => __('admin.catalog.addons.quantity_mode_locked_sold'),
                    $extendingAddon($get) => __('admin.catalog.addons.quantity_mode_stay_hint'),
                    default => __('admin.catalog.addons.quantity_mode_hint'),
                })
                // §4.3·3: `per_guest` ataría la cantidad al nº de INVITADOS (los niños) y el caso del
                // owner es *para los adultos* — un número equivocado con aspecto de correcto.
                ->options(fn (Get $get): array => ($occupyingAddon($get) || $postForm($get))
                    ? [ProductAddon::MODE_FIXED => __('admin.catalog.addons.mode_fixed')]
                    : [
                        ProductAddon::MODE_FIXED => __('admin.catalog.addons.mode_fixed'),
                        ProductAddon::MODE_PER_GUEST => $extendingAddon($get)
                            ? __('admin.catalog.addons.mode_per_guest_stay')
                            : __('admin.catalog.addons.mode_per_guest'),
                    ])
                ->default(ProductAddon::MODE_FIXED)
                ->selectablePlaceholder(false)
                // El candado de §11.11·A1: la AUTORIDAD es el guard de `ProductAddon::booted()`; esto
                // es la cara amable que evita llegar a él. ⚠️ **`dehydrated` se queda en `true` a
                // propósito**: `sanitizePivotData()` reconstruye el array entero, así que una clave
                // ausente NO conserva su valor — cae a `fixed` y **revierte el enganche al guardar**.
                // Es la tercera lista blanca de `#413` §4.7·ter, y aquí muerde al revés.
                ->disabled(fn (): bool => $modeLocked())
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
                // §4.3·8 (D3): OBLIGATORIO en venta posterior. El enlace del post-form se reenvía, así
                // que la deuda máxima que un tercero puede crear tiene que estar declarada por el
                // parque — el «tope de 20» que parece existir vive en `AddonResolver::viewModel()`
                // como pista de UI, no como autoridad.
                ->required($postForm)
                ->visible(fn (Get $get): bool => $get('quantity_mode') === ProductAddon::MODE_FIXED),

            Toggle::make('is_mandatory')
                ->label(__('admin.catalog.addons.is_mandatory'))
                ->helperText(__('admin.catalog.addons.is_mandatory_hint'))
                ->default(false)
                // Oculto para un complemento que OCUPA (ver arriba) y para uno de venta POSTERIOR
                // (§4.3·1: un obligatorio se auto-inyecta, o sea crea deuda sin un clic): oculto no
                // dehidrata y `sanitizePivotData` lo deja en `false`, que es lo único que el guard admite.
                ->visible(fn (Get $get): bool => ! $occupyingAddon($get) && ! $postForm($get)),

            TextInput::make('choice_group')
                ->label(__('admin.catalog.addons.choice_group'))
                ->helperText(__('admin.catalog.addons.choice_group_hint'))
                ->maxLength(50)
                // Reactivo: al marcar el complemento como miembro de grupo se oculta «Requiere».
                ->live(onBlur: true)
                // §4.3·4: un grupo excluyente SIEMPRE tiene un elegido, y post-venta el estado normal
                // es «ninguno», que un grupo no sabe expresar.
                ->visible(fn (Get $get): bool => ! $postForm($get))
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
     * **D4 (`#413` §4.2.bis): un enganche de venta POSTERIOR sobre un producto SIN post-form se
     * AVISA, no se rechaza.**
     *
     * Rechazarlo ataría la configuración a `isPack()`, que es justo lo que el `[DECIDIDO owner]` del
     * alcance evita: la puerta del cliente es «hay post-form», no «es un pack». Pero callar tampoco
     * vale, y está medido: **21 de los 29 enganches reales cuelgan de ENTRADAS**, que no tienen
     * post-form, así que un `postform` ahí crea un complemento que nadie puede comprar jamás — solo el
     * operador desde «Gestionar».
     *
     * ⚠️ El aviso al guardar no basta por sí solo: una semana después nadie recuerda cuál está muerto.
     * Por eso lo acompaña la INSIGNIA de fase en la lista ({@see pivotBadges}).
     *
     * @param  array<string, mixed>  $data
     */
    private function warnIfPostFormWithoutForm(array $data): void
    {
        if (($data['stage'] ?? null) !== ProductAddon::STAGE_POSTFORM) {
            return;
        }

        $owner = $this->getOwnerRecord();
        if ($owner->isPack() && $owner->guestFields() !== []) {
            return;
        }

        Notification::make()
            ->title(__('admin.catalog.addons.postform_without_form'))
            ->body(__('admin.catalog.addons.postform_without_form_body'))
            ->warning()
            ->persistent()
            ->send();
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

        // ⚠️⚠️ **Segunda lista blanca entre el formulario y la fila** (`#413` §4.7·ter): lo que no esté
        // enumerado aquí NO se escribe nunca, así que «Configurar» no podría cambiar la fase de los
        // 29 enganches que ya existen — que es el gesto normal. Callan las tres y por eso hay guarda.
        $stage = in_array($data['stage'] ?? null, ProductAddon::STAGES, true)
            ? $data['stage']
            : ProductAddon::STAGE_BOOKING;
        $postForm = $stage === ProductAddon::STAGE_POSTFORM;

        return [
            'stage' => $stage,
            // El plazo solo significa algo en venta posterior; en `booking` se limpia. ⚠️ `0` es un
            // valor VÁLIDO y distinto de `null`, así que NO se puede copiar el patrón `>= 1` de
            // `max_qty`: convertiría «hasta que empiece» en «sin plazo», que es más permisivo.
            'postform_cutoff_hours' => ($postForm && isset($data['postform_cutoff_hours']) && $data['postform_cutoff_hours'] !== '')
                ? max(0, (int) $data['postform_cutoff_hours'])
                : null,
            // D12 (`#574`): «el menú» que la invitación digital enseña. Segunda lista blanca — sin
            // esta línea la casilla del formulario no se escribiría NUNCA, en silencio.
            'show_in_invitation' => (bool) ($data['show_in_invitation'] ?? false),
            // F5 (`#749`): el bloque de la lista, solo en venta posterior y de la lista cerrada; si no, se limpia.
            'postform_block' => ($postForm && in_array($data['postform_block'] ?? null, ProductAddon::POSTFORM_BLOCKS, true))
                ? $data['postform_block']
                : null,
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
        // La FASE, primero y siempre visible cuando no es la normal (`#413` §4.2.bis): el eje que
        // decide si un complemento puede generar deuda después de la venta no puede exigir abrir
        // «Configurar» uno por uno para verlo. ⚠️ Precedente medido: `max_qty` tampoco tiene insignia
        // y hay CERO filas que lo usen — un campo sin insignia es un campo que nadie usa.
        if ($pivot->isPostFormStage()) {
            $badges[] = $pivot->postformCutoffHours() === null
                ? __('admin.catalog.addons.badge_postform')
                : __('admin.catalog.addons.badge_postform_cutoff', ['hours' => $pivot->postformCutoffHours()]);
        }
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
