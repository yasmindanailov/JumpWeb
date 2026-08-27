<?php

namespace App\Filament\Resources\Orders\Pages\Concerns;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\DependentAssignment;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DependentAssigner;
use App\Domain\Platform\Services\DisplayTime;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * Fase 6 · menores a cargo, tanda 5 — la acción «Asignar menores» de una LÍNEA del pedido
 * (`docs/specs/menores-a-cargo.md` §9.10.2 D14·3, D14·4, D14·6; `DECISIONES #208`).
 *
 * Misma familia que «Gestionar»: se monta desde la sub-card con `mountAction('assignDependents',
 * { item })`, el modal es una lista de casillas —una por menor ACTIVO del titular, con el motivo de
 * las que no se pueden marcar— y el handler revalida TODO antes de tocar nada:
 *
 *  1. permiso `orders.edit_item` (D14·2: para quién es la entrada es un DATO de la línea);
 *  2. `Order::editItemBlockedReason()` — el MISMO gate que editar la línea (`not_in_order` cierra el
 *     IDOR, y cancelado/finalizado/complemento/pedido no operativo cierran lo demás), auditado como
 *     `orders.item_edit_blocked` igual que la familia;
 *  3. solo ENTRADAS;
 *  4. y el dominio: `DependentAssigner::sync()` bajo el lock del titular, con las reglas sobre lo que
 *     se AÑADE y fail-closed (cualquier rechazo → nada escrito, y se dice cuál).
 *
 * Lo que este trait NO hace: orquestar. El conjunto, el lock, las reglas y la auditoría viven en
 * Identity; aquí solo se pinta y se traduce el resultado (`desmontar-view-order.md`).
 *
 * ⚠️ Dependencias hacia la clase que lo compone: `$this->record`, `$this->resolveItem()`,
 * `$this->manageItemBlockedNotification()` (PresentsOrderActions), `$this->logItemActionBlocked()`.
 */
trait AssignsDependents
{
    /**
     * Memo por petición del contexto de cada ítem: el modal lo pide desde `fillForm`, `schema` y el
     * botón de guardar en el MISMO render, y son dos consultas cada vez.
     *
     * @var array<int, array<string, mixed>|null>
     */
    private array $dependentsContextMemo = [];

    public function assignDependentsAction(): Action
    {
        return Action::make('assignDependents')
            ->modalHeading(__('admin.orders.dependents.modal_heading'))
            ->modalDescription(__('admin.orders.dependents.modal_description'))
            ->modalIcon(Heroicon::OutlinedUsers)
            ->modalWidth(Width::Medium)
            // Sin candidatos no hay nada que guardar: el modal solo explica dónde se declaran.
            ->modalSubmitAction(function (?Action $action, array $arguments) {
                $context = $this->dependentsContext($arguments);

                return $context !== null && $context['options'] !== []
                    ? $action?->label(__('admin.orders.dependents.save'))
                    : false;
            })
            ->modalCancelAction(false)
            ->fillForm(fn (array $arguments): array => [
                'dependent_ids' => $this->dependentsContext($arguments)['current'] ?? [],
            ])
            ->schema(function (array $arguments): array {
                $context = $this->dependentsContext($arguments);
                if ($context === null) {
                    // Nunca un schema VACÍO: sin él el modal no tiene formulario y Livewire falla al
                    // montarlo (`$mountedActionSchema0` no existe). Un pack, un ítem ajeno o un pedido
                    // sin titular reciben el motivo; el handler lo vuelve a comprobar y lo audita.
                    return [Placeholder::make('blocked')->hiddenLabel()->content(
                        __('admin.orders.dependents.blocked', ['reason' => __('admin.orders.dependents.reasons.'.DependentAssigner::REASON_ENTRIES_ONLY)]),
                    )];
                }
                if ($context['options'] === []) {
                    return [Placeholder::make('none')->hiddenLabel()->content(__('admin.orders.dependents.none'))];
                }

                $components = [];
                if ($context['conserved'] !== []) {
                    $components[] = Placeholder::make('conserved')
                        ->hiddenLabel()
                        ->content(__('admin.orders.dependents.conserved_hint', ['names' => implode(', ', $context['conserved'])]));
                }
                $components[] = CheckboxList::make('dependent_ids')
                    ->label(__('admin.orders.dependents.field_label'))
                    ->options($context['options'])
                    ->descriptions($context['descriptions'])
                    // Deshabilitada = no se puede MARCAR. Una ya marcada con motivo sigue habilitada para
                    // poder DESMARCARLA (D14·3: lo que se mantiene no se re-valida; lo que se añade, sí).
                    ->disableOptionWhen(fn (string $value): bool => in_array((int) $value, $context['disabled'], true))
                    ->helperText(__('admin.orders.dependents.limit_hint', ['max' => $context['max']]))
                    ->columns(1);

                return $components;
            })
            ->action(function (array $data, array $arguments): void {
                $this->executeAssignDependents($arguments, $data);
            });
    }

