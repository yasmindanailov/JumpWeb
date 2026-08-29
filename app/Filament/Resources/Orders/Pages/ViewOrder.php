<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Domain\Booking\Contracts\ItemRefundRequest;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\ItemEditPricing;
use App\Domain\Booking\Services\MixedPartySurcharge;
use App\Domain\Booking\Services\OrderItemCanceller;
use App\Domain\Booking\Services\OrderItemEditor;
use App\Domain\Booking\Services\OrderItemEventDataWriter;
use App\Domain\Booking\Services\OrderItemRefunder;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\Concerns\AssignsDependents;
use App\Filament\Resources\Orders\Pages\Concerns\ManagesItemCalendar;
use App\Filament\Resources\Orders\Pages\Concerns\PresentsOrderActions;
use App\Notifications\GuestFormRequest;
use App\Notifications\OrderCancelled;
use App\Notifications\OrderConfirmation;
use App\Notifications\OrderItemCancelled;
use App\Notifications\OrderPaymentDeclined;
use App\Notifications\OrderRefunded;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

class ViewOrder extends ViewRecord
{
    use AssignsDependents;
    use ManagesItemCalendar;
    use PresentsOrderActions;
    use WithPagination;

    protected static string $resource = OrderResource::class;

    /**
     * Browser tab title (sin HTML): "Pedido JJ-XXXX".
     */
    public function getTitle(): string|Htmlable
    {
        /** @var Order $record */
        $record = $this->record;

        return __('admin.orders.heading_pedido').' '.$record->code;
    }

    /**
     * H1 enriquecida del detalle (decisión #132): código copiable + badge de estado
     * inline al lado del título. Reemplaza las entradas `code` y `displayStatus` de
     * la card "Resumen" — ahorra dos líneas y deja la referencia siempre visible.
     */
    public function getHeading(): string|Htmlable
    {
        return new HtmlString(view('filament.orders.view-heading', [
            'record' => $this->record,
        ])->render());
    }

    /**
     * Acciones administrativas sobre el pedido (sub-fase 7.2b, decisiones #138 +
     * #139 + corrección #140).
     *
     * Patrón defense in depth (#128 reusado):
     *  1. `->visible()` combina permiso + estado del Order → la acción ni se renderiza
     *     cuando no aplica.
     *  2. El handler revalida con `$record->fresh()` ANTES de tocar BD: si bloquea,
     *     escribe `orders.<action>_blocked` con razón estructurada y devuelve una
     *     `Notification` de error sin mutar nada.
     *  3. La transición de estado va envuelta en `DB::transaction`.
     *  4. El `notify(...)` se dispara DESPUÉS del commit (fuera del bloque txn).
     *
     * Modelo independiente de cancel vs refund (#139): un único email coherente
     * `OrderRefunded` con `alsoCancelled=true` cuando ambos eventos coinciden.
     *
     * Reenvío unificado (#140): UNA acción "Reenviar email" con Select de tipos.
     * Solo aparecen los tipos cuyo evento subyacente OCURRIÓ; el handler revalida.
     */
    protected function getHeaderActions(): array
    {
        return [
            // P11: las acciones del pedido (cancelar · reembolsar · reenviar email) se agrupan en un
            // menú nativo de Filament que se revela al pulsar. Refinamiento clienta (2026-06-13): el
            // disparador es un BOTÓN etiquetado «Acciones del pedido» (antes era un lápiz icon-only) —
            // `->button()` cambia el trigger a la vista de botón con texto (más explícito para el
            // empleado no técnico). La visibilidad por permiso/estado de cada acción se conserva (un
            // grupo sin acciones visibles no se pinta).
            ActionGroup::make([
                $this->cancelAction(),
                $this->refundAction(),
                $this->resendEmailAction(),
            ])
                ->button()
                ->label(__('admin.orders.actions.group_label'))
                ->color('gray'),
        ];
    }

    private function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label(__('admin.orders.actions.cancel.label'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (Order $record): bool => auth()->user()?->hasPermission('orders.cancel')
                && $record->canBeCancelled())
            ->requiresConfirmation()
            ->modalHeading(__('admin.orders.actions.cancel.modal_heading'))
            ->modalDescription(fn (Order $record): string => __(
                $record->status === Order::STATUS_PAID
                    ? 'admin.orders.actions.cancel.modal_description_paid'
                    : 'admin.orders.actions.cancel.modal_description_pending'
            ))
            ->modalSubmitActionLabel(__('admin.orders.actions.cancel.submit'))
            ->action(function (Order $record): void {
                $record = $record->fresh();

                $reason = $record->cancellationBlockedReason();
                if ($reason !== null) {
                    $this->logBlocked('orders.cancel_blocked', $record, $reason);
                    $this->blockedNotification('cancel', $reason);

                    return;
                }

                $previousStatus = $record->status;

                $operator = auth()->user();

                DB::transaction(function () use ($record, $previousStatus, $operator): void {
                    $record->status = Order::STATUS_CANCELLED;
                    $record->save();

                    // ⚠️⚠️ **Cancelar el PEDIDO cancela sus RESERVAS** (`DECISIONES #127`). Éste es el
                    // camino MÁS común del operador y hasta ahora dejaba las líneas vivas: el parque
                    // retenía el dinero, el panel ya no podía devolverlo desde aquí
                    // (`refundBlockedReason` bloquea los cancelados) y el cliente leía «Total 19,80 €»
                    // sin una palabra sobre lo que se le debe. Con la cascada, lo cobrado sin producto
                    // detrás aflora como «pendiente de devolución» y **el cliente se entera**.
                    $record->cancelLiveItems($operator);

                    AuditLogger::log(
                        action: 'orders.cancelled',
                        target: $record,
                        payload: [
                            'order_code' => $record->code,
                            'previous_status' => $previousStatus,
                        ],
                    );
                });

                $record->notifyCustomer(new OrderCancelled($record));

                Notification::make()
                    ->title(__('admin.orders.actions.cancel.success'))
                    ->success()
                    ->send();

                $this->refreshFormData(['status']);
            });
    }

    private function refundAction(): Action
    {
        return Action::make('refund')
            ->label(__('admin.orders.actions.refund.label'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->visible(fn (Order $record): bool => auth()->user()?->hasPermission('orders.refund')
                && $record->canBeRefunded())
            ->requiresConfirmation()
            ->modalHeading(__('admin.orders.actions.refund.modal_heading'))
            // Descripción contextual: si el servicio ya finalizó (#141), explicitamos
            // que solo se ofrece reembolso (sin opción de cancelar). Si el servicio
            // sigue activo, el texto estándar describe el flujo combinable.
            // `#153`: el importe SE NOMBRA — esta acción devuelve el pago ENTERO (cuando es
            // alcanzable nunca hay reembolsos previos: `refundBlockedReason` bloquea tras
            // cualquier parcial), y el operador tiene que leer CUÁNTO antes de confirmarlo.
            ->modalDescription(fn (Order $record): string => __(
                $record->canBeCancelled()
                    ? 'admin.orders.actions.refund.modal_description'
                    : 'admin.orders.actions.refund.modal_description_finished_service',
                ['amount' => $this->eurosFromCents((int) ($record->paidPayment()?->amount ?? 0))],
            ))
            ->modalSubmitActionLabel(__('admin.orders.actions.refund.submit'))
            // El Toggle "También cancelar" SOLO se ofrece si el Order admite todavía
            // cancelación (#141). Para Orders con servicio prestado (FINISHED),
            // cancelar es semánticamente incorrecto: el Toggle se oculta vía
            // `->visible()` para no inducir error operativo. Mantenemos el schema
            // como array estable (no closure raíz) para no romper la inicialización
            // de Filament; la condicional vive en el `->visible()` del Toggle.
            ->schema([
                // `#153` (owner): sin campo de importe AQUÍ a propósito — el parcial se devuelve
                // por LÍNEA (atribución que explica el desglose). Lo que sí hay es la señal: si el
                // operador NO va a cancelar, probablemente busca devolver una PARTE, y este aviso
                // le dice dónde se hace. Con «también cancelar» activo no aparece: devolver todo
                // y cancelar es el uso correcto de esta acción.
                Placeholder::make('partial_hint')
                    ->hiddenLabel()
                    ->content(__('admin.orders.actions.refund.partial_hint'))
                    ->visible(fn (Get $get): bool => ! (bool) $get('also_cancel')),
                // Modo del refund (#142): por defecto vía REST de Redsys (cierra el
                // bucle automáticamente). Manual es opt-in para casos en los que el
                // operador ya devolvió desde el portal banco y solo quiere registrar.
                Radio::make('mode')
                    ->label(__('admin.orders.actions.refund.mode_label'))
                    // #225 (D7): un pago en CAJA (sin gateway_order) NO se puede reembolsar por
                    // Redsys → solo se ofrece el «reembolso manual» (record-only).
                    ->options(fn (Order $record): array => $record->isRedsysRefundable()
                        ? [
                            PaymentRefund::MODE_REST => __('admin.orders.actions.refund.mode_rest'),
                            PaymentRefund::MODE_MANUAL => __('admin.orders.actions.refund.mode_manual'),
                        ]
                        : [PaymentRefund::MODE_MANUAL => __('admin.orders.actions.refund.mode_manual')])
                    ->descriptions([
                        PaymentRefund::MODE_REST => __('admin.orders.actions.refund.mode_rest_desc'),
                        PaymentRefund::MODE_MANUAL => __('admin.orders.actions.refund.mode_manual_desc'),
                    ])
                    ->default(fn (Order $record): string => $record->isRedsysRefundable()
                        ? PaymentRefund::MODE_REST
                        : PaymentRefund::MODE_MANUAL)
                    ->required(),
                Toggle::make('also_cancel')
                    ->label(__('admin.orders.actions.refund.also_cancel'))
                    ->helperText(__('admin.orders.actions.refund.also_cancel_help'))
                    // Cancelar el pedido completo exige `orders.cancel`, igual que la acción
                    // standalone (auditoría Fase 1, Sistema 5): el toggle solo se ofrece a quien
                    // tiene AMBOS permisos. La defensa real vive en el handler (re-fuerza false).
                    ->visible(fn (?Order $record): bool => ($record?->canBeCancelled() ?? false)
                        && (auth()->user()?->hasPermission('orders.cancel') ?? false))
                    // Reactivo: el motivo aparece o desaparece según se cancele o no.
                    ->live()
                    ->default(true),
                // ⚠️⚠️ **Por qué se devuelve, y solo cuando hace falta preguntarlo**
                // (`DECISIONES #127(c)`). Si además se cancela, el motivo es evidente —desapareció el
                // producto— y preguntarlo sería ruido. Si NO se cancela, el cliente conserva su
                // reserva y el dinero vuelve: sin esta respuesta, su desglose no puede decirle lo
                // único que necesita saber, **si sigue debiendo ese importe**.
                Radio::make('intent')
                    ->label(__('admin.orders.actions.refund.intent_label'))
                    ->options([
                        PaymentRefund::INTENT_COMPENSATION => __('admin.orders.actions.refund.intent_compensation'),
                        PaymentRefund::INTENT_PAID_IN_PERSON => __('admin.orders.actions.refund.intent_paid_in_person'),
                    ])
                    ->descriptions([
                        PaymentRefund::INTENT_COMPENSATION => __('admin.orders.actions.refund.intent_compensation_desc'),
                        PaymentRefund::INTENT_PAID_IN_PERSON => __('admin.orders.actions.refund.intent_paid_in_person_desc'),
                    ])
                    ->visible(fn (Get $get): bool => ! (bool) $get('also_cancel'))
                    ->required(fn (Get $get): bool => ! (bool) $get('also_cancel')),
            ])
            ->action(function (Order $record, array $data): void {
                $record = $record->fresh();

                $reason = $record->refundBlockedReason();
                if ($reason !== null) {
                    $this->logBlocked('orders.refund_blocked', $record, $reason);
                    $this->blockedNotification('refund', $reason);

                    return;
                }

                $mode = (string) ($data['mode'] ?? PaymentRefund::MODE_REST);
                // #225 (D7): defensa server-side — en pagos sin gateway (caja) el REST no es
                // ejecutable; forzamos «manual» (record-only) aunque llegara otro valor.
                if (! $record->isRedsysRefundable()) {
                    $mode = PaymentRefund::MODE_MANUAL;
                }
                $alsoCancelRequested = (bool) ($data['also_cancel'] ?? false);
                // Defensa server-side (auditoría Fase 1, Sistema 5): cancelar el pedido completo es
                // una operación de `orders.cancel`. El toggle ya se oculta sin ese permiso, pero un
                // rol con `orders.refund` y SIN `orders.cancel` (config no-default) no debe poder
                // cancelar por esta vía → se re-fuerza a false, espejo de la guarda de `mode`.
                if (! (auth()->user()?->hasPermission('orders.cancel') ?? false)) {
                    $alsoCancelRequested = false;
                }

                // Si se cancela, la intención es evidente y no se pregunta: el producto desapareció.
                $intent = $alsoCancelRequested
                    ? PaymentRefund::INTENT_VALUE_RETURNED
                    : ($data['intent'] ?? null);
                if (! in_array($intent, PaymentRefund::intents(), true)) {
                    $intent = null;
                }

                $result = $record->executeFullRefund(
                    by: auth()->user(),
                    mode: $mode,
                    alsoCancel: $alsoCancelRequested,
                    intent: $intent,
                );

                if (! ($result['ok'] ?? false)) {
                    $this->renderRefundFailure($result);

                    return;
                }

                // Post-commit: notify al cliente con `alsoCancelled` reflejando lo
                // realmente aplicado por el orquestador (puede ser distinto a lo
                // pedido si #141 lo bloqueó por estado FINISHED).
                $alsoCancelled = (bool) ($result['also_cancelled'] ?? false);
                $payment = $result['payment'] ?? null;
                $record->notifyCustomer(new OrderRefunded(
                    $record->refresh(),
                    $payment,
                    alsoCancelled: $alsoCancelled,
                ));

                $titleKey = match (true) {
                    $alsoCancelled => 'admin.orders.actions.refund.success_with_cancel_'.$mode,
                    default => 'admin.orders.actions.refund.success_only_'.$mode,
                };

                Notification::make()
                    ->title(__($titleKey))
                    ->success()
                    ->send();

                $this->refreshFormData(['status']);
            });
    }

    /**
     * Acción única "Reenviar email" (#140): el operador elige en un Select cuál
     * de los emails transaccionales reenviar. El Select solo lista los tipos
     * cuyo evento SUBYACENTE ya ocurrió en el pedido — reenviar un email sobre
     * algo que no pasó sería comunicación falsa al cliente. El handler revalida
     * con `Order::canResend($type)` (defense in depth #128).
     *
     * Tipos soportados:
     *  - confirmation:  Order paid → re-dispara `OrderConfirmation`.
     *  - refund:        Order con `refunded_at` set → re-dispara `OrderRefunded`
     *                   con `alsoCancelled = (status === cancelled)` para reflejar
     *                   el estado actual del servicio.
     *  - cancellation:  Order cancelled → re-dispara `OrderCancelled`.
     *  - payment_retry: Order pending + canBeRetried → re-dispara
     *                   `OrderPaymentDeclined` con el `Ds_Response` del último
     *                   Payment failed (o null si solo hay pending intent).
     *
     * Sin rate limit — el modal de confirmación filtra clicks accidentales; cada
     * envío queda en `orders.email_resent` audit log con `payload.type` para
     * auditoría a posteriori si hubiera abuso (decisión #138 confirmada).
     */
    private function resendEmailAction(): Action
    {
        return Action::make('resendEmail')
            ->label(__('admin.orders.actions.resend.label'))
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->visible(fn (Order $record): bool => auth()->user()?->hasPermission('orders.view')
                && $record->canResendAnyEmail())
            ->modalHeading(__('admin.orders.actions.resend.modal_heading'))
            ->modalDescription(fn (Order $record): string => __(
                'admin.orders.actions.resend.modal_description',
                ['email' => $record->user?->email ?? ''],
            ))
            ->modalSubmitActionLabel(__('admin.orders.actions.resend.submit'))
            ->schema(fn (Order $record): array => [
                Select::make('email_type')
                    ->label(__('admin.orders.actions.resend.type_label'))
                    ->options(self::resendTypeOptions($record))
                    ->required()
                    // Auto-selecciona si solo hay un tipo disponible (UX rápida).
                    ->default(fn (): ?string => count($record->availableResendEmailTypes()) === 1
                        ? $record->availableResendEmailTypes()[0]
                        : null)
                    ->native(false),
            ])
            ->action(function (Order $record, array $data): void {
                $record = $record->fresh();
                $type = (string) ($data['email_type'] ?? '');

                if (! $record->canResend($type)) {
                    AuditLogger::log(
                        action: 'orders.email_resent_blocked',
                        target: $record,
                        payload: [
                            'order_code' => $record->code,
                            'order_status' => $record->displayStatus(),
                            'requested_type' => $type,
                            'reason' => 'event_did_not_happen',
                        ],
                    );
                    Notification::make()
                        ->title(__('admin.orders.actions.resend.blocked'))
                        ->danger()
                        ->send();

                    return;
                }

                $this->dispatchResend($record, $type);

                // Minimización RGPD (auditoría Fase 1, Sistema 5): NO se persiste el email del
                // cliente en el payload. `audit_logs` no es Prunable y `User::anonymize()` no podía
                // alcanzar este registro → el email sobrevivía a la supresión (art. 17). El `target`
                // (Order → user) ya traza al destinatario mientras la cuenta exista; tras anonimizar,
                // deja de hacerlo (que es lo correcto). `log()` es para payloads SIN dato personal.
                AuditLogger::log(
                    action: 'orders.email_resent',
                    target: $record,
                    payload: [
                        'order_code' => $record->code,
                        'type' => $type,
                    ],
                );

                Notification::make()
                    ->title(__('admin.orders.actions.resend.success', [
                        'email' => $record->user?->email ?? '',
                        'type' => __('admin.orders.actions.resend.types.'.$type),
                    ]))
                    ->success()
                    ->send();
            });
    }

    /**
     * Routing de la notification a disparar según tipo. Cada tipo aquí tiene su
     * propio constructor con los datos del evento; mantenerlo en un método permite
     * que el handler de la acción sea más legible (validate → dispatch → audit).
     */
    private function dispatchResend(Order $record, string $type): void
    {
        $user = $record->user;
        if ($user === null) {
            return; // Order huérfana — defensive; la FK `user_id` no permite null pero el fresh() puede deserializar a null en escenarios extremos.
        }
        // #264-audit: cliente sin email enrutable → no encolar correos muertos (defensa; el Select de
        // reenvío ya no ofrece tipos sin email vía availableResendEmailTypes()).
        if (! filled($user->email)) {
            return;
        }

        match ($type) {
            Order::RESEND_TYPE_CONFIRMATION => $user->notify(new OrderConfirmation($record)),
            Order::RESEND_TYPE_REFUND => $user->notify(new OrderRefunded(
                $record,
                $record->paidPayment(),
                alsoCancelled: $record->status === Order::STATUS_CANCELLED,
            )),
            Order::RESEND_TYPE_CANCELLATION => $user->notify(new OrderCancelled($record)),
            Order::RESEND_TYPE_PAYMENT_RETRY => $user->notify(new OrderPaymentDeclined(
                $record,
                $record->lastFailedPayment()?->dsResponse(),
            )),
            // Individualizado POR RESERVA (#217): reenvía el enlace de CADA pack del pedido que pide el
            // post-form (relleno o no — el operador puede reenviarlo si el cliente perdió el email).
            Order::RESEND_TYPE_GUEST_FORM => $record->guestFormItems()
                ->each(fn (OrderItem $reservation) => $user->notify(new GuestFormRequest($reservation))),
        };
    }

    /**
     * Registro estructurado de intentos bloqueados (patrón #128).
     */
    private function logBlocked(string $action, Order $order, string $reason): void
    {
        AuditLogger::log(
            action: $action,
            target: $order,
            payload: [
                'order_code' => $order->code,
                'order_status' => $order->displayStatus(),
                'reason' => $reason,
            ],
        );
    }

    // ─── Sub-fase 7.2c — detalle por OrderItem (decisión #147ter) ────────
    // ─── Sub-fase 7.2e.2 — modal "Gestionar producto" (decisión #159) ────
    //
    // 7.2c migró de patrón "Blade + Alpine + POST tradicional" (#147bis) a
    // Filament Action nativo con Tabs nativas + modal + Notification +
    // form nativo. 7.2e.2 amplía esa base: el action se renombra de
    // `viewItemDetail` a `manageItem`, se reestructura el modal en 2 tabs
    // (Producto y reserva + Complementos) y la Tab 1 incorpora selectores
    // fecha+hora con validación estricta (aforo+pack+park+productWindow).
    // Se invoca desde `items-list.blade.php` vía `wire:click="mountAction(
    // 'manageItem', @js(['item' => $item->id]))"`. Defense in depth amplía
    // a 5 capas (slot ↔ aforo serializa con `lockForUpdate` sobre el NEW
    // slot dentro de la txn).

    /**
     * Opciones de tamaño de página para el modal del audit log agregado del
     * Order (sub-fase 7.2d, decisión #151 + #151bis). El operador elige
     * cuántas entradas ver a la vez desde el selector renderizado por
     * `<x-filament::pagination>`. Default 5 (decisión #151bis): el modal
     * con sticky header+footer y poco contenido se siente más compacto,
     * y el operador escala con el selector cuando lo necesita.
     */
    private const ORDER_AUDIT_PAGE_SIZES = [5, 10, 25, 50];

    /**
     * Tamaño de página actual del audit log del Order. Property pública
     * Livewire — `<x-filament::pagination>` enlaza el `<select>` del page-size
     * a este nombre via `currentPageOptionProperty`.
     *
     * `#[Url(as: ...)]` mantiene el valor en query string para deep-linking.
     */
    #[Url(as: 'auditPerPage', except: 5, history: true, keep: false)]
    public int $auditPerPage = 5;

    /**
     * Paginator del audit log agregado del Order. Renderizado por Livewire
     * como computed property — se recalcula en cada render cuando cambia
     * `$auditPerPage` o la página activa (manejada internamente por
     * `WithPagination` con `pageName='auditPage'`).
     */
    public function getOrderAuditPaginatorProperty(): LengthAwarePaginator
    {
        return $this->orderAuditBaseQuery()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->with('user:id,name,email')
            ->paginate(
                perPage: $this->auditPerPage,
                pageName: 'auditPage',
            );
    }

    /**
     * Action nativa de Filament para abrir el modal "Gestionar producto" del
     * OrderItem (sub-fase 7.2e.2, decisión #159 — rename y reestructura de
     * `viewItemDetailAction` entregada en 7.2c/#147ter/#149bis).
     *
     * Estructura del modal (abre directo en las tabs; la card-resumen superior
     * se eliminó en #171 por ser redundante con el Tab 1):
     *   - Tab 1 "Producto y reserva" (PRIMERA y default): selector fecha +
     *     selector hora con aforo real (`SlotAvailability` para entradas,
     *     `PackAvailability` para packs) y validación de `OperatingSchedule` +
     *     `ProductAvailability` aplicada en la lista de opciones. Si el
     *     item es pack con `eventFields` definidos y el operador tiene
     *     `orders.edit_event_data`, el form de event_data se renderiza
     *     debajo de los selectores fecha+hora (Tab 1 agrupa todos los
     *     campos editables del producto/reserva en un único schema).
     *   - Tab 2 "Complementos": placeholder disabled — su contenido
     *     operativo llega en 7.2e.4 (Tab Complementos con tabla addons +
     *     selector "Añadir complemento").
     *
     * Bloqueos (sub-fase 7.2e.0, `Order::editItemBlockedReason`):
     *  - item soft-cancelado, finalizado, addon directo, no operativo en el
     *    Order → el botón "Guardar cambios" se oculta y los selectores no
     *    son interactivos (mountUsing rechaza con notification + Halt).
     *
     * Defense in depth 5 capas (toca aforo real):
     *  1. `visible()` heredada de `orders.view` (el botón Gestionar siempre
     *     visible aunque las acciones internas estén bloqueadas — el modal
     *     puede consultarse en items cancelados/finalizados como histórico).
     *  2. `mountUsing()` revalida ownership + `editItemBlockedReason` ANTES
     *     de abrir el modal en items que sí permiten guardar (pack con
     *     event_data o slot mutable). Para items NO editables, el modal
     *     abre como read-only viewer.
     *  3. Sentinels en el form: `Hidden optimistic_token` (timestamp
     *     `item.updated_at`).
     *  4. Handler revalida `editItemBlockedReason` con fila fresca + audit
     *     log estructurado de bloqueos.
     *  5. `executeItemSlotChange` toma `lockForUpdate` sobre el NUEVO slot
     *     + revalida aforo con `availableFor`/`availableGuestsFor` DENTRO
     *     de la transacción + actualiza `slot_id` atómicamente.
     */
    public function manageItemAction(): Action
    {
        return Action::make('manageItem')
            ->modalWidth(Width::ThreeExtraLarge)
            ->modalHeading(__('admin.orders.manage_item.modal_heading'))
            // #171 (feedback clienta): la card-resumen superior se ELIMINÓ — era
            // redundante con el Tab 1 "Producto y reserva", que ya muestra
            // producto + cantidad + slot editables. El modal abre directo en las tabs.
            // Botón "Reembolsar" al pie del modal (#171): reemplaza al icono
            // por-item de la sub-card. Reusa íntegramente la action `refundItem`
            // vía `replaceMountedAction` (cierra Gestionar, abre Reembolsar con
            // el mismo item) — cero duplicación del formulario de reembolso.
            // #172: botón "Cancelar producto" al pie del modal — mismo patrón
            // que Reembolsar (#171), reusa la action `cancelItem` vía
            // `replaceMountedAction`. El icono de cancelar SIGUE en la sub-card
            // (la clienta pidió añadirlo también aquí, no moverlo).
            ->extraModalFooterActions([
                Action::make('cancelFromManage')
                    ->label(__('admin.orders.manage_item.cancel_button'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(function (): bool {
                        $itemId = $this->mountedActions[0]['arguments']['item'] ?? null;
                        if ($itemId === null || ! (auth()->user()?->hasPermission('orders.cancel_item') ?? false)) {
                            return false;
                        }
                        $item = OrderItem::find($itemId);
                        /** @var Order $order */
                        $order = $this->record;

                        return $item !== null && $order->canCancelItem($item);
                    })
                    ->action(function (): void {
                        $itemId = (int) ($this->mountedActions[0]['arguments']['item'] ?? 0);
                        if ($itemId > 0) {
                            $this->replaceMountedAction('cancelItem', ['item' => $itemId]);
                        }
                    }),
                Action::make('refundFromManage')
                    ->label(__('admin.orders.manage_item.refund_button'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('warning')
                    ->visible(function (): bool {
                        $itemId = $this->mountedActions[0]['arguments']['item'] ?? null;
                        if ($itemId === null || ! (auth()->user()?->hasPermission('orders.refund_item') ?? false)) {
                            return false;
                        }
                        $item = OrderItem::find($itemId);
                        /** @var Order $order */
                        $order = $this->record;

                        return $item !== null && $order->canRefundItem($item);
                    })
                    ->action(function (): void {
                        $itemId = (int) ($this->mountedActions[0]['arguments']['item'] ?? 0);
                        if ($itemId > 0) {
                            $this->replaceMountedAction('refundItem', ['item' => $itemId]);
                        }
                    }),
            ])
            // Submit visible solo si hay form que guardar:
            //  - Slot editable (canEditItem TRUE), o
            //  - Pack con eventFields + permiso edit_event_data.
            // Cuando ningún campo es editable, el modal funciona como viewer
            // (solo Cerrar) — caso típico: item cancelled/finished, addon,
            // Order pending/cancelled/refunded.
            ->modalSubmitAction(function (?Action $action, array $arguments) {
                $item = $this->resolveItem($arguments);
                if ($item === null) {
                    return false;
                }
                /** @var Order $order */
                $order = $this->record;

                return $this->itemHasAnyEditableField($item, $order)
                    ? $action?->label(__('admin.orders.manage_item.save'))
                    : false;
            })
            // P4: sin botón «Cerrar» en el footer — la X de la esquina superior derecha ya cierra el modal.
            ->modalCancelAction(false)
            ->mountUsing(function (array $arguments): void {
                // Sub-fase 7.2e.2bis6 (#160) + 7.2e.2bis9 (#163, fix):
                // Filament 5 rebindea las closures de `mountUsing` al
                // contexto de la Action. Diagnosticado empíricamente vía
                // Network tab que las properties Livewire seteadas dentro
                // de este closure NO persisten en el snapshot. Como
                // workaround robusto, las wire methods Y el helper
                // `buildCalendarViewData` invocan `ensureCalendarState
                // Initialized()` que lee el `item_id` desde
                // `mountedActions[0]['arguments']['item']` (que SÍ
                // persiste fiablemente) y resuelve el state on-demand.
                // Resultado: el calendario funciona correctamente sin
                // depender del éxito de este mountUsing.
                $this->resetCalendarStateForItem($arguments);
            })
            ->fillForm(function (array $arguments): array {
                $item = $this->resolveItem($arguments);
                if ($item === null) {
                    return [];
                }

                // ②b: miembros de grupos de elección → se excluyen de `addon_edits` (los gobierna el Radio).
                $groupMemberIds = $this->groupMemberAddonTypeIds($item);
                $mixedPartyLineIds = app(MixedPartySurcharge::class)->governedLineIds($item);

                // ②b: precarga el Radio de cada grupo con el miembro PRESENTE (el que el cliente eligió
                // al comprar), para que el modal arranque marcado en él. Con una Action+fillForm el
                // valor inicial debe venir AQUÍ (no basta el ->default() del campo).
                $groupDefaults = [];
                foreach ($this->itemChoiceGroups($item) as $groupKey => $group) {
                    $groupDefaults[self::groupFieldName($groupKey)] = $group['current'];
                }

                return $groupDefaults + [
                    'optimistic_token' => (string) ($item->updated_at?->getTimestamp() ?? ''),
                    // Sub-fase 7.2e.3 (#167): precarga del producto y la cantidad
                    // actuales para los selectores del Tab 1 (solo se renderizan
                    // si el item es editable; las claves extra se ignoran si no).
                    'product_id' => (int) $item->ticket_type_id,
                    'quantity' => (int) $item->quantity,
                    'event_data' => is_array($item->event_data) ? $item->event_data : [],
                    // Sub-fase 7.2e.4 (#170): precarga de los complementos activos
                    // para el repeater del Tab 2 (child_id + nombre + cantidad).
                    // ②b: los miembros de un grupo de elección NO se precargan aquí — su cambio lo
                    // gobierna el Radio del grupo (no una línea bloqueada redundante).
                    // ⚠️⚠️ La línea del SUPLEMENTO de fiesta mixta NO se ofrece: no es un complemento
                    // que el operador gobierne, es el reflejo de una edad que declaró el cliente.
                    // Ofrecerla era ofrecer un gesto que el sistema deshace en la misma pulsación
                    // (`specs/cumple-mixto.md` §12.4). El criterio lo da el dominio, no un id de
                    // producto adivinado aquí.
                    'addon_edits' => $item->children
                        ->reject(fn (OrderItem $c): bool => $c->isCancelled())
                        ->reject(fn (OrderItem $c): bool => in_array((int) $c->id, $mixedPartyLineIds, true))
                        ->reject(fn (OrderItem $c): bool => isset($groupMemberIds[(int) $c->ticket_type_id]))
                        ->values()
                        ->map(fn (OrderItem $c): array => [
                            'child_id' => $c->id,
                            'name' => $c->ticketType?->tr('name') ?? ('#'.$c->id),
                            'quantity' => (int) $c->quantity,
                        ])
                        ->all(),
                    'addon_adds' => [],
                ];
            })
            ->schema(function (array $arguments): array {
                $item = $this->resolveItem($arguments);
                if ($item === null) {
                    return [];
                }

                return [
                    Tabs::make('manage-item-tabs')
                        ->tabs($this->manageItemTabs($item))
                        ->contained(false)
                        ->columnSpanFull(),
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $this->executeManageItemSave($arguments, $data);
            });
    }

    /**
     * Tabs del modal Gestionar (sub-fase 7.2e.2bis6, decisión #160):
     *  - **Tab 1 "Producto y reserva"** (siempre presente): calendario
     *    visual con grid mensual (días seleccionables con estado de
     *    disponibilidad) + lista de horas como chips. Reemplaza los Selects
     *    nativos de fecha+hora de 7.2e.2 por una experiencia más
     *    intuitiva, alineada con la del flujo de compra del cliente (que en
     *    su día fue `Tickets\Purchase` y hoy es el cajón SPA).
     *  - **Tab 2 "Datos del evento"** (solo si pack + eventFields +
     *    permiso edit_event_data): form `event_data` separado del bloque
     *    fecha+hora. Restaura el patrón de 7.2c donde estos datos vivían
     *    en su propia tab — separación clara entre "cuándo se hace" y
     *    "qué se hace" para el operador.
     *  - **Tab 3 "Complementos"**: placeholder disabled hasta 7.2e.4.
     *
     * @return array<int, Tab>
     */
    /**
     * #173 (reestructura pedida por la clienta): 2 tabs.
     *  - "Reserva": SOLO fecha + franja (calendario).
     *  - "Editar producto": el producto en sí (selector producto + cantidad/
     *    invitados + datos del evento si pack) en una sección, y los complementos
     *    en otra. La antigua tab "Datos del evento" se pliega aquí.
     */
    private function manageItemTabs(OrderItem $item): array
    {
        return [
            $this->manageItemReservationTab($item),
            $this->manageItemEditProductTab($item),
        ];
    }

    private function itemHasEventDataTab(OrderItem $item): bool
    {
        $isPack = $item->ticketType?->isPack() ?? false;
        $eventFields = $item->ticketType?->eventFields() ?? [];
        $canEditEventData = auth()->user()?->hasPermission('orders.edit_event_data') ?? false;

        return $isPack && $eventFields !== [] && $canEditEventData;
    }

    /**
     * Tab 1 "Reserva" (#173) — SOLO fecha + franja horaria (calendario visual
     * + lista de horas). Los selectores de producto/cantidad y los datos del
     * evento se movieron al Tab "Editar producto". Si el item no es editable,
     * el partial renderiza el banner de bloqueo + calendario read-only.
     *
     * Sub-fase 7.2e.2bis7 (decisión #161): la closure `viewData` se re-evalúa
     * en cada render Livewire; el partial NO accede a `$this` (dentro de la
     * closure de `View`, `$this` es el View object, no el page Livewire).
     */
    private function manageItemReservationTab(OrderItem $item): Tab
    {
        return Tab::make(__('admin.orders.manage_item.tab_reservation'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->schema([
                Hidden::make('optimistic_token'),
                ViewComponent::make('filament.orders.partials.manage-item-calendar')
                    ->viewData(fn () => $this->buildCalendarViewData($item))
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Acción «Enlace del formulario» POR PRODUCTO (#263): un icono de enlace en la fila de acciones de
     * cada reserva con post-form abre este modal, que muestra el enlace FIRMADO para copiarlo y
     * enviarlo por WhatsApp/SMS (útil sobre todo si el cliente no tiene email). Se monta desde la
     * lista de productos con `mountAction('copyGuestFormLink', { item: <id> })`.
     *
     * El contenido es una VISTA Blade server-rendered (no un campo de formulario): así el enlace
     * aparece siempre (un campo `->default()` dentro de un modal con `fillForm` se quedaba vacío).
     * Defensa: solo entrega el enlace si el item es una reserva con post-form de un pedido PAGADO.
     */
    public function copyGuestFormLinkAction(): Action
    {
        return Action::make('copyGuestFormLink')
            ->modalHeading(__('admin.orders.copy_guest_form.modal_heading'))
            ->modalDescription(__('admin.orders.copy_guest_form.modal_description'))
            ->modalIcon(Heroicon::OutlinedLink)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin.orders.copy_guest_form.close'))
            ->modalContent(function (array $arguments): ?View {
                $item = $this->resolveItem($arguments);
                $url = $item !== null ? $this->guestFormLinkForManageItem($item) : null;
                if ($url === null) {
                    return null;
                }

                return view('filament.orders.partials.guest-form-link', ['url' => $url]);
            });
    }

    /**
     * Enlace FIRMADO del post-form de ESTA reserva. Solo cuando el item es una reserva con post-form
     * (`isGuestFormReservation()`) de un pedido PAGADO — coincide con lo que el endpoint del post-form
     * acepta (404 si no está pagado). Misma fuente que el email (`OrderItem::guestFormSignedUrl()`):
     * acceso sin sesión, caduca tras el evento. `null` = no se ofrece el enlace (entradas, complementos,
     * items cancelados, pedido no pagado). Gobierna tanto el icono de la lista como este modal.
     */
    public function guestFormLinkForManageItem(OrderItem $item): ?string
    {
        /** @var Order $order */
        $order = $this->record;

        // #264-audit (defensa IDOR, redundante con el scoping de resolveItem): el item DEBE pertenecer
        // al pedido en pantalla — nunca acuñar un enlace firmado a la reserva (PII de menores) de otro
        // pedido aunque se fuerce el id por `mountAction`.
        if ((int) $item->order_id !== (int) $order->id
            || $order->status !== Order::STATUS_PAID
            || ! $item->isGuestFormReservation()) {
            return null;
        }

        return $item->guestFormSignedUrl();
    }

    /**
     * Campos de producto + cantidad del Tab 1 (sub-fase 7.2e.3, #167) con un
     * Placeholder reactivo que muestra el diff de precio en vivo.
     *
     * Alcance acotado (decisión clienta 2026-06-01): solo productos del MISMO
     * tipo y MISMA zona (entrada↔entrada de la zona, pack↔pack). Cambiar de
     * zona o entre entrada/pack se hace con un pedido manual (7.3) — esos
     * productos no aparecen en el selector.
     *
     * @return array<int, Field>
     */
    private function productAndQuantityFields(OrderItem $item): array
    {
        $type = $item->ticketType;
        $isPack = $type?->isPack() ?? false;

        $quantity = TextInput::make('quantity')
            ->label($isPack
                ? __('admin.orders.manage_item.field_guests')
                : __('admin.orders.manage_item.field_quantity'))
            ->numeric()
            ->integer()
            ->required()
            ->minValue($isPack ? (int) ($type->min_qty ?? 1) : 1)
            ->live(onBlur: true);

        if ($isPack) {
            $quantity
                ->maxValue($type->max_qty !== null ? (int) $type->max_qty : null)
                ->helperText(__('admin.orders.manage_item.field_guests_help', [
                    'min' => (int) ($type->min_qty ?? 1),
                    'max' => $type->max_qty !== null ? (int) $type->max_qty : '∞',
                ]));
        }

        // Sub-fase 7.2e.3 (pulido #168): producto + cantidad EN LÍNEA (uno al
        // lado del otro) para ahorrar espacio vertical en el modal. El de
        // cantidad/invitados va más estrecho (4 de 12). En móvil ambos apilan
        // (grid de 1 columna por debajo de `sm`).
        return [
            Grid::make(['default' => 1, 'sm' => 12])
                ->schema([
                    Select::make('product_id')
                        ->label(__('admin.orders.manage_item.field_product'))
                        ->options($this->sameScopeProductOptions($item))
                        ->selectablePlaceholder(false)
                        ->native(false)
                        ->live()
                        // P6: cambiar de producto se BLOQUEA por ahora en la UI (no se usa). El selector
                        // se muestra deshabilitado con el producto actual. `->dehydrated()` mantiene el
                        // valor en el estado (el producto actual) para no romper el guardado ni la lógica
                        // de backend (que sigue existiendo, solo inaccesible por UI). Reactivar = quitar
                        // `->disabled()->dehydrated()`.
                        ->disabled()
                        ->dehydrated()
                        ->helperText(__('admin.orders.manage_item.product_scope_note'))
                        ->columnSpan(['default' => 1, 'sm' => 8]),
                    $quantity->columnSpan(['default' => 1, 'sm' => 4]),
                ])
                ->columnSpanFull(),
            Placeholder::make('price_preview')
                ->label(__('admin.orders.manage_item.price_heading'))
                ->content(fn (Get $get): HtmlString => $this->priceDiffPreview($item, $get))
                ->columnSpanFull(),
        ];
    }

    /** Formato de céntimos a euros con separadores ES (1.234,50). */
    private function eurosFromCents(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.');
    }

    /**
     * Los servicios del dominio de la extracción 4b (spec §9.6): la edición de
     * un ítem y su tarificación viven en `app/Domain/`. La página es un
     * componente Livewire —sin inyección por constructor—, así que se
     * resuelven del contenedor al usarlos, como el trait del calendario hace
     * con `ItemRescheduleOffer`.
     */
    private function itemEditor(): OrderItemEditor
    {
        return app(OrderItemEditor::class);
    }

    private function itemEditPricing(): ItemEditPricing
    {
        return app(ItemEditPricing::class);
    }

    private function eventDataWriter(): OrderItemEventDataWriter
    {
        return app(OrderItemEventDataWriter::class);
    }

    private function itemCanceller(): OrderItemCanceller
    {
        return app(OrderItemCanceller::class);
    }

    private function itemRefunder(): OrderItemRefunder
    {
        return app(OrderItemRefunder::class);
    }

    // ─── Complementos (Tab 2 del modal Gestionar, sub-fase 7.2e.4, #170) ───

    /**
     * Normaliza los edits de complementos del form (`$data`) a una estructura
     * estable (sub-fase 7.2e.4, #170). Filtra filas malformadas. Sin efectos.
     *
     * Contrato de `$data`:
     *  - `addon_edits`: filas `['child_id' => int, 'quantity' => int]` de los
     *    complementos ACTUALES (cantidad editable; 0 = quitar).
     *  - `addon_adds` : filas `['ticket_type_id' => int, 'quantity' => int]` de
     *    complementos NUEVOS a añadir.
     *
     * @return array{edits: array<int, array{child_id:int, quantity:int}>, adds: array<int, array{ticket_type_id:int, quantity:int}>}
     */
    private function normalizeAddonEdits(array $data, ?OrderItem $item = null): array
    {
        // Regla 12: no se confía en que el formulario oculte lo que no debe llegar. Aunque el
        // repeater ya no la pinte, una fila del suplemento que llegue por una petición fabricada se
        // descarta aquí — si no, se aceptaría un cambio que la reconciliación desharía acto seguido.
        $governed = $item === null ? [] : app(MixedPartySurcharge::class)->governedLineIds($item);

        $edits = [];
        foreach ((array) ($data['addon_edits'] ?? []) as $row) {
            if (! is_array($row) || ! isset($row['child_id'])) {
                continue;
            }
            if (in_array((int) $row['child_id'], $governed, true)) {
                continue;
            }
            $edits[] = [
                'child_id' => (int) $row['child_id'],
                'quantity' => (int) ($row['quantity'] ?? 0),
            ];
        }

        $adds = [];
        foreach ((array) ($data['addon_adds'] ?? []) as $row) {
            if (! is_array($row) || empty($row['ticket_type_id'])) {
                continue;
            }
            $adds[] = [
                'ticket_type_id' => (int) $row['ticket_type_id'],
                'quantity' => (int) ($row['quantity'] ?? 1),
            ];
        }

        return ['edits' => $edits, 'adds' => $adds];
    }

    /**
     * Complementos que el item PUEDE añadir: los del pivote `addons()` del
     * producto `$forType` (o el actual) que aún no están presentes como child
     * activo. Para el selector "Añadir complemento" (sub-fase 7.2e.4, #170).
     *
     * @return array<int, string> [ticket_type_id => nombre]
     */
    private function addableAddonsFor(OrderItem $item, ?TicketType $forType = null): array
    {
        $type = $forType ?? $item->ticketType;
        if ($type === null) {
            return [];
        }

        $present = $item->children
            ->reject(fn (OrderItem $c): bool => $c->isCancelled())
            ->map(fn (OrderItem $c): int => (int) $c->ticket_type_id)
            ->all();

        return $type->addons()
            ->get()
            ->reject(fn (TicketType $t): bool => in_array((int) $t->id, $present, true))
            // ②b: los miembros de un grupo de elección se cambian con el Radio, no por el selector
            // «Añadir» (evita dos controles para lo mismo y la confusión de añadir un 2.º del grupo).
            ->reject(fn (TicketType $t): bool => $t->pivot->choiceGroup() !== null)
            ->mapWithKeys(fn (TicketType $t): array => [$t->id => $t->tr('name')])
            ->all();
    }

    /**
     * Metadatos de los complementos AÑADIBLES (repeater `addon_adds` del modal Gestionar), por
     * ticket_type_id: si la cantidad está BLOQUEADA (per-invitado/grupo: la fija el aforo o la
     * elección, no la teclea el empleado), la cantidad efectiva a reflejar, el grupo excluyente y el
     * tope (incluido-sin-extras / `max_qty`). Espejo de {@see childAddonMeta} para los que aún NO
     * están en el item; misma autoridad que la compra pública.
     *
     * @return array<int, array{locked:bool, qty:int, group:?string, max:?int, per_guest:bool}>
     */
    private function addableAddonMeta(OrderItem $item): array
    {
        $type = $item->ticketType;
        if ($type === null) {
            return [];
        }

        $guests = (int) $item->quantity;
        $addons = $type->addons()->get();
        $nameById = $addons->mapWithKeys(fn (TicketType $a): array => [(int) $a->id => $a->tr('name')])->all();
        $out = [];
        foreach ($addons as $addon) {
            $pivot = $addon->pivot;
            $perGuest = $pivot->isPerGuest();
            $group = $pivot->choiceGroup();
            // Per-invitado: sin topes de «extra» (su cantidad es fija = invitados). Ver childAddonMeta.
            $caps = [];
            if (! $perGuest) {
                if ($pivot->is_included && ! $pivot->allow_extra) {
                    $caps[] = max(1, (int) $pivot->included_quantity);
                }
                if ($pivot->max_qty !== null) {
                    $caps[] = (int) $pivot->max_qty;
                }
            }

            $requiredId = $pivot->requiresAddonId();
            $out[(int) $addon->id] = [
                'locked' => $perGuest || $group !== null,
                'qty' => $perGuest ? $guests : ($pivot->is_mandatory ? max(1, (int) $pivot->included_quantity) : 1),
                'group' => $group,
                'max' => $caps === [] ? null : min($caps),
                'per_guest' => $perGuest,
                // Dependencia «requiere»: nombre del complemento que debe estar en la reserva (o null).
                'requires_name' => $requiredId !== null ? ($nameById[$requiredId] ?? null) : null,
            ];
        }

        return $out;
    }

    /**
     * ②b — Grupos de elección EXCLUYENTE del producto del item, para el control Radio del modal
     * Gestionar («como la landing»): por cada `choice_group` con ≥2 miembros, sus miembros
     * (id→nombre, en orden de pivote) y el miembro ACTUALMENTE presente (child activo) como valor por
     * defecto. Un grupo de un solo miembro no se ofrece (un radio de uno no aporta).
     *
     * @return array<string, array{members: array<int,string>, current: ?int}>
     */
    private function itemChoiceGroups(OrderItem $item): array
    {
        $type = $item->ticketType;
        if ($type === null) {
            return [];
        }
        $present = $item->children
            ->reject(fn (OrderItem $c): bool => $c->isCancelled())
            ->map(fn (OrderItem $c): int => (int) $c->ticket_type_id)
            ->all();

        $groups = [];
        foreach ($type->addons()->get() as $addon) {
            $group = $addon->pivot->choiceGroup();
            if ($group === null) {
                continue;
            }
            $groups[$group]['members'][(int) $addon->id] = (string) $addon->tr('name');
            if (in_array((int) $addon->id, $present, true)) {
                $groups[$group]['current'] = (int) $addon->id;
            }
        }

        $out = [];
        foreach ($groups as $key => $g) {
            if (count($g['members']) < 2) {
                continue;
            }
            $out[$key] = ['members' => $g['members'], 'current' => $g['current'] ?? null];
        }

        return $out;
    }

    /** ②b — Nombre de campo (form) determinista para el Radio de un grupo (evita chars problemáticos en el statePath). */
    private static function groupFieldName(string $groupKey): string
    {
        return 'group_choice_'.md5($groupKey);
    }

    /**
     * ②b — ticket_type_ids de TODOS los miembros de grupos de elección del producto. Se EXCLUYEN de
     * los repeaters de complementos sueltos (current/add): su cambio lo gobierna el Radio, no una
     * línea editable ni el selector «Añadir».
     *
     * @return array<int, true>
     */
    private function groupMemberAddonTypeIds(OrderItem $item): array
    {
        $type = $item->ticketType;
        if ($type === null) {
            return [];
        }
        $ids = [];
        foreach ($type->addons()->get() as $addon) {
            if ($addon->pivot->choiceGroup() !== null) {
                $ids[(int) $addon->id] = true;
            }
        }

        return $ids;
    }

    /**
     * ②b — Materializa la elección de cada grupo (Radio del modal) en el mecanismo EXISTENTE de
     * `addon_adds`: si el miembro elegido difiere del presente, se añade a `adds` y el guardado lo
     * sustituye (group-replacement ya probado, ViewOrder ~3160). NO toca el conteo/financiero: solo
     * traduce la intención del Radio a la MISMA entrada que ya consumía el selector «Añadir».
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function applyGroupChoices(OrderItem $item, array $data): array
    {
        foreach ($this->itemChoiceGroups($item) as $groupKey => $group) {
            $chosen = (int) ($data[self::groupFieldName($groupKey)] ?? 0);
            if ($chosen > 0 && isset($group['members'][$chosen]) && $chosen !== $group['current']) {
                $data['addon_adds'][] = ['ticket_type_id' => $chosen, 'quantity' => 1];
            }
        }

        return $data;
    }

    /**
     * Texto de ayuda del campo cantidad de un complemento actual según sus condiciones.
     *
     * @param  array{min?:int, locked?:bool}  $meta
     */
    private function addonQuantityHint(array $meta): ?string
    {
        if ($meta['locked'] ?? false) {
            return __('admin.orders.manage_item.addon_locked_hint');
        }
        if (($meta['min'] ?? 0) > 0) {
            return __('admin.orders.manage_item.addon_min_hint', ['min' => $meta['min']]);
        }

        return __('admin.orders.manage_item.addon_remove_hint');
    }

    /**
     * Tab 2 "Editar producto" (#173) — el producto en sí + sus complementos,
     * en dos secciones diferenciadas:
     *  - **Producto**: selector de producto (mismo tipo+zona) + cantidad/
     *    invitados + datos del evento (si el item es pack con `eventFields` y el
     *    operador tiene `orders.edit_event_data`). Pliega la antigua tab "Datos
     *    del evento".
     *  - **Complementos**: gestión real de addons (#170) — repeater de los
     *    actuales (cantidad editable, 0 = quitar) + "Añadir complemento".
     *
     * Si el item NO es editable o es él mismo un complemento (child), muestra el
     * banner read-only (la edición solo aplica al producto principal editable).
     */
    private function manageItemEditProductTab(OrderItem $item): Tab
    {
        $tab = Tab::make(__('admin.orders.manage_item.tab_edit_product'))
            ->icon(Heroicon::OutlinedPencilSquare);

        /** @var Order $order */
        $order = $this->record;
        if ($item->parent_item_id !== null || ! $order->canEditItem($item)) {
            return $tab->schema([
                ViewComponent::make('filament.orders.partials.manage-item-addons-placeholder')
                    ->columnSpanFull(),
            ]);
        }

        // Sección "Producto": producto + cantidad/invitados + datos del evento.
        $productFields = $this->productAndQuantityFields($item);
        if ($this->itemHasEventDataTab($item)) {
            $productFields = array_merge($productFields, $this->eventDataFormFields($item));
        }

        return $tab->schema([
            Section::make(__('admin.orders.manage_item.section_product'))
                ->icon(Heroicon::OutlinedTag)
                // P7: 2 columnas → los campos de evento de una línea quedan inline (el Grid de
                // producto/cantidad y el preview de precio ocupan toda la fila con columnSpanFull).
                ->columns(2)
                ->schema($productFields),
            Section::make(__('admin.orders.manage_item.section_addons'))
                ->icon(Heroicon::OutlinedSquares2x2)
                ->schema($this->addonFields($item)),
        ]);
    }

    /**
     * Campos del Tab Complementos (sub-fase 7.2e.4, #170): repeater de
     * complementos actuales (cantidad editable, 0 = quitar) + repeater "Añadir
     * complemento" con las opciones compatibles + Placeholder reactivo con el
     * importe del cambio. El estado fluye al handler via `$data['addon_edits']`
     * y `$data['addon_adds']`.
     *
     * @return array<int, Field|Placeholder|Repeater>
     */
    private function addonFields(OrderItem $item): array
    {
        $fields = [
            Placeholder::make('addons_intro')
                ->hiddenLabel()
                ->content(new HtmlString('<p class="text-sm" style="opacity:.75;">'.e(__('admin.orders.manage_item.addons_intro')).'</p>')),
        ];

        // ②b — Control Radio por grupo de elección excluyente (Menú 1 ⊻ Menú 2), «como la landing»:
        // arranca en el miembro presente; al guardar, el cambio se materializa vía `applyGroupChoices`
        // (reusa el group-replacement ya probado). Es la ÚNICA forma de cambiar un grupo (sus miembros
        // se excluyen de los repeaters de complementos sueltos).
        foreach ($this->itemChoiceGroups($item) as $groupKey => $group) {
            $fields[] = Radio::make(self::groupFieldName($groupKey))
                ->label(__('admin.orders.manage_item.group_choice_label'))
                ->helperText(__('admin.orders.manage_item.group_choice_hint'))
                ->options($group['members'])
                ->default($group['current'])
                ->live();
        }

        // Condiciones por complemento (MISMAS que la web): mín, bloqueo y badge según el pivote.
        $meta = $this->itemEditor()->childAddonMeta($item);

        // ②b: los miembros de grupo NO se listan como línea editable (los gobierna el Radio de arriba).
        $groupMemberIds = $this->groupMemberAddonTypeIds($item);
        $activeChildren = $item->children
            ->reject(fn (OrderItem $c): bool => $c->isCancelled())
            ->reject(fn (OrderItem $c): bool => isset($groupMemberIds[(int) $c->ticket_type_id]))
            ->values();
        if ($activeChildren->isNotEmpty()) {
            $fields[] = Repeater::make('addon_edits')
                ->label(__('admin.orders.manage_item.addons_current'))
                ->schema([
                    Hidden::make('child_id'),
                    Hidden::make('name'),
                    Placeholder::make('addon_label')
                        ->hiddenLabel()
                        ->content(function (Get $get) use ($meta): HtmlString {
                            $name = e((string) $get('name'));
                            $badge = $meta[(int) $get('child_id')]['badge'] ?? null;
                            if ($badge !== null) {
                                $name .= ' <span style="display:inline-block;margin-left:4px;padding:0 6px;border-radius:999px;background:#d1fae5;color:#047857;font-size:10px;font-weight:700;text-transform:uppercase;">'.e(__('tickets.addon_badge_'.$badge)).'</span>';
                            }

                            return new HtmlString($name);
                        }),
                    TextInput::make('quantity')
                        ->label(__('admin.orders.manage_item.addon_quantity'))
                        ->numeric()
                        ->integer()
                        // Los incluidos/obligatorios no bajan de lo incluido; los per-invitado/grupo
                        // se bloquean (se cambian con "elige menú", no editando la cantidad).
                        ->minValue(fn (Get $get): int => $meta[(int) $get('child_id')]['min'] ?? 0)
                        ->disabled(fn (Get $get): bool => $meta[(int) $get('child_id')]['locked'] ?? false)
                        ->dehydrated() // un campo deshabilitado conserva su valor (no se lee como "quitar")
                        ->required()
                        ->helperText(fn (Get $get): ?string => $this->addonQuantityHint($meta[(int) $get('child_id')] ?? []))
                        ->live(onBlur: true),
                ])
                ->columns(['default' => 1, 'sm' => 2])
                ->addable(false)
                ->deletable(false)
                ->reorderable(false);
        }

        $addable = $this->addableAddonsFor($item);
        if ($addable !== []) {
            $addMeta = $this->addableAddonMeta($item);
            $fields[] = Repeater::make('addon_adds')
                ->label(__('admin.orders.manage_item.addons_add'))
                ->schema([
                    Select::make('ticket_type_id')
                        ->label(__('admin.orders.manage_item.addon_name'))
                        ->options($addable)
                        ->required()
                        ->native(false)
                        ->live()
                        // Aviso reactivo: per-invitado (cantidad automática) o grupo excluyente (al
                        // guardar reemplaza al otro del grupo) → más intuitivo para el empleado.
                        ->helperText(function (Get $get) use ($addMeta): ?string {
                            $m = $addMeta[(int) $get('ticket_type_id')] ?? null;
                            if ($m === null) {
                                return null;
                            }
                            // Dependencia «requiere»: avisa de que el requisito debe estar en la reserva
                            // (el guardado lo BLOQUEA si falta, vía validateAddonEdits).
                            if (($m['requires_name'] ?? null) !== null) {
                                return __('admin.orders.manage_item.addon_add_requires_hint', ['name' => $m['requires_name']]);
                            }
                            if ($m['per_guest']) {
                                return __('admin.orders.manage_item.addon_add_per_guest_hint');
                            }

                            return $m['group'] !== null ? __('admin.orders.manage_item.addon_add_group_hint') : null;
                        })
                        // Per-invitado/grupo: la cantidad la fija el aforo/elección → la auto-rellena
                        // (y el campo de abajo se deshabilita), coherente con la web y con `addon_edits`.
                        ->afterStateUpdated(function (Set $set, $state) use ($addMeta): void {
                            $m = $addMeta[(int) $state] ?? null;
                            $set('quantity', ($m !== null && $m['locked']) ? $m['qty'] : 1);
                        }),
                    TextInput::make('quantity')
                        ->label(__('admin.orders.manage_item.addon_quantity'))
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->maxValue(fn (Get $get): ?int => $addMeta[(int) $get('ticket_type_id')]['max'] ?? null)
                        // Per-invitado/grupo: NO editable (la cantidad es automática); el servidor la
                        // fuerza igual en `computeAddonPricing`, aquí lo hacemos visible y coherente.
                        ->disabled(fn (Get $get): bool => $addMeta[(int) $get('ticket_type_id')]['locked'] ?? false)
                        ->dehydrated()
                        ->default(1)
                        ->required()
                        ->helperText(function (Get $get) use ($addMeta): ?string {
                            $m = $addMeta[(int) $get('ticket_type_id')] ?? null;
                            if ($m !== null && $m['per_guest']) {
                                return __('admin.orders.manage_item.addon_add_per_guest_qty', ['count' => $m['qty']]);
                            }

                            return ($m !== null && $m['locked']) ? __('admin.orders.manage_item.addon_locked_hint') : null;
                        })
                        ->live(onBlur: true),
                ])
                ->columns(['default' => 1, 'sm' => 2])
                ->addActionLabel(__('admin.orders.manage_item.addons_add_button'))
                ->defaultItems(0)
                ->reorderable(false);
        } else {
            $fields[] = Placeholder::make('addons_add_none')
                ->hiddenLabel()
                ->content(new HtmlString('<p class="text-sm" style="opacity:.6;">'.e(__('admin.orders.manage_item.addons_add_none')).'</p>'));
        }

        $fields[] = Placeholder::make('addons_price_preview')
            ->label(__('admin.orders.manage_item.addons_price_heading'))
            ->content(fn (Get $get): HtmlString => $this->addonDiffPreview($item, $get));

        return $fields;
    }

    /**
     * Campos del form de `event_data` generados dinámicamente desde el
     * esquema del pack (`eventFields`). Soporta `text` / `number` /
     * `textarea` con `required` heredado del esquema.
     *
     * Sub-fase 7.2e.2 (decisión #159): el `Hidden optimistic_token` se
     * mueve a `manageItemProductTab` para que sea único en el schema —
     * antes vivía en este helper porque era la única tab editable.
     *
     * @return array<int, Field>
     */
    private function eventDataFormFields(OrderItem $item): array
    {
        $ticketType = $item->ticketType;
        if ($ticketType === null) {
            return [];
        }

        $fields = [];

        foreach ($ticketType->eventFields() as $field) {
            $key = $field['key'];
            $type = $field['type'] ?? 'text';
            $required = (bool) ($field['required'] ?? false);
            $label = $ticketType->eventFieldLabel($field);
            $fieldName = "event_data.{$key}";

            $fields[] = match ($type) {
                'textarea' => Textarea::make($fieldName)
                    ->label($label)
                    ->required($required)
                    ->rows(3)
                    ->columnSpanFull(),
                // P7: los campos de evento de una sola línea (texto/número) van INLINE (1 de 2
                // columnas); la sección «Producto» es `columns(2)`. La textarea ocupa toda la fila.
                default => TextInput::make($fieldName)
                    ->label($label)
                    ->required($required)
                    ->inputMode(TicketType::isNumericFieldType($type) ? 'numeric' : 'text'),
            };
        }

        return $fields;
    }

    /**
     * Resuelve el OrderItem desde los `$arguments['item']` con eager loading
     * de las relaciones que el modal necesita. Devuelve null si no existe
     * (defense-in-depth — el render de la action lo asume).
     */
    private function resolveItem(array $arguments): ?OrderItem
    {
        $id = $arguments['item'] ?? null;
        if ($id === null) {
            return null;
        }

        // F3: el partial item-summary-flat consulta $item->order->isVoidedLeftoverItem()
        // (adjustments + payment_refunds del pedido) → eager-load para no lazy-cargar
        // en el modal de cancelar/reembolsar.
        //
        // Nota IDOR (#264-audit): resolveItem NO se acota al pedido a propósito — las acciones de
        // MUTACIÓN (gestionar/cancelar/reembolsar) ya rechazan items de otro pedido AGUAS ABAJO con
        // su propia guarda auditada (`editItemBlockedReason`/ownership + audit log; ver el test
        // `item_from_other_order_rejected`). La acción de COPIAR-ENLACE (solo lectura, sin audit de
        // mutación) cierra el IDOR en su propio gate: `guestFormLinkForManageItem` exige `order_id`.
        return OrderItem::with([
            'ticketType', 'slot', 'parent', 'children.ticketType',
            'order.adjustments', 'order.payments.refunds',
        ])->find($id);
    }

    // ─── Sub-fase 7.2e.2 — modal Gestionar Tab 1 (decisión #159) ──────────

    /**
     * ¿El item tiene ALGÚN campo editable (slot o event_data)?
     * Helper de visibilidad del botón "Guardar cambios" del modal Gestionar.
     */
    private function itemHasAnyEditableField(OrderItem $item, Order $order): bool
    {
        $canEditSlot = $order->canEditItem($item);
        $isPack = $item->ticketType?->isPack() ?? false;
        $eventFields = $item->ticketType?->eventFields() ?? [];
        $canEditEventData = auth()->user()?->hasPermission('orders.edit_event_data') ?? false;
        $hasEventDataForm = $isPack && $eventFields !== [] && $canEditEventData;

        return $canEditSlot || $hasEventDataForm;
    }

    // ─── Calendario visual del modal Gestionar (7.2e.2bis6, #160) ─────────

    /**
     * Query base reusable para el computed `orderAuditPaginator`. Captura en
     * una closure las dos condiciones OR (Order + ItemIn) para garantizar
     * que la paginación es consistente y no leakea cross-Order.
     *
     * Sub-fase 7.2d (decisión #151).
     *
     * @return Builder<AuditLog>
     */
    private function orderAuditBaseQuery(): Builder
    {
        /** @var Order $order */
        $order = $this->record;
        // Alias del morphMap (Fase 2): audit_logs.target_type guarda 'order'/'order_item'.
        $orderClass = (new Order)->getMorphClass();
        $itemClass = (new OrderItem)->getMorphClass();
        $orderId = $order->id;
        $itemIds = $order->items->pluck('id')->all();

        return AuditLog::query()
            ->where(function ($q) use ($orderClass, $orderId, $itemClass, $itemIds): void {
                $q->where(function ($q2) use ($orderClass, $orderId): void {
                    $q2->where('target_type', $orderClass)
                        ->where('target_id', $orderId);
                })
                    ->orWhere(function ($q2) use ($itemClass, $itemIds): void {
                        $q2->where('target_type', $itemClass)
                            ->whereIn('target_id', $itemIds);
                    });
            });
    }

    /**
     * Action Filament para abrir el modal del audit log agregado del Order
     * (decisión #151). Se monta desde el CTA al final de la card "Detalles"
     * en `OrderInfolist::detailsSection()` con `wire:click="mountAction('viewOrderHistory')"`.
     * Sin `arguments` — el record es el Order completo del que extender la
     * vista; el método agrega `target_type=Order + Items`.
     *
     * Permiso: hereda de `orders.view` (cualquier staff con acceso al Order
     * puede ver su audit). NO requiere `audit.view` específico — la operativa
     * documentada (#150) implica que el staff debe poder defender al cliente
     * sobre qué pasó con su pedido.
     */
    public function viewOrderHistoryAction(): Action
    {
        return Action::make('viewOrderHistory')
            ->modalWidth(Width::FourExtraLarge)
            ->modalHeading(__('admin.orders.audit_modal.heading'))
            ->modalDescription(__('admin.orders.audit_modal.description'))
            // Sin form, solo lectura — el modal es puro viewer.
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin.orders.item_detail.modal_close'))
            // Sticky header + footer (decisión #151bis tras feedback empírico):
            // sin esto el modal Filament expande su altura al contenido y la
            // PÁGINA es la que scrollea (peor UX, pierde contexto del Order).
            // Sticky fija header (heading + close) y footer (cancel) → el
            // contenido entre ambos consume `max-height: 90vh` con scroll
            // interno propio.
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->visible(fn (): bool => auth()->user()?->hasPermission('orders.view') ?? false)
            ->modalContent(fn () => view('filament.orders.partials.order-audit-view', [
                'order' => $this->record,
                'paginator' => $this->orderAuditPaginator,
                'perPageOptions' => self::ORDER_AUDIT_PAGE_SIZES,
            ]));
    }

    /**
     * Handler maestro del modal Gestionar (sub-fase 7.2e.2, decisión #159).
     *
     * Orquesta los dos sub-saves de Tab 1 — slot_change y event_data —
     * dentro del mismo flujo defense in depth con un solo email + un solo
     * audit log consolidados. Patrón:
     *
     *  1. Resolver item + permission + ownership + optimistic.
     *  2. Detectar diffs (slot_change, event_data_change).
     *  3. Si no hay nada → notification info "Sin cambios".
     *  4. Si solo event_data cambió → delegar a `saveItemEventData` (existing).
     *  5. Si slot cambió → `executeItemSlotChange` (atómico).
     *     Si además event_data cambió → procesar tras el slot (misma txn
     *     conceptual; el orden es: slot primero porque su validación es
     *     más cara). Email único cubre ambos `$changes`.
     *  6. Email `OrderItemModified` con `$changes` consolidado.
     *
     * Sub-fase 7.2e.2 entrega solo cambio de slot. event_data delega a
     * `saveItemEventData` ya validada en 7.2c — si solo event_data cambia
     * y el slot no, el flujo recae en ella (mismo email + audit que 7.2c).
     */
    private function executeManageItemSave(array $arguments, array $data): void
    {
        $user = auth()->user();
        /** @var Order $order */
        $order = $this->record->fresh();
        $item = OrderItem::with(['ticketType', 'slot', 'children.ticketType'])->find($arguments['item'] ?? null);

        if ($item === null || $item->order_id !== $order->id) {
            $this->itemEventDataBlockedNotification('not_found');

            return;
        }

        // Sub-fase 7.2e.2bis6 (decisión #160): el calendario visual del Tab 1
        // gestiona la selección fecha+hora via state Livewire (no via Selects
        // del form). El handler lee:
        //   - PRIMERO de las properties Livewire (runtime: calendario
        //     interactivo en el modal).
        //   - FALLBACK a `$data['slot_*']` si las properties están vacías —
        //     mantiene la API compatible con tests/automatización que setean
        //     valores via `data` del `callAction` y permite a futuras
        //     integraciones programáticas bypasear el calendario sin perder
        //     la defense in depth.
        $newDate = (string) ($this->calendarSelectedDate ?? $data['slot_date'] ?? '');
        $newTime = (string) ($this->calendarSelectedTime ?? $data['slot_time'] ?? '');
        $currentSlot = $item->slot;
        $slotChanged = $newDate !== '' && $newTime !== '' && (
            $currentSlot === null
            || $currentSlot->date->toDateString() !== $newDate
            || $currentSlot->start_time !== $newTime
        );

        $rawEventData = (array) ($data['event_data'] ?? []);
        $isPack = $item->ticketType?->isPack() ?? false;
        $eventDataApplies = $isPack
            && $item->ticketType?->eventFields() !== []
            && ($user?->hasPermission('orders.edit_event_data') ?? false);

        // Si el form NO incluyó event_data (item no-pack o sin permiso),
        // no lo consideramos editable en la ruta de slot_change.
        $eventDataCandidate = $eventDataApplies ? $rawEventData : null;

        // Sub-fase 7.2e.3 (#167): ¿cambió la cantidad o el producto? Esas
        // dimensiones tocan dinero (diff de precio) y se gestionan en el
        // handler unificado `executeItemEdit` (que absorbe también el slot +
        // event_data en UN solo guardado → un email → un audit → un refund o
        // un adjustment). Si el form no rinde estos campos (item no editable),
        // `$data` no los trae y caemos en current → sin cambio.
        $newProductId = (int) ($data['product_id'] ?? $item->ticket_type_id);
        $newQty = (int) ($data['quantity'] ?? $item->quantity);
        $productChanged = $newProductId !== (int) $item->ticket_type_id;
        $quantityChanged = $newQty !== (int) $item->quantity;

        // Sub-fase 7.2e.4 (#170): cambios de complementos (Tab 2). El diff de
        // complementos se acumula con el de Tab 1 en el MISMO `executeItemEdit`
        // (un email, un audit, movimientos financieros separados por #170).
        // ②b: la elección del Radio de cada grupo se materializa como un `add` (group-replacement).
        $data = $this->applyGroupChoices($item, $data);
        $addonEdits = $this->normalizeAddonEdits($data, $item);
        $addonsChanged = $this->itemEditor()->addonEditsPresent($item, $addonEdits);

        // ⚠️⚠️ **Mover la FECHA re-tarifica** (`DECISIONES #127(d)`). Un cambio de día cuya tarifa
        // difiere TOCA DINERO, así que tiene que entrar por el handler unificado —el que ya sabe
        // cobrar en puerta y acreditar— en vez de por `executeItemSlotChange`, que no roza el precio.
        //
        // Sin esto, comprar el día barato y pedir el cambio al sábado salía GRATIS: el descuento era
        // exactamente la diferencia de tarifa, y el previo del operador afirmaba «sin cambio de
        // precio». No era una política, era un arbitraje abierto.
        //
        // ⚠️ El LÍMITE: solo re-tarifica el cambio de FECHA. Una edición que no mueve el día conserva
        // la tarifa histórica del ítem, como siempre.
        $tariffChanged = $slotChanged && ! $productChanged
            && $this->itemEditPricing()->catalogUnitPriceFor($item, $newDate) !== null
            && $this->itemEditPricing()->catalogUnitPriceFor($item, $newDate) !== (int) $item->unit_price;

        if ($productChanged || $quantityChanged || $addonsChanged || $tariffChanged) {
            $this->executeItemEdit(
                $order, $item, $data, $newDate, $newTime, $slotChanged,
                $newProductId, $newQty, $eventDataCandidate, $addonEdits,
            );

            return;
        }

        // Ramificación (paths preexistentes, intactos):
        //  - Slot NO cambió → delegar SIEMPRE a `saveItemEventData` (ruta 7.2c).
        //    `saveItemEventData` cubre todas las semánticas y maneja:
        //      · audit blocked `not_pack` / `no_event_fields` cuando aplica;
        //      · notif "sin cambios" cuando diff vacío en pack con eventFields;
        //      · save + email cuando hay diff real.
        //    Preserva 1-a-1 el contrato establecido en 7.2c.
        //  - Slot cambió → ruta nueva 7.2e.2 con su propia defense in depth +
        //    delegación a `saveItemEventDataReturningDiffPresence` para
        //    consolidar el email cuando además hay event_data válido.
        if (! $slotChanged) {
            $this->saveItemEventData($arguments, $data);

            return;
        }

        // Slot cambió: ruta 7.2e.2 con su propio defense in depth.
        $this->executeItemSlotChange($order, $item, $data, $newDate, $newTime, $eventDataCandidate);
    }

    /**
     * Edición unificada de un item (sub-fase 7.2e.3, decisión #167): cantidad
     * + producto (+ slot + event_data + complementos si vienen en el mismo
     * guardado). La operación entera —las guardas, la transacción de aforo
     * bajo el lock de zona/día, la secuencia financiera post-commit y el
     * email— vive en `OrderItemEditor::edit()` desde la extracción 4b. Esta
     * capa traduce el desenlace: el permiso sin audit (como siempre), los
     * huérfanos con su lista para el banner, el resto por `blockEdit`, y el
     * éxito a su toast con los importes.
     *
     * @param  array{edits: array<int, array{child_id:int, quantity:int}>, adds: array<int, array{ticket_type_id:int, quantity:int}>}  $addonEdits
     */
    private function executeItemEdit(
        Order $order,
        OrderItem $item,
        array $data,
        string $newDate,
        string $newTime,
        bool $slotChanged,
        int $newProductId,
        int $newQty,
        ?array $eventDataCandidate,
        array $addonEdits = ['edits' => [], 'adds' => []],
    ): void {
        $outcome = $this->itemEditor()->edit(
            $order,
            $item,
            $newDate,
            $newTime,
            $slotChanged,
            $newProductId,
            $newQty,
            $eventDataCandidate,
            $addonEdits,
            (string) ($data['optimistic_token'] ?? ''),
            auth()->user(),
        );

        if ($outcome->isBlocked()) {
            if ($outcome->reason === 'permission_denied') {
                Notification::make()
                    ->title(__('admin.orders.manage_item.permission_denied'))
                    ->danger()
                    ->send();

                return;
            }
            if ($outcome->reason === 'orphan_addons') {
                $this->logManageItemBlocked($order, $item, 'edit', 'orphan_addons', [
                    'orphan_addon_item_ids' => $outcome->extra['orphan_addon_item_ids'],
                ]);
                $this->orphanAddonsBlockedNotification($outcome->extra['unresolved']);

                return;
            }
            $this->blockEdit($order, $item, $outcome->reason);

            return;
        }

        $this->editSuccessNotification(
            $outcome->extra['extra_due_cents'],
            $outcome->extra['reduced_cents'],
            $outcome->extra['item_edit_context'],
        );
    }

    /**
     * Helper de bloqueo del edit (sub-fase 7.2e.3): audit de rechazo +
     * notificación al operador con la razón estructurada.
     */
    private function blockEdit(Order $order, OrderItem $item, string $reason): void
    {
        $this->logManageItemBlocked($order, $item, 'edit', $reason);
        $this->manageItemBlockedNotification($reason);
    }

    /**
     * Cambio de slot del item (sub-fase 7.2e.2, decisión #159): la operación
     * entera —las cinco capas de defense in depth, la transacción bajo el
     * lock de zona/día, el audit, los datos del evento y el email— vive en
     * `OrderItemEditor::changeSlot()` desde la extracción 4b. Esta capa
     * traduce el desenlace: el rechazo a su audit + aviso (el permiso, sin
     * audit, como siempre) y el éxito a su toast.
     */
    private function executeItemSlotChange(
        Order $order,
        OrderItem $item,
        array $data,
        string $newDate,
        string $newTime,
        ?array $eventDataCandidate,
    ): void {
        $outcome = $this->itemEditor()->changeSlot(
            $order,
            $item,
            $newDate,
            $newTime,
            (string) ($data['optimistic_token'] ?? ''),
            $eventDataCandidate,
            auth()->user(),
        );

        if ($outcome->isBlocked()) {
            if ($outcome->reason === 'permission_denied') {
                Notification::make()
                    ->title(__('admin.orders.manage_item.permission_denied'))
                    ->danger()
                    ->send();

                return;
            }
            $this->logManageItemBlocked($order, $item, 'edit', $outcome->reason);
            $this->manageItemBlockedNotification($outcome->reason);

            return;
        }

        Notification::make()
            ->title(__('admin.orders.manage_item.success_slot_changed'))
            ->success()
            ->send();
    }

    /**
     * Audit log estructurado de un intento bloqueado de gestionar item
     * (sub-fase 7.2e.2). Paralelo al `logItemActionBlocked` de cancel/refund
     * pero con `actionKey = 'edit'`.
     *
     * @param  array<string,mixed>  $extra
     */
    private function logManageItemBlocked(Order $order, OrderItem $item, string $actionKey, string $reason, array $extra = []): void
    {
        AuditLogger::log(
            action: 'orders.item_'.$actionKey.'_blocked',
            target: $order,
            payload: array_merge([
                'order_code' => $order->code,
                'order_status' => $order->displayStatus(),
                'order_item_id' => $item->id,
                'ticket_type_id' => $item->ticket_type_id,
                'reason' => $reason,
            ], $extra),
        );
    }

    /**
     * Handler del save de `event_data` desde la action. Misma defense in
     * depth que el controller HTTP previo (#147) —hoy en
     * `OrderItemEventDataWriter`—, pero con Filament Notification en lugar
     * de flash session y con `mountAction` en lugar de HTTP POST.
     *
     * Capas:
     *  1. Permiso `orders.edit_event_data` (403) — aquí ANTES de resolver el
     *     ítem, como siempre; el servicio lo re-exige en el punto de ejecución.
     *  2. Ownership: el item pertenece al $this->record (404).
     *  3–final (en el servicio): pack con eventFields · optimistic lock ·
     *     sanitize + obligatorios · legacy · lockForUpdate + diff + audit.
     *  Esta capa traduce el rechazo: audit `event_data_blocked` + aviso.
     */
    private function saveItemEventData(array $arguments, array $data): void
    {
        $user = auth()->user();
        abort_unless(
            $user?->hasPermission('orders.edit_event_data') ?? false,
            403,
        );

        /** @var Order $order */
        $order = $this->record;
        $item = OrderItem::with('ticketType')->find($arguments['item'] ?? null);
        if ($item === null || $item->order_id !== $order->id) {
            $this->itemEventDataBlockedNotification('not_found');

            return;
        }

        $outcome = $this->eventDataWriter()->save(
            $order,
            $item,
            (array) ($data['event_data'] ?? []),
            (string) ($data['optimistic_token'] ?? ''),
            $user,
        );

        if ($outcome->isBlocked()) {
            if ($outcome->reason === 'permission_denied') {
                abort(403); // Inalcanzable tras la puerta de arriba; se conserva el contrato (SEC-04).
            }
            $this->eventDataWriter()->auditBlocked($order, $item, $outcome->reason, $outcome->extra);
            if ($outcome->reason === 'required_missing') {
                Notification::make()
                    ->title(__('admin.orders.item_detail.flash_required_missing', [
                        'missing' => implode(', ', $outcome->extra['missing_keys'] ?? []),
                    ]))
                    ->danger()
                    ->send();

                return;
            }
            $this->itemEventDataBlockedNotification($outcome->reason);

            return;
        }

        if (! $outcome->changed) {
            Notification::make()
                ->title(__('admin.orders.item_detail.flash_no_changes'))
                ->info()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('admin.orders.item_detail.flash_saved'))
            ->success()
            ->send();
    }

    // ─── Sub-fase 7.2e.1bis — cancelar / reembolsar item (decisión #154) ─────
    //
    // Iteración correctiva tras feedback de la clienta sobre 7.2e.1 (#153).
    // Decisiones cerradas:
    //  1. CANCELAR ITEM solo cancela. NO dispara refund automático — alineado
    //     con el patrón del Order completo de 7.2b/#139. Si procede devolución,
    //     el operador usa `Reembolsar` por separado. El email queda neutro
    //     ("si procede recibirás un correo aparte") vía `OrderItemCancelled`.
    //  2. REEMBOLSAR ITEM ya NO acepta importe libre. Modal presenta una
    //     LISTA DE CHECKBOXES con el item principal + sus complementos (los
    //     que tengan importe refundable restante). El operador marca los que
    //     quiere devolver. Cada checkbox marcado → soft-cancel + refund parcial
    //     REST de su importe completo. Atomicidad parcial: si una REST call
    //     falla en el batch, los items refundados ANTES quedan aplicados;
    //     los pendientes no se intentan (operador ve resumen con errores).
    //  3. Items cancelados SÍ permiten refund posterior. `refundItemBlockedReason`
    //     ya NO bloquea por `item_cancelled` (cambio en Order.php).
    //
    // Defense in depth conservada:
    //  - `visible()` filtra por permiso fino.
    //  - `mountUsing()` revalida ownership + bloqueo del item antes del modal.
    //  - `Hidden optimistic_token` (versión del item) — rechaza si cambió.
    //  - `Hidden expected_capacity_cents` (capacity al render) — rechaza si bajó
    //    por refund concurrente sobre otro item del mismo Order.
    //  - Handler revalida `*ItemBlockedReason()` con fresh + audit log.
    //  - Orquestador toma `lockForUpdate` + revalida capacity + mutex `inflight_refund`.

    /**
     * Action: cancelar item suelto.
     *
     * Cancelar item = soft-cancel (`cancelled_at`+`cancelled_by`) + refund REST
     * AUTOMÁTICO del importe completo del item (decisión #150 cerrada en sesión
     * 7.2e). El modal solo pide el modo (rest/manual); el importe se calcula
     * server-side como `quantity × unit_price − itemRefundedCents` (lo que queda
     * pendiente del item). `alsoCancelItem` se fuerza a `true` SIEMPRE — es la
     * semántica completa del botón 🗑️ (a diferencia de refundItem, donde el
     * Toggle es editable).
     *
     * No envía email desde el orquestador (patrón establecido en #142); el
     * caller dispara `OrderItemRefunded` POST-éxito.
     */
    /**
     * Action: cancelar item suelto. SOLO cancela — no toca Redsys (decisión #154).
     *
     * Marca el item como soft-cancelled (libera plaza automáticamente vía
     * `SlotAvailability` que excluye `cancelled_at !== null`) y dispara
     * `OrderItemCancelled` al cliente con texto NEUTRO sobre el reembolso.
     *
     * Si procede devolución, el operador la dispara por separado con el
     * botón ↩️ (que ahora acepta refund sobre items cancelados — cambio en
     * `refundItemBlockedReason`). El sub-card refleja "⚠ Pendiente de
     * reembolso" para ayudar al operador a no olvidarlo.
     */
    public function cancelItemAction(): Action
    {
        return Action::make('cancelItem')
            ->label(__('admin.orders.cancel_item.label'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->visible(fn (): bool => auth()->user()?->hasPermission('orders.cancel_item') ?? false)
            ->modalWidth(Width::TwoExtraLarge)
            ->modalHeading(__('admin.orders.cancel_item.modal_heading'))
            // Sub-fase 7.2e.1bis5 (decisión #158, punto 5 feedback): el modal
            // cancel renderiza también los complementos del producto. Cancelar
            // cascada a children (#157), el operador debe verlos para confirmar
            // con contexto completo el alcance de la cancelación.
            ->modalContent(fn (array $arguments) => view(
                'filament.orders.partials.item-summary-flat',
                ['item' => $this->resolveItem($arguments), 'showChildren' => true],
            ))
            ->modalDescription(__('admin.orders.cancel_item.modal_description'))
            ->modalSubmitActionLabel(__('admin.orders.cancel_item.submit'))
            ->mountUsing(function (array $arguments): void {
                // Capa 2: ownership + bloqueo validados ANTES de abrir el modal.
                $this->assertItemActionAllowed($arguments, mode: 'cancel');
            })
            ->fillForm(function (array $arguments): array {
                $item = $this->resolveItem($arguments);

                return [
                    'item_id' => (int) ($arguments['item'] ?? 0),
                    // Optimistic lock vs `item.updated_at` — rechaza si otro
                    // operador modificó el item (ej. lo canceló) entre el
                    // render del modal y el submit.
                    'optimistic_token' => (string) ($item?->updated_at?->getTimestamp() ?? ''),
                ];
            })
            ->schema([
                Hidden::make('item_id'),
                Hidden::make('optimistic_token'),
            ])
            ->action(function (array $data): void {
                $this->executeItemCancellation($data);
            });
    }

    /**
     * Action: reembolsar item suelto (refund parcial con SELECTOR DE CHECKBOXES).
     *
     * Sub-fase 7.2e.1bis (decisión #154) — sustituye el patrón "importe libre"
     * de 7.2e.1 por una lista de items refundables que el operador marca
     * individualmente. Diseño operativo:
     *
     *  - Para un **item simple** (sin complementos): la lista tiene 1 sola
     *    opción ("Producto X — Y €"), pre-marcada. El operador confirma con
     *    submit. Submit → refund REST del item completo + soft-cancel.
     *
     *  - Para un **pack con complementos**: la lista presenta el pack +
     *    cada complemento como filas separadas. El operador marca cuáles
     *    devolver. Si marca el pack, los complementos quedan auto-marcados
     *    (lógica server-side: "refundar el pack implica refundar también
     *    su contenido" — operativamente coherente).
     *
     *  - Items que ya tienen TODO su importe refundado quedan FUERA de la
     *    lista (sin opción de double-refund).
     *
     * ⚠️⚠️ **Cada item marcado → `executePartialRefund` con `alsoCancelItem=FALSE`: reembolsar NO
     * cancela la línea.** Es deliberado (`DECISIONES #127(c)`, owner): reembolsar y cancelar son
     * independientes para que el operador tenga flexibilidad al entenderse con el cliente en las
     * instalaciones. ⚠️ Este docblock **afirmaba lo contrario** —`alsoCancelItem=true`, «devolver =
     * ese ítem ya no se entrega»— mientras el código pasaba `false`: la conducta era correcta y el
     * texto no, que en dinero es como el siguiente lector escribe mal.
     * ▶ Y por eso el MOTIVO es obligatorio aquí: la reserva sigue viva y el dinero vuelve, así que
     * sin él el desglose no puede decirle al cliente si sigue debiendo ese importe.
     * Modo `rest`/`manual` aplica al batch entero.
     *
     * Atomicidad parcial: si la REST call falla en mitad del batch, los
     * items procesados antes quedan aplicados, los pendientes no se intentan.
     * El operador ve resumen con éxitos/fallos y puede reintentar los fallidos.
     */
    public function refundItemAction(): Action
    {
        return Action::make('refundItem')
            ->label(__('admin.orders.refund_item.label'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('warning')
            ->visible(fn (): bool => auth()->user()?->hasPermission('orders.refund_item') ?? false)
            ->modalWidth(Width::TwoExtraLarge)
            ->modalHeading(__('admin.orders.refund_item.modal_heading'))
            ->modalContent(fn (array $arguments) => view(
                'filament.orders.partials.item-summary-flat',
                ['item' => $this->resolveItem($arguments)],
            ))
            ->modalDescription(__('admin.orders.refund_item.modal_description'))
            ->modalSubmitActionLabel(__('admin.orders.refund_item.submit'))
            ->mountUsing(function (array $arguments): void {
                $this->assertItemActionAllowed($arguments, mode: 'refund');
            })
            ->fillForm(function (array $arguments): array {
                $item = $this->resolveItem($arguments);
                /** @var Order $order */
                $order = $this->record;

                // Bug fix #155bis (feedback empírico 2026-05-30): pre-marcar
                // el principal SOLO si está en `buildRefundItemsOptions` (es
                // decir, tiene importe refundable restante > 0). Si está
                // full-refunded, `buildRefundItemsOptions` lo excluye y
                // pre-marcarlo causaría error de validación de Filament
                // ("seleccionado no es válido") al submit — el CheckboxList
                // valida server-side que cada value pertenezca a `options()`.
                //
                // Cuando el operador entra al modal con el principal full-
                // refunded pero complementos vivos, la lista pre-fill queda
                // vacía y el operador marca los complementos manualmente.
                // #172: el pre-marcado debe coincidir con `buildRefundItemsOptions`
                // (que ahora excluye items que exceden la capacidad reembolsable);
                // pre-marcar una opción ausente dispara error de validación de
                // Filament (CheckboxList valida value ∈ options). Mismo cuidado
                // que el fix #155bis para el principal full-refunded.
                $mainRemainder = $item !== null ? $order->itemRefundableRemainderCents($item) : 0;
                $defaultSelection = ($mainRemainder > 0 && $mainRemainder <= $order->refundableCapacityCents())
                    ? [$item->id]
                    : [];

                // D5 (`#146`): si el pedido DEBE dinero, el importe sugerido es esa deuda — no el
                // remanente de la línea. Es el caso que este campo existe para resolver: una bajada
                // de precio (p. ej. por cambio de fecha) deja «pendiente de devolución» y el botón
                // devolvía la línea entera (medido: se debían 10,00 € y devolvía 30,00 €).
                $pendingCents = $order->financialSummary()->pendienteDevolucion();

                return [
                    'item_id' => (int) ($arguments['item'] ?? 0),
                    'optimistic_token' => (string) ($item?->updated_at?->getTimestamp() ?? ''),
                    'expected_capacity_cents' => (int) $order->refundableCapacityCents(),
                    'mode' => PaymentRefund::MODE_REST,
                    'items_to_refund' => $defaultSelection,
                    'amount_mode' => 'remainder',
                    'custom_amount' => $pendingCents > 0
                        ? number_format($pendingCents / 100, 2, '.', '')
                        : null,
                ];
            })
            ->schema(function (array $arguments): array {
                return [
                    Hidden::make('item_id'),
                    Hidden::make('optimistic_token'),
                    Hidden::make('expected_capacity_cents'),
                    Radio::make('mode')
                        ->label(__('admin.orders.refund_item.mode_label'))
                        // #225 (D7): pago en CAJA (sin gateway_order) → solo «reembolso manual».
                        ->options(fn (): array => $this->record->isRedsysRefundable()
                            ? [
                                PaymentRefund::MODE_REST => __('admin.orders.refund_item.mode_rest'),
                                PaymentRefund::MODE_MANUAL => __('admin.orders.refund_item.mode_manual'),
                            ]
                            : [PaymentRefund::MODE_MANUAL => __('admin.orders.refund_item.mode_manual')])
                        ->descriptions([
                            PaymentRefund::MODE_REST => __('admin.orders.refund_item.mode_rest_desc'),
                            PaymentRefund::MODE_MANUAL => __('admin.orders.refund_item.mode_manual_desc'),
                        ])
                        ->default(fn (): string => $this->record->isRedsysRefundable()
                            ? PaymentRefund::MODE_REST
                            : PaymentRefund::MODE_MANUAL)
                        ->required(),
                    CheckboxList::make('items_to_refund')
                        ->label(__('admin.orders.refund_item.items_label'))
                        ->helperText(__('admin.orders.refund_item.items_help'))
                        ->options($this->buildRefundItemsOptions($arguments))
                        ->required()
                        ->bulkToggleable()
                        ->columns(1),
                    // ⚠️⚠️ **CUÁNTO se devuelve** (`#146`/D5). Sin esta elección, el botón devolvía
                    // SIEMPRE el remanente entero de la línea — y tras una bajada de precio eso
                    // REGALA dinero (medido: se debían 10,00 € y devolvía 30,00 €, dejando una
                    // reserva viva de 30,00 pagada con 10,00). El dominio siempre supo devolver un
                    // importe arbitrario (`executePartialRefund`); lo que faltaba era preguntarlo.
                    Radio::make('amount_mode')
                        ->label(__('admin.orders.refund_item.amount_mode_label'))
                        ->options([
                            'remainder' => __('admin.orders.refund_item.amount_mode_remainder'),
                            'custom' => __('admin.orders.refund_item.amount_mode_custom'),
                        ])
                        ->descriptions([
                            'remainder' => __('admin.orders.refund_item.amount_mode_remainder_desc'),
                            'custom' => __('admin.orders.refund_item.amount_mode_custom_desc'),
                        ])
                        ->default('remainder')
                        ->live()
                        ->required(),
                    TextInput::make('custom_amount')
                        ->label(__('admin.orders.refund_item.custom_amount_label'))
                        ->helperText(function (): string {
                            /** @var Order $order */
                            $order = $this->record;
                            $pending = $order->financialSummary()->pendienteDevolucion();

                            return $pending > 0
                                ? __('admin.orders.refund_item.custom_amount_help_pending', [
                                    'pending' => $this->eurosFromCents($pending),
                                ])
                                : __('admin.orders.refund_item.custom_amount_help');
                        })
                        ->suffix('€')
                        ->numeric()
                        ->minValue(0.01)
                        ->step(0.01)
                        ->visible(fn (Get $get): bool => $get('amount_mode') === 'custom')
                        ->required(fn (Get $get): bool => $get('amount_mode') === 'custom'),
                    // ⚠️⚠️ **Por qué se devuelve** (`DECISIONES #127(c)`). Aquí es SIEMPRE obligatorio:
                    // este camino no cancela la reserva, así que el cliente se queda con ella y con su
                    // dinero de vuelta — y sin esta respuesta su desglose no puede decirle lo único
                    // que necesita saber, si sigue debiendo ese importe.
                    Radio::make('intent')
                        ->label(__('admin.orders.refund_item.intent_label'))
                        ->options([
                            PaymentRefund::INTENT_COMPENSATION => __('admin.orders.refund_item.intent_compensation'),
                            PaymentRefund::INTENT_PAID_IN_PERSON => __('admin.orders.refund_item.intent_paid_in_person'),
                        ])
                        ->descriptions([
                            PaymentRefund::INTENT_COMPENSATION => __('admin.orders.refund_item.intent_compensation_desc'),
                            PaymentRefund::INTENT_PAID_IN_PERSON => __('admin.orders.refund_item.intent_paid_in_person_desc'),
                        ])
                        ->required(),
                ];
            })
            ->action(function (array $data): void {
                $this->executeItemRefundBatch($data);
            });
    }

    /**
     * Label legible para una opción del CheckboxList. Formato:
     *  - Principal:  "Producto X — Y,YY €"
     *  - Complemento: "↳ Complemento Z — Y,YY €" (prefijo Unicode visual)
     */
    private function refundItemOptionLabel(OrderItem $item, bool $isAddon): string
    {
        /** @var Order $order */
        $order = $this->record;
        $name = $item->ticketType?->tr('name') ?? '—';
        $amountCents = $order->itemRefundableRemainderCents($item);
        $amount = number_format($amountCents / 100, 2, ',', '.');
        $prefix = $isAddon ? '↳ ' : '';

        return $prefix.$name.' — '.$amount.' €';
    }

    /**
     * Handler de `cancelItemAction` (sub-fase 7.2e.1bis): SOLO soft-cancel +
     * dispara `OrderItemCancelled`. No toca Redsys.
     *
     * Defense in depth (capas 3-4-5 simplificadas — no hay capa 5 financiera
     * porque no hay refund):
     *  - Capa 3: optimistic lock vs `item.updated_at`.
     *  - Capa 4: re-valida `cancelItemBlockedReason()` con fila fresca + audit
     *    log de bloqueos.
     *  - Capa 5: la cancelación va dentro de `DB::transaction` con
     *    `lockForUpdate` sobre Order Y item — serializa contra ediciones
     *    concurrentes (otro operador cancelando el mismo item).
     */
    private function executeItemCancellation(array $data): void
    {
        /** @var Order $order */
        $order = $this->record->fresh();
        $itemId = (int) ($data['item_id'] ?? 0);

        $item = OrderItem::with('ticketType')->find($itemId);
        if ($item === null) {
            $this->itemActionBlockedNotification('cancel', 'not_found');

            return;
        }

        // La operación entera —guardas, la transacción con la cascada a los complementos y el
        // audit dentro, el email— vive en `OrderItemCanceller` (extracción 4b). Aquí, el desenlace.
        $outcome = $this->itemCanceller()->cancel(
            $order,
            $item,
            (string) ($data['optimistic_token'] ?? ''),
            auth()->user(),
        );

        if ($outcome->isBlocked()) {
            $this->logItemActionBlocked($order, $item, 'cancel', $outcome->reason, $outcome->extra);
            $this->itemActionBlockedNotification('cancel', $outcome->reason);

            return;
        }

        Notification::make()
            ->title(__('admin.orders.cancel_item.success'))
            ->success()
            ->send();
    }

    /**
     * Handler de `refundItemAction` (sub-fase 7.2e.1bis): refund por
     * checkboxes. La petición se normaliza AQUÍ en lo que es de la entrega
     * —el modo (forzado a manual sin pasarela, `#225` D7, como el reembolso
     * de PEDIDO) y el motivo (`intent`, contra `PaymentRefund::intents()`)—
     * y `OrderItemRefunder` aplica las guardas y delega el dinero en
     * `Order::executePartialRefundBatch` (atomicidad PARCIAL: un fallo REST
     * aborta las pendientes, no deshace las hechas). Esta capa audita el
     * rechazo con su `extra` y renderiza el resultado.
     */
    private function executeItemRefundBatch(array $data): void
    {
        /** @var Order $order */
        $order = $this->record->fresh();
        $mode = (string) ($data['mode'] ?? PaymentRefund::MODE_REST);
        // #225 (D7): defensa server-side — en pagos sin gateway (caja) el REST no es ejecutable;
        // forzamos «manual» (record-only) aunque llegara otro valor.
        if (! $order->isRedsysRefundable()) {
            $mode = PaymentRefund::MODE_MANUAL;
        }
        $primaryItemId = (int) ($data['item_id'] ?? 0);

        $primaryItem = OrderItem::with('ticketType', 'children.ticketType')->find($primaryItemId);
        if ($primaryItem === null) {
            $this->itemActionBlockedNotification('refund', 'not_found');

            return;
        }

        // ⚠️ Este camino **NO cancela la línea**, a propósito: reembolsar y cancelar son
        // independientes (`DECISIONES #127(c)`, owner). Por eso el motivo es OBLIGATORIO aquí — la
        // reserva sigue viva y el dinero vuelve, así que sin él el desglose no puede decirle al
        // cliente si sigue debiendo ese importe.
        $intent = $data['intent'] ?? null;
        if (! in_array($intent, PaymentRefund::intents(), true)) {
            $intent = null;
        }

        $outcome = $this->itemRefunder()->refund($order, $primaryItem, new ItemRefundRequest(
            selectedIds: array_map('intval', (array) ($data['items_to_refund'] ?? [])),
            optimisticToken: (string) ($data['optimistic_token'] ?? ''),
            expectedCapacityCents: (int) ($data['expected_capacity_cents'] ?? -1),
            mode: $mode,
            intent: $intent,
            amountMode: (string) ($data['amount_mode'] ?? 'remainder'),
            customAmount: $data['custom_amount'] ?? null,
        ), auth()->user());

        if ($outcome->isBlocked()) {
            // Una selección vacía nunca dejó rastro; el resto de rechazos, sí, con su `extra`.
            if ($outcome->reason !== 'no_items_selected') {
                $this->logItemActionBlocked($order, $primaryItem, 'refund', $outcome->reason, $outcome->extra);
            }
            $this->itemActionBlockedNotification('refund', $outcome->reason);

            return;
        }

        $this->renderBatchResult($outcome->extra['batch'], $order, (string) $outcome->extra['mode']);
    }

    /**
     * Capa 2 de defense in depth: revalida que la action puede operar sobre el
     * item ANTES de renderizar el modal. Si el item no pasa los bloqueos,
     * notifica al operador y halta el mount.
     *
     * **NO loguea aquí**: Filament 5 envuelve `mountUsing` en una transacción
     * implícita que rollbackea al lanzar `Halt` (verificado empíricamente en
     * 7.2e.1) — el audit log se perdería. El log queda en capa 4 (handler).
     *
     * @param  'cancel'|'refund'  $mode
     */
    private function assertItemActionAllowed(array $arguments, string $mode): void
    {
        $item = $this->resolveItem($arguments);
        if ($item === null) {
            $this->itemActionBlockedNotification($mode, 'not_found');
            throw new Halt;
        }

        /** @var Order $order */
        $order = $this->record;
        $reason = $mode === 'cancel'
            ? $order->cancelItemBlockedReason($item)
            : $order->refundItemBlockedReason($item);

        if ($reason !== null) {
            $this->itemActionBlockedNotification($mode, $reason);
            throw new Halt;
        }
    }

    /**
     * Audit log estructurado de un intento bloqueado de cancel/refund item.
     *
     * @param  array<string,mixed>  $extra
     */
    private function logItemActionBlocked(Order $order, OrderItem $item, string $actionKey, string $reason, array $extra = []): void
    {
        AuditLogger::log(
            action: 'orders.item_'.$actionKey.'_blocked',
            target: $order,
            payload: array_merge([
                'order_code' => $order->code,
                'order_status' => $order->displayStatus(),
                'order_item_id' => $item->id,
                'reason' => $reason,
            ], $extra),
        );
    }
}