    /**
     * Lo que el modal necesita de UNA línea: los candidatos del titular en la fecha de la visita (con su
     * motivo), lo ya asignado que el operador puede tocar, y lo que se CONSERVA sin enseñarse como
     * casilla (asignaciones a menores ya retirados de la cuenta, D14·3). `null` si la línea no admite
     * menores (no es entrada, no es de este pedido, no hay titular).
     *
     * @return array{item: OrderItem, holder: User, date: string, max: int, current: list<int>, conserved: list<string>, options: array<int, string>, descriptions: array<int, string>, disabled: list<int>}|null
     */
    private function dependentsContext(array $arguments): ?array
    {
        $itemId = (int) ($arguments['item'] ?? 0);
        if (array_key_exists($itemId, $this->dependentsContextMemo)) {
            return $this->dependentsContextMemo[$itemId];
        }

        $item = $this->resolveItem($arguments);
        /** @var Order $order */
        $order = $this->record;
        $holder = $order->user;

        if ($item === null
            || (int) $item->order_id !== (int) $order->getKey()
            || $item->ticketType?->type !== TicketType::TYPE_ENTRY
            || ! $holder instanceof User) {
            return $this->dependentsContextMemo[$itemId] = null;
        }

        $date = $item->slot?->date !== null
            ? CarbonImmutable::parse($item->slot->date)->toDateString()
            : DisplayTime::today()->toDateString();
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'UTC');

        $candidates = app(DependentAssigner::class)->candidates($holder, $date);
        $candidateIds = array_map(fn (array $c): int => (int) $c['dependent']->getKey(), $candidates);

        $current = [];
        $conserved = [];
        DependentAssignment::query()
            ->where('order_item_id', $item->getKey())
            ->with('dependent')
            ->orderBy('id')
            ->get()
            ->each(function (DependentAssignment $row) use (&$current, &$conserved, $candidateIds): void {
                $id = (int) $row->dependent_id;
                if (in_array($id, $candidateIds, true)) {
                    $current[] = $id;
                } else {
                    $conserved[] = (string) ($row->dependent?->name ?? "#{$id}");
                }
            });

        $options = [];
        $descriptions = [];
        $disabled = [];
        foreach ($candidates as $candidate) {
            /** @var Dependent $dependent */
            $dependent = $candidate['dependent'];
            $id = (int) $dependent->getKey();
            $options[$id] = __('admin.orders.dependents.option', ['name' => $dependent->name, 'age' => $dependent->ageOn($day)]);
            if ($candidate['reason'] !== null) {
                $descriptions[$id] = __('admin.orders.dependents.reasons.'.$candidate['reason']);
                if (! in_array($id, $current, true)) {
                    $disabled[] = $id;
                }
            }
        }

        return $this->dependentsContextMemo[$itemId] = [
            'item' => $item,
            'holder' => $holder,
            'date' => $date,
            'max' => max(0, (int) $item->quantity - count($conserved)),
            'current' => $current,
            'conserved' => $conserved,
            'options' => $options,
            'descriptions' => $descriptions,
            'disabled' => $disabled,
        ];
    }

    /**
     * El handler: cuatro capas de defensa y una llamada al dominio. Cada bloqueo se audita como en la
     * familia (`orders.item_edit_blocked`, con `dependents: true` en el payload para distinguirlo).
     *
     * @param  array<string, mixed>  $data
     */
    private function executeAssignDependents(array $arguments, array $data): void
    {
        /** @var Order $order */
        $order = $this->record->fresh();
        $item = $this->resolveItem($arguments);
        if ($item === null) {
            $this->manageItemBlockedNotification('not_found');

            return;
        }

        if (! (auth()->user()?->hasPermission('orders.edit_item') ?? false)) {
            $this->logItemActionBlocked($order, $item, 'edit', 'permission_denied', ['dependents' => true]);
            Notification::make()->title(__('admin.orders.manage_item.permission_denied'))->danger()->send();

            return;
        }

        $reason = $order->editItemBlockedReason($item);
        if ($reason !== null) {
            $this->logItemActionBlocked($order, $item, 'edit', $reason, ['dependents' => true]);
            $this->manageItemBlockedNotification($reason);

            return;
        }

        $holder = $order->user;
        if ($item->ticketType?->type !== TicketType::TYPE_ENTRY || ! $holder instanceof User) {
            $this->logItemActionBlocked($order, $item, 'edit', DependentAssigner::REASON_ENTRIES_ONLY, ['dependents' => true]);
            Notification::make()
                ->title(__('admin.orders.dependents.blocked', ['reason' => __('admin.orders.dependents.reasons.'.DependentAssigner::REASON_ENTRIES_ONLY)]))
                ->danger()
                ->send();

            return;
        }

        $ids = array_values(array_map('intval', array_filter((array) ($data['dependent_ids'] ?? []), 'is_numeric')));
        $outcome = app(DependentAssigner::class)->sync($holder, (int) $order->getKey(), (int) $item->getKey(), $ids);

        if ($outcome->abortedBecause === 'no_line') {
            $this->manageItemBlockedNotification('not_found');

            return;
        }
        if ($outcome->abortedBecause !== null) {
            Notification::make()->title(__('admin.orders.dependents.failed'))->danger()->persistent()->send();

            return;
        }
        if ($outcome->rejections !== []) {
            $reasons = array_values(array_unique(array_map(
                fn (string $reason): string => __('admin.orders.dependents.reasons.'.$reason),
                $outcome->rejections,
            )));
            Notification::make()
                ->title(__('admin.orders.dependents.rejected', ['reasons' => implode(' · ', $reasons)]))
                ->danger()
                ->send();

            return;
        }
        if (! $outcome->changed()) {
            Notification::make()->title(__('admin.orders.dependents.unchanged'))->info()->send();

            return;
        }

        $names = Dependent::query()->whereIn('id', $ids)->orderBy('id')->pluck('name')->all();
        Notification::make()
            ->title($names === []
                ? __('admin.orders.dependents.saved_none')
                : __('admin.orders.dependents.saved', ['names' => implode(', ', $names)]))
            ->success()
            ->send();
    }
}
