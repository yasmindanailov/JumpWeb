<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentRefund;
use App\Models\ProductAddon;
use App\Models\Slot;
use App\Models\TicketType;
use App\Notifications\GuestFormRequest;
use App\Notifications\OrderCancelled;
use App\Notifications\OrderConfirmation;
use App\Notifications\OrderItemCancelled;
use App\Notifications\OrderItemModified;
use App\Notifications\OrderItemRefunded;
use App\Notifications\OrderPaymentDeclined;
use App\Notifications\OrderRefunded;
use App\Support\AddonResolver;
use App\Support\AuditLogger;
use App\Support\PackAvailability;
use App\Support\ParkSchedule;
use App\Support\PaymentSettings;
use App\Support\ProductAvailability;
use App\Support\RateResolver;
use App\Support\SlotAvailability;
use Carbon\CarbonPeriod;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

class ViewOrder extends ViewRecord
{
    use WithPagination;

    protected static string $resource = OrderResource::class;

    /**
     * Estado del calendario visual del modal Gestionar (sub-fase 7.2e.2bis6,
     * decisión #160). Properties Livewire públicas que el partial blade del
     * calendario lee y actualiza vía `wire:click` a los métodos del
     * componente (`calendarPrevMonth`, `calendarNextMonth`, `calendarSelectDate`,
     * `calendarSelectTime`).
     *
     *  - `$calendarItemId`        : id del OrderItem operativo (se setea en mountUsing).
     *  - `$calendarMonth`         : 'YYYY-MM' del mes visible.
     *  - `$calendarSelectedDate`  : 'YYYY-MM-DD' del día elegido.
     *  - `$calendarSelectedTime`  : 'HH:MM:SS' de la hora elegida.
     *
     * El handler `executeManageItemSave` lee `$this->calendarSelectedDate`
     * y `$this->calendarSelectedTime` en lugar de los `$data['slot_*']` del
     * form Filament — el calendario reemplaza los Selects.
     */
    public ?int $calendarItemId = null;

    public ?string $calendarMonth = null;

    public ?string $calendarSelectedDate = null;

    public ?string $calendarSelectedTime = null;

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

                DB::transaction(function () use ($record, $previousStatus): void {
                    $record->status = Order::STATUS_CANCELLED;
                    $record->save();

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
            ->modalDescription(fn (Order $record): string => __(
                $record->canBeCancelled()
                    ? 'admin.orders.actions.refund.modal_description'
                    : 'admin.orders.actions.refund.modal_description_finished_service'
            ))
            ->modalSubmitActionLabel(__('admin.orders.actions.refund.submit'))
            // El Toggle "También cancelar" SOLO se ofrece si el Order admite todavía
            // cancelación (#141). Para Orders con servicio prestado (FINISHED),
            // cancelar es semánticamente incorrecto: el Toggle se oculta vía
            // `->visible()` para no inducir error operativo. Mantenemos el schema
            // como array estable (no closure raíz) para no romper la inicialización
            // de Filament; la condicional vive en el `->visible()` del Toggle.
            ->schema([
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
                    ->default(true),
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

                $result = $record->executeFullRefund(
                    by: auth()->user(),
                    mode: $mode,
                    alsoCancel: $alsoCancelRequested,
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
     * Render del feedback cuando el orquestador devuelve `ok=false`. Distingue:
     *  - `gateway_failed` con `failure_reason=transport_error_check_portal`:
     *    el operador DEBE verificar manualmente en el portal antes de reintentar.
     *  - `gateway_failed` con `failure_reason=gateway_denied`: banco denegó; muestra
     *    el `Ds_Response` para que el operador entienda el motivo.
     *  - `inflight_refund`: ya hay un refund pending para el mismo Payment (otro click).
     *  - `no_paid_payment`: defensivo, no debería ocurrir si `canBeRefunded()=true`.
     *  - Razones de bloqueo (`already_*`, `not_paid`, etc): reutiliza el helper.
     */
    private function renderRefundFailure(array $result): void
    {
        $reason = (string) ($result['reason'] ?? 'unknown');

        if ($reason === 'gateway_failed') {
            /** @var PaymentRefund|null $refund */
            $refund = $result['refund'] ?? null;
            $isTransport = $refund?->failure_reason === PaymentRefund::FAILURE_TRANSPORT;
            $msg = $isTransport
                ? __('admin.orders.actions.refund.transport_error')
                : __('admin.orders.actions.refund.gateway_denied', [
                    'code' => $result['gateway_response_code'] ?? '—',
                ]);

            Notification::make()
                ->title(__('admin.orders.actions.refund.failed_title'))
                ->body($msg)
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if ($reason === 'inflight_refund') {
            Notification::make()
                ->title(__('admin.orders.actions.refund.inflight_title'))
                ->body(__('admin.orders.actions.refund.inflight_body'))
                ->warning()
                ->send();

            return;
        }

        if ($reason === 'no_paid_payment') {
            Notification::make()
                ->title(__('admin.orders.actions.refund.no_paid_payment'))
                ->danger()
                ->send();

            return;
        }

        // Razones de bloqueo del modelo (already_*, not_paid, etc.).
        $this->blockedNotification('refund', $reason);
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
     * Opciones del Select del modal: label legible por tipo + (cuando aplica)
     * detalles contextuales que ayudan al operador a elegir (importe del reembolso,
     * fecha de cancelación, etc.). Solo se exponen los tipos disponibles para
     * este record concreto (`availableResendEmailTypes()`).
     *
     * @return array<string, string>
     */
    private static function resendTypeOptions(Order $record): array
    {
        return collect($record->availableResendEmailTypes())
            ->mapWithKeys(fn (string $type): array => [
                $type => __('admin.orders.actions.resend.types.'.$type),
            ])
            ->all();
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

    private function blockedNotification(string $actionKey, string $reason): void
    {
        Notification::make()
            ->title(__("admin.orders.actions.{$actionKey}.blocked", [
                'reason' => __("admin.orders.actions.reasons.{$reason}"),
            ]))
            ->danger()
            ->send();
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
     * `viewItemDetailAction` entregada en 7.2c/#147ter/#148bis).
     *
     * Estructura del modal (abre directo en las tabs; la card-resumen superior
     * se eliminó en #171 por ser redundante con el Tab 1):
     *   - Tab 1 "Producto y reserva" (PRIMERA y default): selector fecha +
     *     selector hora con aforo real (`SlotAvailability` para entradas,
     *     `PackAvailability` para packs) y validación de `ParkSchedule` +
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
                    'addon_edits' => $item->children
                        ->reject(fn (OrderItem $c): bool => $c->isCancelled())
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
     * Resetea el estado Livewire del calendario al abrir el modal Gestionar
     * para un item específico (sub-fase 7.2e.2bis6, #160).
     *
     * Si el item NO es editable (canEditItem false), igualmente seteamos los
     * valores iniciales para que el calendario muestre la franja actual del
     * item como referencia visual (read-only).
     *
     * Sub-fase 7.2e.2bis9 (#163): se mantiene `public` como API estable
     * para invocación desde closures donde `$this` puede no estar bien
     * bindado al page Livewire — patrón defensivo.
     */
    public function resetCalendarStateForItem(array $arguments): void
    {
        $item = $this->resolveItem($arguments);
        if ($item === null) {
            $this->calendarItemId = null;
            $this->calendarMonth = null;
            $this->calendarSelectedDate = null;
            $this->calendarSelectedTime = null;

            return;
        }

        $slot = $item->slot;
        $today = Carbon::today();
        $slotDate = $slot?->date ? Carbon::parse($slot->date->toDateString()) : null;

        $this->calendarItemId = $item->id;
        // El mes inicial es el del slot actual si está en el rango ofrecible
        // (today..today+horizon); si está en el pasado o fuera de rango,
        // arrancamos en el mes actual para no abrir en un mes vacío.
        $horizon = $today->copy()->addMonths(PaymentSettings::purchaseHorizonMonths());
        $initialMonth = $slotDate !== null && $slotDate->gte($today) && $slotDate->lte($horizon)
            ? $slotDate
            : $today;
        $this->calendarMonth = $initialMonth->format('Y-m');
        $this->calendarSelectedDate = $slot?->date?->toDateString();
        $this->calendarSelectedTime = $slot?->start_time;
    }

    /**
     * Tabs del modal Gestionar (sub-fase 7.2e.2bis6, decisión #160):
     *  - **Tab 1 "Producto y reserva"** (siempre presente): calendario
     *    visual con grid mensual (días seleccionables con estado de
     *    disponibilidad) + lista de horas como chips. Reemplaza los Selects
     *    nativos de fecha+hora de 7.2e.2 por una experiencia más
     *    intuitiva, alineada con el flujo cliente (Tickets\Purchase).
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

    /**
     * Opciones del selector de producto: mismo `type` + misma `zone` +
     * vendibles (sub-fase 7.2e.3, #167). El producto ACTUAL se incluye
     * siempre aunque dejara de ser vendible tras la compra, para que el
     * operador pueda mantenerlo sin que el modal le obligue a cambiarlo.
     *
     * @return array<int, string>
     */
    private function sameScopeProductOptions(OrderItem $item): array
    {
        $type = $item->ticketType;
        if ($type === null || $type->zone_id === null) {
            return [];
        }

        $types = TicketType::query()
            ->where('type', $type->type)
            ->where('zone_id', $type->zone_id)
            ->where('is_sellable', true)
            ->orderBy('position')
            ->get();

        if (! $types->contains('id', $type->id)) {
            $types->push($type);
        }

        return $types
            ->sortBy('position')
            ->mapWithKeys(fn (TicketType $t): array => [$t->id => $t->tr('name')])
            ->all();
    }

    /**
     * Bloque HTML del recálculo en vivo del importe (Placeholder reactivo,
     * sub-fase 7.2e.3, #167). Lee el producto + cantidad del estado del form
     * (`Get`) y la fecha efectiva del estado Livewire del calendario; computa
     * el precio nuevo con `RateResolver` (tarifa del día) y muestra
     * actual → nuevo + el signo del diff (cobro en puerta / reembolso / sin
     * cambio).
     */
    private function priceDiffPreview(OrderItem $item, Get $get): HtmlString
    {
        $newTypeId = (int) ($get('product_id') ?: $item->ticket_type_id);
        $newQty = max(1, (int) ($get('quantity') ?: $item->quantity));
        $dateStr = $this->calendarSelectedDate ?: $item->slot?->date?->toDateString();

        $pricing = $this->computeEditPricing($item, $newTypeId, $newQty, $dateStr);
        $oldTotal = $pricing['old'];

        $rows = '<div class="text-sm" style="display:flex;gap:1.5rem;flex-wrap:wrap;">'
            .'<span><span style="opacity:.7;">'.e(__('admin.orders.manage_item.price_current')).':</span> <strong>'.$this->eurosFromCents($oldTotal).' €</strong></span>';

        if ($pricing['new'] === null) {
            $rows .= '<span style="opacity:.7;">'.e(__('admin.orders.manage_item.price_new')).': —</span></div>';

            return new HtmlString($rows);
        }

        $newTotal = (int) $pricing['new'];
        $diff = (int) $pricing['diff'];
        $rows .= '<span><span style="opacity:.7;">'.e(__('admin.orders.manage_item.price_new')).':</span> <strong>'.$this->eurosFromCents($newTotal).' €</strong></span></div>';

        if ($diff > 0) {
            $line = __('admin.orders.manage_item.price_diff_extra', ['amount' => $this->eurosFromCents($diff)]);
            $color = '#b45309'; // amber-700: se cobra en puerta
        } elseif ($diff < 0) {
            // #225 (D8): bajar = solo cancelar; NO se reembolsa automáticamente (color ámbar, no verde).
            $line = __('admin.orders.manage_item.price_diff_reduce', ['amount' => $this->eurosFromCents(-$diff)]);
            $color = '#b45309'; // amber-700: se cancela; reembolso aparte si procede
        } else {
            $line = __('admin.orders.manage_item.price_diff_none');
            $color = '#6b7280'; // gray-500
        }

        return new HtmlString($rows.'<div class="text-sm" style="margin-top:.35rem;font-weight:600;color:'.$color.';">'.e($line).'</div>');
    }

    /** Formato de céntimos a euros con separadores ES (1.234,50). */
    private function eurosFromCents(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.');
    }

    /**
     * Cómputo PURO del importe de un edit (sub-fase 7.2e.3, #167) — sin
     * efectos: compartido por `priceDiffPreview` (display reactivo) y
     * `executeItemEdit` (guardado), garantizando que lo que ve el operador y
     * lo que se cobra/reembolsa coinciden.
     *
     * Criterio de tarifa:
     *  - Producto SIN cambio → conserva el `unit_price` HISTÓRICO del item
     *    (extiende la reserva a la tarifa que pagó el cliente, sin sorpresas).
     *  - Producto CAMBIADO → tarifa de catálogo (`RateResolver`) en la fecha
     *    efectiva. Si no hay precio ese día, `new`/`diff` = null (bloqueante).
     *
     * @return array{old:int, unit:int, new:?int, diff:?int}
     */
    private function computeEditPricing(OrderItem $item, int $newTypeId, int $newQty, ?string $dateStr): array
    {
        $newQty = max(1, $newQty);
        $oldTotal = $item->chargedSubtotalCents();
        $productChanged = $newTypeId !== (int) $item->ticket_type_id;

        if (! $productChanged) {
            $unit = (int) $item->unit_price;
        } else {
            $newType = TicketType::find($newTypeId);
            $date = $dateStr !== null && $dateStr !== '' ? Carbon::parse($dateStr) : Carbon::today();
            $resolved = $newType !== null ? app(RateResolver::class)->priceCents($newType, $date) : null;
            if ($resolved === null) {
                return ['old' => $oldTotal, 'unit' => 0, 'new' => null, 'diff' => null];
            }
            $unit = (int) $resolved;
        }

        $newTotal = $unit * $newQty;

        return ['old' => $oldTotal, 'unit' => $unit, 'new' => $newTotal, 'diff' => $newTotal - $oldTotal];
    }

    /**
     * Validación PURA del destino de un edit (sub-fase 7.2e.3, #167):
     * producto (mismo tipo + misma zona + vendible, sin addons huérfanos) +
     * cantidad (rango del pack). Devuelve la razón estructurada de bloqueo o
     * `null` si pasa. Sin efectos secundarios → testeable por reflexión, igual
     * que `validateNewSlot`. La capa 3 de Filament (Select `in:options` +
     * `min/max` del TextInput) rechaza la mayoría ANTES; esto es la defensa en
     * profundidad para un cliente que manipule el form.
     */
    private function validateItemEditTarget(OrderItem $item, TicketType $newType, int $newQty): ?string
    {
        $oldType = $item->ticketType;
        $productChanged = (int) $newType->id !== (int) $item->ticket_type_id;

        if ($productChanged) {
            if (! $newType->is_sellable) {
                return 'invalid_product';
            }
            if ($oldType === null || $newType->type !== $oldType->type) {
                return 'cross_type_change_forbidden';
            }
            if ($newType->zone_id === null || (int) $newType->zone_id !== (int) $oldType->zone_id) {
                return 'cross_zone_change_forbidden_product';
            }
            if ($this->orphanAddonsForNewProduct($item, $newType) !== []) {
                return 'orphan_addons';
            }
        }

        if ($newQty < 1) {
            return 'invalid_quantity';
        }
        if ($newType->isPack()) {
            $min = (int) ($newType->min_qty ?? 1);
            $max = $newType->max_qty !== null ? (int) $newType->max_qty : null;
            if ($newQty < $min || ($max !== null && $newQty > $max)) {
                return 'pack_quantity_range';
            }
        }

        return null;
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
    private function normalizeAddonEdits(array $data): array
    {
        $edits = [];
        foreach ((array) ($data['addon_edits'] ?? []) as $row) {
            if (! is_array($row) || ! isset($row['child_id'])) {
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
     * ¿El guardado incluye algún cambio REAL de complementos? (sub-fase 7.2e.4).
     * Un edit que deja la cantidad igual NO cuenta. Usado por el router para
     * decidir si entrar al handler unificado `executeItemEdit`.
     *
     * @param  array{edits: array<int, array{child_id:int, quantity:int}>, adds: array<int, array{ticket_type_id:int, quantity:int}>}  $normalized
     */
    private function addonEditsPresent(OrderItem $item, array $normalized): bool
    {
        if (($normalized['adds'] ?? []) !== []) {
            return true;
        }
        $childById = $item->children->keyBy('id');
        foreach ($normalized['edits'] ?? [] as $edit) {
            $child = $childById->get($edit['child_id']);
            if ($child === null) {
                // Referencia desconocida (p. ej. IDOR cross-item o concurrente):
                // enrutar para que `validateAddonEdits` la bloquee con razón.
                return true;
            }
            if ($child->isCancelled()) {
                // Ya cancelado → un edit sobre él es no-op (no enrutar por esto).
                continue;
            }
            if ((int) $edit['quantity'] !== (int) $child->quantity) {
                return true;
            }
        }

        return false;
    }

    /**
     * child_ids de complementos que se QUITAN (cantidad 0) en este guardado.
     * Usado por la orphan-resolution: un complemento huérfano deja de bloquear
     * si se está quitando en el mismo guardado (sub-fase 7.2e.4, #170).
     *
     * @param  array{edits: array<int, array{child_id:int, quantity:int}>, adds: array<int, mixed>}  $addonEdits
     * @return array<int, int>
     */
    private function addonChildIdsBeingRemoved(array $addonEdits): array
    {
        $ids = [];
        foreach ($addonEdits['edits'] ?? [] as $edit) {
            if ((int) ($edit['quantity'] ?? -1) === 0) {
                $ids[] = (int) $edit['child_id'];
            }
        }

        return $ids;
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
     * Pivotes de los complementos del producto del item, indexados por ticket_type_id, para leer
     * su config (incluido/obligatorio/por-invitado/grupo) al editar (modal "Gestionar"). Misma
     * autoridad que la compra pública.
     *
     * @return array<int, ProductAddon>
     */
    private function addonPivotsFor(OrderItem $item): array
    {
        $type = $item->ticketType;
        if ($type === null) {
            return [];
        }

        $out = [];
        foreach ($type->addons()->get() as $addon) {
            $out[(int) $addon->id] = $addon->pivot;
        }

        return $out;
    }

    /**
     * Metadatos de cada complemento ACTUAL del item para aplicar las MISMAS condiciones que la web:
     * cantidad mínima (los incluidos/obligatorios no se quitan ni bajan de lo incluido), bloqueo de
     * cantidad (per-invitado/grupo: se cambian con "elige menú", no editando la cantidad) y badge.
     *
     * @return array<int, array{min:int, locked:bool, badge:?string, group:?string, free:int}>
     */
    private function childAddonMeta(OrderItem $item): array
    {
        $pivots = $this->addonPivotsFor($item);
        $isPack = $item->ticketType?->isPack() ?? false;

        $meta = [];
        foreach ($item->children as $child) {
            $pivot = $pivots[(int) $child->ticket_type_id] ?? null;
            $perGuest = $pivot?->isPerGuest() ?? false;
            $group = $pivot?->choiceGroup();
            $locked = $perGuest || $group !== null;
            $min = $locked
                ? (int) $child->quantity
                : (($pivot?->is_mandatory ?? false) ? max(1, (int) $pivot->included_quantity) : 0);
            $badge = ($pivot?->is_included ?? false) ? ($isPack ? 'included' : 'free') : null;
            // Tope superior (#225 · auditoría Fase 1 P4): un complemento INCLUIDO SIN extras
            // (`is_included && !allow_extra`) no admite unidades de pago por encima de lo incluido —
            // la compra pública lo capa en `AddonResolver::effectiveQuantity`; el edit del panel
            // debe heredar la MISMA autoridad (antes cobraba `extra_due` de unidades no vendibles).
            // null = sin tope (admite extras de pago).
            // Un PER-INVITADO no tiene «extras» que topar: su cantidad es fija (= invitados) y
            // bloqueada. Los topes (incluido-sin-extra / max_qty) SOLO aplican a cantidad FIJA. Sin
            // este guard, un Menú incluido per-invitado daba max=included_quantity(1) y la validación
            // rechazaba CUALQUIER cambio de complementos del pack con `addon_no_extra`.
            $caps = [];
            if (! $perGuest) {
                if (($pivot?->is_included ?? false) && ! ($pivot?->allow_extra ?? false)) {
                    $caps[] = max(1, (int) $pivot->included_quantity);
                }
                if (($pivot?->max_qty ?? null) !== null) { // P9: tope por complemento
                    $caps[] = (int) $pivot->max_qty;
                }
            }
            $max = $caps === [] ? null : min($caps);

            $meta[(int) $child->id] = [
                'min' => $min,
                'max' => $max,
                'locked' => $locked,
                'badge' => $badge,
                'group' => $group,
                'free' => (int) $child->free_quantity,
            ];
        }

        return $meta;
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
     * Validación PURA de los edits de complementos (sub-fase 7.2e.4, #170).
     * Reflexión-testeable como `validateItemEditTarget`. Devuelve la razón
     * estructurada de bloqueo o `null`. Reglas:
     *  - cada EDIT referencia un child ACTIVO del item (`addon_not_in_parent`);
     *  - cantidad de un edit ≥ 0 (`addon_quantity_invalid`); la reducción
     *    parcial a un valor intermedio (0 < q < actual) NO está soportada sin
     *    refund (`addon_partial_reduce_unsupported`) — quitar (0) sí;
     *  - cada ADD está en el pivote `addons()` del producto NUEVO
     *    (`addon_incompatible_with_product`), con cantidad ≥ 1
     *    (`addon_quantity_invalid`), sin duplicar un complemento ya presente
     *    ni repetirlo en la misma tanda (`addon_already_added`).
     *
     * @param  array<int, array{child_id:int, quantity:int}>  $edits
     * @param  array<int, array{ticket_type_id:int, quantity:int}>  $adds
     */
    private function validateAddonEdits(OrderItem $item, TicketType $newType, array $edits, array $adds): ?string
    {
        $childById = $item->children->keyBy('id');
        $meta = $this->childAddonMeta($item);

        foreach ($edits as $edit) {
            $child = $childById->get($edit['child_id']);
            if ($child === null || (int) $child->parent_item_id !== (int) $item->id || $child->isCancelled()) {
                return 'addon_not_in_parent';
            }
            $q = (int) $edit['quantity'];
            if ($q < 0) {
                return 'addon_quantity_invalid';
            }
            // MISMAS condiciones que la web: los incluidos/obligatorios no se quitan ni bajan de lo
            // incluido; los per-invitado/grupo están bloqueados (se cambian con "elige menú").
            $cmeta = $meta[(int) $child->id] ?? ['min' => 0, 'locked' => false];
            if (($cmeta['locked'] ?? false) && $q !== (int) $child->quantity) {
                return 'addon_locked';
            }
            if ($q < ($cmeta['min'] ?? 0)) {
                return 'addon_locked';
            }
            // Tope de un INCLUIDO sin extras (#225 · auditoría Fase 1 P4): no se cobran unidades de
            // pago por encima de lo incluido (misma autoridad que la compra pública, AddonResolver).
            if (($cmeta['max'] ?? null) !== null && $q > $cmeta['max']) {
                return 'addon_no_extra';
            }
            if ($q > 0 && $q < (int) $child->quantity) {
                return 'addon_partial_reduce_unsupported';
            }
        }

        $allowedAddonIds = array_map('intval', $newType->addons()->pluck('ticket_types.id')->all());
        $activeAddonTypeIds = $item->children
            ->reject(fn (OrderItem $c): bool => $c->isCancelled())
            ->map(fn (OrderItem $c): int => (int) $c->ticket_type_id)
            ->all();

        // Pivotes del producto nuevo para detectar conflictos de grupo entre los adds.
        $newPivots = [];
        foreach ($newType->addons()->get() as $a) {
            $newPivots[(int) $a->id] = $a->pivot;
        }

        $seen = [];
        $seenGroups = [];
        foreach ($adds as $add) {
            $typeId = (int) $add['ticket_type_id'];
            if ((int) $add['quantity'] < 1) {
                return 'addon_quantity_invalid';
            }
            if (! in_array($typeId, $allowedAddonIds, true)) {
                return 'addon_incompatible_with_product';
            }
            if (in_array($typeId, $activeAddonTypeIds, true) || in_array($typeId, $seen, true)) {
                return 'addon_already_added';
            }
            // No se pueden elegir DOS del mismo grupo a la vez (la web es radio). Añadir UN miembro
            // de un grupo que ya tiene otro presente es un CAMBIO de menú (se permite; el guardado
            // sustituye el anterior).
            $group = $newPivots[$typeId]?->choiceGroup();
            if ($group !== null) {
                if (in_array($group, $seenGroups, true)) {
                    return 'addon_group_conflict';
                }
                $seenGroups[] = $group;
            }
            $seen[] = $typeId;
        }

        // Dependencias «requiere» (data-driven): tras aplicar edits+adds, ningún complemento ACTIVO
        // puede quedar con su requisito ausente. Conjunto de tipos que QUEDARÁN activos = children no
        // quitados (edit qty != 0) ∪ los añadidos; cada dependiente exige su requisito dentro (misma
        // autoridad que la compra pública, AddonResolver). Cubre tanto «añadir el dependiente sin su
        // requisito» como «quitar el requisito dejando al dependiente huérfano».
        $removedChildIds = [];
        foreach ($edits as $edit) {
            if ((int) $edit['quantity'] === 0) {
                $removedChildIds[(int) $edit['child_id']] = true;
            }
        }
        $finalActiveTypeIds = [];
        foreach ($item->children as $child) {
            if ($child->isCancelled() || isset($removedChildIds[(int) $child->id])) {
                continue;
            }
            $finalActiveTypeIds[(int) $child->ticket_type_id] = true;
        }
        foreach ($adds as $add) {
            $finalActiveTypeIds[(int) $add['ticket_type_id']] = true;
        }
        // El GUARDADO sustituye al miembro de grupo presente cuando un add es del MISMO grupo
        // (group-replacement, ②b). Simúlalo aquí: ese miembro NO seguirá activo, así que un
        // dependiente que lo «requiere» debe disparar `addon_requires_missing` (igual que la web),
        // en vez de quedar huérfano. Sin esto, el panel divergiría de la autoridad pública.
        $addedGroups = [];
        foreach ($adds as $add) {
            $group = $newPivots[(int) $add['ticket_type_id']]?->choiceGroup();
            if ($group !== null) {
                $addedGroups[$group] = true;
            }
        }
        if ($addedGroups !== []) {
            foreach ($item->children as $child) {
                if ($child->isCancelled()) {
                    continue;
                }
                $childGroup = $newPivots[(int) $child->ticket_type_id]?->choiceGroup();
                if ($childGroup !== null && isset($addedGroups[$childGroup])) {
                    unset($finalActiveTypeIds[(int) $child->ticket_type_id]);
                }
            }
        }
        foreach (array_keys($finalActiveTypeIds) as $typeId) {
            $required = $newPivots[$typeId]?->requiresAddonId();
            if ($required !== null && ! isset($finalActiveTypeIds[$required])) {
                return 'addon_requires_missing';
            }
        }

        return null;
    }

    /**
     * Cómputo PURO del importe de los cambios de complementos (sub-fase 7.2e.4,
     * #170). Modelo financiero (decisión #170): SUBIDAS (añadir / subir
     * cantidad) → cobro en puerta; BAJADAS (quitar, cantidad 0) → SIN
     * movimiento (refund manual aparte). Compartido por el guardado y la
     * preview reactiva (lo que ve el operador == lo que se cobra).
     *
     * @param  array<int, array{child_id:int, quantity:int}>  $edits
     * @param  array<int, array{ticket_type_id:int, quantity:int}>  $adds
     * @return array{upcharge:int, changes:array<string,mixed>, add_unit_prices:array<int,int>, add_quantities:array<int,int>, add_free_quantities:array<int,int>, error:?string}
     */
    private function computeAddonPricing(OrderItem $item, TicketType $newType, array $edits, array $adds, ?string $dateStr): array
    {
        $childById = $item->children->keyBy('id');
        $upcharge = 0;
        $added = [];
        $removed = [];
        $updated = [];
        $addUnitPrices = [];
        $addQuantities = [];
        $addFreeQuantities = [];
        // Cargos por complemento, cada uno ATADO a su child (no al principal): así, al
        // cancelar un complemento, su extra_due se anula solo (OrderFinancialSummary).
        $charges = [];

        foreach ($edits as $edit) {
            $child = $childById->get($edit['child_id']);
            if ($child === null) {
                continue;
            }
            $q = (int) $edit['quantity'];
            $oldQ = (int) $child->quantity;
            $name = $child->ticketType?->tr('name') ?? ('#'.$child->id);
            if ($q === 0) {
                $removed[] = $name;
            } elseif ($q > $oldQ) {
                // Subir cantidad añade unidades de PAGO: las gratis (free_quantity) se conservan
                // intactas, así que el delta es (q − oldQ) × unit_price.
                $delta = ($q - $oldQ) * (int) $child->unit_price;
                $upcharge += $delta;
                $updated[] = ['name' => $name, 'old' => $oldQ, 'new' => $q];
                if ($delta > 0) {
                    $charges[] = [
                        'child_id' => (int) $child->id,
                        'type_id' => null,
                        'amount' => $delta,
                        'context' => ['addon_change' => ['added' => [], 'removed' => [], 'updated' => [['name' => $name, 'old' => $oldQ, 'new' => $q]]]],
                    ];
                }
            }
        }

        $date = $dateStr !== null && $dateStr !== '' ? Carbon::parse($dateStr) : Carbon::today();
        foreach ($adds as $add) {
            $typeId = (int) $add['ticket_type_id'];
            $addonType = TicketType::find($typeId);
            if ($addonType === null) {
                return ['upcharge' => 0, 'changes' => [], 'add_unit_prices' => [], 'add_quantities' => [], 'add_free_quantities' => [], 'charges' => [], 'error' => 'invalid_product'];
            }

            // Config del pivote (incluido / por-invitado / extras): MISMA autoridad que la compra
            // pública vía AddonResolver, para no cobrar lo que viene incluido (#170 + complementos
            // avanzados). Un incluido SIN precio se trata como gratis (unit 0), igual que el resolver.
            $pivot = $newType->addons()->where('ticket_types.id', $typeId)->first()?->pivot;
            $resolved = app(RateResolver::class)->priceCents($addonType, $date);
            if ($resolved === null && ! ($pivot?->is_included)) {
                return ['upcharge' => 0, 'changes' => [], 'add_unit_prices' => [], 'add_quantities' => [], 'add_free_quantities' => [], 'charges' => [], 'error' => 'addon_unavailable_on_date'];
            }
            $unit = (int) ($resolved ?? 0);

            $reqQty = (int) $add['quantity'];
            $effQty = $pivot ? AddonResolver::effectiveQuantity($pivot, $reqQty, (int) $item->quantity) : max(1, $reqQty);
            $free = $pivot ? AddonResolver::freeUnits($pivot, $effQty) : 0;

            $thisUpcharge = max(0, $effQty - $free) * $unit;
            $upcharge += $thisUpcharge;
            $addUnitPrices[$typeId] = $unit;
            $addQuantities[$typeId] = $effQty;
            $addFreeQuantities[$typeId] = $free;
            $addonName = $addonType->tr('name');
            $added[] = ['name' => $addonName, 'qty' => $effQty];
            if ($thisUpcharge > 0) {
                $charges[] = [
                    'child_id' => null,            // se resuelve al crear el child en la txn
                    'type_id' => $typeId,
                    'amount' => $thisUpcharge,
                    'context' => ['addon_change' => ['added' => [['name' => $addonName, 'qty' => $effQty]], 'removed' => [], 'updated' => []]],
                ];
            }
        }

        $changes = [];
        if ($added !== [] || $removed !== [] || $updated !== []) {
            $changes['addon_change'] = ['added' => $added, 'removed' => $removed, 'updated' => $updated];
        }

        return [
            'upcharge' => $upcharge,
            'changes' => $changes,
            'add_unit_prices' => $addUnitPrices,
            'add_quantities' => $addQuantities,
            'add_free_quantities' => $addFreeQuantities,
            'charges' => $charges,
            'error' => null,
        ];
    }

    /**
     * Computa todas las variables que el partial blade del calendario
     * necesita para renderizar (sub-fase 7.2e.2bis7, fix de bug).
     *
     * Se invoca en cada render Livewire (closure pasada a `->viewData()`)
     * para que la rejilla refleje el `calendarMonth` actualizado tras un
     * `calendarPrevMonth`/`Next`, y la lista de horas refleje el
     * `calendarSelectedDate` actualizado tras un `calendarSelectDate`.
     *
     * @return array<string, mixed>
     */
    private function buildCalendarViewData(OrderItem $item): array
    {
        /** @var Order $order */
        $order = $this->record;
        $editable = $order->canEditItem($item);
        $blockedReason = $editable ? null : $order->editItemBlockedReason($item);

        // Sub-fase 7.2e.2bis9 (#163, fix render inicial): cuando las
        // properties Livewire están null (mountUsing rebindeado al Action
        // no las persistió), derivamos los DEFAULTS visuales DIRECTAMENTE
        // del slot del item — sin mutar las properties (eso lo hace
        // ensureCalendarStateInitialized en los wire methods cuando hay
        // interacción del usuario). El render inicial muestra el mes y
        // hora correctos del item; tras un click, los wire methods toman
        // el relevo y persisten state en el snapshot.
        $today = Carbon::today();
        $horizon = $today->copy()->addMonths(PaymentSettings::purchaseHorizonMonths());
        $itemSlotDate = $item->slot?->date ? Carbon::parse($item->slot->date->toDateString()) : null;
        $defaultMonth = $itemSlotDate !== null && $itemSlotDate->gte($today) && $itemSlotDate->lte($horizon)
            ? $itemSlotDate->copy()->startOfMonth()
            : $today->copy()->startOfMonth();

        $monthCarbon = $this->calendarMonth
            ? Carbon::parse($this->calendarMonth.'-01')
            : $defaultMonth;
        $effectiveSelectedDate = $this->calendarSelectedDate ?? $item->slot?->date?->toDateString();
        $effectiveSelectedTime = $this->calendarSelectedTime ?? $item->slot?->start_time;

        return [
            'item' => $item,
            'record' => $order,
            'editable' => $editable,
            'blockedReason' => $blockedReason,
            'matrix' => $this->calendarMatrixForItemWithSelection(
                $item,
                $monthCarbon,
                $effectiveSelectedDate,
            ),
            'monthYmd' => $monthCarbon->format('Y-m'),
            'monthLabel' => $this->calendarMonthLabel($monthCarbon->format('Y-m')),
            'selectedDate' => $effectiveSelectedDate,
            'selectedTime' => $effectiveSelectedTime,
            'times' => $effectiveSelectedDate
                ? $this->calendarTimesForItemWithSelection($item, $effectiveSelectedDate, $effectiveSelectedTime)
                : [],
        ];
    }

    /**
     * Variante de `calendarMatrixForItem` que acepta el `$selectedDate`
     * explícitamente (en lugar de leerlo de `$this->calendarSelectedDate`).
     * Necesario para el render inicial cuando las properties Livewire
     * pueden estar null pero queremos marcar como "selected" el slot
     * actual del item.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function calendarMatrixForItemWithSelection(OrderItem $item, Carbon $month, ?string $selectedDate): array
    {
        $today = Carbon::today();
        $horizon = $today->copy()->addMonths(PaymentSettings::purchaseHorizonMonths());

        $start = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $end = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $selectableDates = $this->selectableDatesInRange($item, $start, $end);

        // #173: el heatmap de saturación (#164) se retiró — la clienta no lo
        // necesita; ya no se computa el mapa ni se pasa al blade.
        $currentSlotDate = $item->slot?->date?->toDateString();

        $weeks = [];
        $week = [];
        foreach (CarbonPeriod::create($start, $end) as $day) {
            /** @var Carbon $day */
            $ymd = $day->toDateString();
            $week[] = [
                'date' => $ymd,
                'day' => $day->day,
                'in_month' => $day->month === $month->month,
                'selectable' => in_array($ymd, $selectableDates, true),
                'is_current' => $ymd === $currentSlotDate,
                'is_selected' => $ymd === $selectedDate,
                'is_past' => $day->lt($today),
                'is_beyond_horizon' => $day->gt($horizon),
            ];
            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        return $weeks;
    }

    /**
     * Plazas LIBRES de una franja para MOSTRAR al operador en el slider del modal
     * Gestionar (sub-fase 7.2e.3 pulido #168):
     *  - Entradas: `SlotAvailability::availableFor`.
     *  - Packs: `PackAvailability::freeGuestSlots` (cupo de invitados restante;
     *    si no hay tope, cae a `availableGuestsFor`).
     *
     * #173 (decisión clienta): el slider es **FIDEDIGNO con la lógica REAL de
     * reservas** — muestra las plazas que un booking vería de verdad, CONTANDO la
     * huella propia del item. Un cumpleaños ocupa su ventana (montaje + fiesta +
     * limpieza) y las fiestas que solapan comparten cupo; es la MISMA lógica que
     * la compra pública. Por eso NO se excluye aquí la huella propia: la asimetría
     * con la validación (que sí la excluye para permitir crecer/recolocar el item)
     * se abordará en la fase de gestión/edición de reservas. (Se revirtió un intento
     * previo de excluirla en el display, que mostraba 60 ocultando la ocupación real.)
     */
    private function displayAvailableFor(Slot $slot, TicketType $ticketType): int
    {
        if ($ticketType->isPack()) {
            $pack = app(PackAvailability::class);

            return $pack->freeGuestSlots($slot, $ticketType) ?? $pack->availableGuestsFor($slot, $ticketType);
        }

        return app(SlotAvailability::class)->availableFor($slot, $ticketType->duration_min);
    }

    /**
     * Variante de `calendarTimesForItem` con `$selectedTime` explícito —
     * paralelo a `calendarMatrixForItemWithSelection`.
     *
     * @return array<int, array<string, mixed>>
     */
    private function calendarTimesForItemWithSelection(OrderItem $item, string $date, ?string $selectedTime): array
    {
        if ($date === '') {
            return [];
        }
        $ticketType = $item->ticketType;
        if ($ticketType === null || $ticketType->zone_id === null) {
            return [];
        }

        $dateCarbon = Carbon::parse($date);
        $isParkOpen = app(ParkSchedule::class)->isOpenOn($dateCarbon);
        $productWindow = app(ProductAvailability::class);
        $currentSlot = $item->slot;

        $slots = Slot::query()
            ->where('zone_id', $ticketType->zone_id)
            ->where('date', $date)
            ->sellableOnline()
            ->orderBy('start_time')
            ->get();

        $isPack = $ticketType->isPack();
        $seatsNeeded = (int) $item->seats;
        $entries = [];

        if ($isParkOpen) {
            foreach ($slots as $slot) {
                if (! $productWindow->allowsStart($ticketType, $dateCarbon, $slot->start_time)) {
                    continue;
                }
                // #164 + #173 (decisión clienta): el cómputo muestra las plazas
                // REALES libres CONTANDO la huella propia del item — FIDEDIGNO con
                // la lógica de reservas: un cumpleaños ocupa su ventana montaje+
                // fiesta+limpieza y las fiestas que solapan comparten cupo (misma
                // lógica que la compra pública). El slot actual SIEMPRE se incluye
                // en las opciones (mantenerlo es no-op). El excluir la huella propia
                // para crecer/recolocar se hará en la gestión de reservas futura.
                $available = $this->displayAvailableFor($slot, $ticketType);
                $isCurrentSlot = $currentSlot !== null && $currentSlot->id === $slot->id;
                if ($available < $seatsNeeded && ! $isCurrentSlot) {
                    continue;
                }
                $entries[] = [
                    'time' => $slot->start_time,
                    'display' => Str::substr($slot->start_time, 0, 5),
                    'available' => $available,
                    'is_current' => $isCurrentSlot,
                    'is_selected' => $slot->start_time === $selectedTime,
                ];
            }
        }

        if ($currentSlot !== null
            && $currentSlot->date->toDateString() === $date
            && ! collect($entries)->contains(fn (array $e) => $e['time'] === $currentSlot->start_time)
        ) {
            array_unshift($entries, [
                'time' => $currentSlot->start_time,
                'display' => Str::substr($currentSlot->start_time, 0, 5),
                'available' => $seatsNeeded,
                'is_current' => true,
                'is_selected' => $currentSlot->start_time === $selectedTime,
            ]);
        }

        return $entries;
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
        $meta = $this->childAddonMeta($item);

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
     * Bloque HTML reactivo del importe del cambio de complementos (Placeholder,
     * sub-fase 7.2e.4, #170). Reusa `computeAddonPricing` (mismo cálculo que el
     * guardado). Subidas → cobro en puerta; quitar → pendiente de reembolso.
     */
    private function addonDiffPreview(OrderItem $item, Get $get): HtmlString
    {
        // ②b: el preview reactivo refleja la elección del Radio del grupo (la materializa como un
        // `add` igual que el guardado), de modo que «lo que se muestra == lo que se cobrará».
        $data = [
            'addon_edits' => $get('addon_edits') ?? [],
            'addon_adds' => $get('addon_adds') ?? [],
        ];
        foreach ($this->itemChoiceGroups($item) as $groupKey => $group) {
            $data[self::groupFieldName($groupKey)] = $get(self::groupFieldName($groupKey));
        }
        $normalized = $this->normalizeAddonEdits($this->applyGroupChoices($item, $data));
        $type = $item->ticketType;
        if ($type === null) {
            return new HtmlString('');
        }
        $dateStr = $this->calendarSelectedDate ?: $item->slot?->date?->toDateString();
        $pricing = $this->computeAddonPricing($item, $type, $normalized['edits'], $normalized['adds'], $dateStr);

        $upcharge = (int) ($pricing['upcharge'] ?? 0);
        $hasRemoval = ! empty($pricing['changes']['addon_change']['removed']);

        if ($upcharge > 0) {
            $line = __('admin.orders.manage_item.addons_upcharge_extra', ['amount' => $this->eurosFromCents($upcharge)]);
            $color = '#b45309';
        } elseif ($hasRemoval) {
            $line = __('admin.orders.manage_item.addons_upcharge_removed');
            $color = '#15803d';
        } else {
            $line = __('admin.orders.manage_item.addons_upcharge_none');
            $color = '#6b7280';
        }

        return new HtmlString('<div class="text-sm" style="font-weight:600;color:'.$color.';">'.e($line).'</div>');
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
                    ->inputMode($type === 'number' ? 'numeric' : 'text'),
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

    /**
     * Opciones del selector de FECHA del modal Gestionar Tab 1.
     *
     * Reglas (decisión #159 + 4ª pregunta validada por la clienta — modo
     * estricto: aforo + Pack + ParkSchedule + ProductAvailability):
     *  - Solo mismo zone del item (cross-zone bloqueado, eso es cambio de
     *    producto → 7.2e.3).
     *  - Solo fechas FUTURAS (today + horas restantes del día → mañana
     *    siempre incluida; today con slots aún por venir → incluida).
     *  - `online_sales_open = true` + `status != closed` (defensa antes
     *    de pasar al cómputo de aforo).
     *  - `ParkSchedule::isOpenOn` true ese día.
     *  - Al menos UN slot del día cumple `ProductAvailability::allowsStart`
     *    + tiene aforo suficiente para `item.seats` (entradas →
     *    `SlotAvailability::availableFor`; packs → `PackAvailability::
     *    availableGuestsFor`). Sin esto, mostrar la fecha sería engañoso
     *    (el operador la elige y luego no hay horas válidas).
     *
     * Slot ACTUAL del item siempre incluido marcado "(actual)" aunque NO
     * cumpla los filtros — caso operativo: el admin cerró el zone tras
     * la compra, el operador debe poder dejar el item donde está sin que
     * el modal le obligue a moverlo. Validación al submit revalida.
     *
     * Sub-fase 7.2e.2 escoge formato humano `isoFormat('ddd D MMM YYYY')`
     * en el locale activo del panel (pregunta de UX validada por la clienta).
     *
     * @return array<string, string> Y-m-d => "Vie 12 Jun 2026"
     */
    private function availableDatesForItem(OrderItem $item): array
    {
        $ticketType = $item->ticketType;
        if ($ticketType === null || $ticketType->zone_id === null) {
            return [];
        }

        $schedule = app(ParkSchedule::class);
        $productWindow = app(ProductAvailability::class);
        $today = Carbon::today();
        // Horizonte de compra/edición (sub-fase 7.2e.2bis6, decisión #160):
        // restricción operativa del negocio aplicada a TODOS los flujos de
        // selección de fecha. Default 6 meses; configurable via Setting.
        $horizon = $today->copy()->addMonths(PaymentSettings::purchaseHorizonMonths());
        $currentSlot = $item->slot;

        // Pool inicial: slots del mismo zone, no cerrados, fecha en
        // [hoy, hoy+horizonte].
        $slots = Slot::query()
            ->where('zone_id', $ticketType->zone_id)
            ->where('date', '>=', $today->toDateString())
            ->where('date', '<=', $horizon->toDateString())
            ->sellableOnline()
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        // Agrupar por fecha y filtrar las que tienen AL MENOS un slot operable.
        $validDates = collect();
        foreach ($slots->groupBy(fn (Slot $s) => $s->date->toDateString()) as $dateStr => $daySlots) {
            $dateCarbon = Carbon::parse($dateStr);
            if (! $schedule->isOpenOn($dateCarbon)) {
                continue;
            }
            $anyOperable = $daySlots->contains(
                fn (Slot $s) => $this->slotMeetsItemRequirements($s, $item, $productWindow)
            );
            if ($anyOperable) {
                $validDates->push($dateStr);
            }
        }

        // Slot actual: incluir su fecha aunque no cumpla los filtros (operativa).
        if ($currentSlot !== null) {
            $currentDateStr = $currentSlot->date->toDateString();
            if (! $validDates->contains($currentDateStr)) {
                $validDates->prepend($currentDateStr);
            }
        }

        $currentDateStr = $currentSlot?->date?->toDateString();
        $marker = ' '.__('admin.orders.manage_item.current_marker');

        return $validDates
            ->unique()
            ->sort()
            ->mapWithKeys(function (string $dateStr) use ($currentDateStr, $marker): array {
                $label = Str::ucfirst(
                    Carbon::parse($dateStr)->locale(app()->getLocale())->isoFormat('ddd D MMM YYYY')
                );
                if ($dateStr === $currentDateStr) {
                    $label .= $marker;
                }

                return [$dateStr => $label];
            })
            ->all();
    }

    /**
     * Opciones del selector de HORA del modal Gestionar Tab 1 para la fecha
     * elegida.
     *
     * Filtros: zone match + product window + park open + aforo suficiente
     * para `item.seats`. Slot ACTUAL del item siempre incluido si la fecha
     * elegida es la suya (mismo razonamiento que `availableDatesForItem`).
     *
     * @return array<string, string> H:i:s => "HH:MM (X libres)"
     */
    private function availableTimesForItem(OrderItem $item, string $date): array
    {
        if ($date === '') {
            return [];
        }

        $ticketType = $item->ticketType;
        if ($ticketType === null || $ticketType->zone_id === null) {
            return [];
        }

        $dateCarbon = Carbon::parse($date);
        if (! app(ParkSchedule::class)->isOpenOn($dateCarbon)) {
            // Sin park abierto → solo el slot actual si la fecha coincide.
            return $this->buildCurrentSlotOnlyTimeOption($item, $date);
        }

        $productWindow = app(ProductAvailability::class);
        $currentSlot = $item->slot;

        $slots = Slot::query()
            ->where('zone_id', $ticketType->zone_id)
            ->where('date', $date)
            ->sellableOnline()
            ->orderBy('start_time')
            ->get();

        $isPack = $ticketType->isPack();
        $seatsNeeded = (int) $item->seats;
        $marker = ' '.__('admin.orders.manage_item.current_marker');

        $options = [];

        foreach ($slots as $slot) {
            if (! $productWindow->allowsStart($ticketType, $dateCarbon, $slot->start_time)) {
                continue;
            }
            $available = $this->displayAvailableFor($slot, $ticketType);

            // El cómputo cuenta el ITEM ACTUAL si vive en este slot. Para no
            // engañar al operador con "0 libres" en su propio slot, le sumamos
            // de vuelta sus plazas.
            $isCurrentSlot = $currentSlot !== null && $currentSlot->id === $slot->id;
            if ($isCurrentSlot) {
                $available += $seatsNeeded;
            }
            if ($available < $seatsNeeded && ! $isCurrentSlot) {
                continue;
            }

            $options[$slot->start_time] = $this->formatTimeOption(
                $slot->start_time,
                $available,
                $isCurrentSlot,
                $marker,
            );
        }

        // Slot actual fuera de los filtros (zone cerrado, etc.): incluirlo SIEMPRE
        // si la fecha coincide.
        if ($currentSlot !== null
            && $currentSlot->date->toDateString() === $date
            && ! array_key_exists($currentSlot->start_time, $options)
        ) {
            $options = array_merge(
                [
                    $currentSlot->start_time => $this->formatTimeOption(
                        $currentSlot->start_time,
                        $seatsNeeded,
                        true,
                        $marker,
                    ),
                ],
                $options,
            );
        }

        return $options;
    }

    /**
     * Helper para mostrar SOLO el slot actual cuando la fecha elegida es la
     * suya y el resto de validaciones bloquean cualquier opción. Evita un
     * select vacío que confundiría al operador en items con slot bloqueado.
     *
     * @return array<string, string>
     */
    private function buildCurrentSlotOnlyTimeOption(OrderItem $item, string $date): array
    {
        $current = $item->slot;
        if ($current === null || $current->date->toDateString() !== $date) {
            return [];
        }
        $marker = ' '.__('admin.orders.manage_item.current_marker');

        return [
            $current->start_time => $this->formatTimeOption(
                $current->start_time,
                (int) $item->seats,
                true,
                $marker,
            ),
        ];
    }

    private function formatTimeOption(string $startTime, int $available, bool $isCurrent, string $marker): string
    {
        // Sub-fase 7.2e.2bis6 (decisión #160): bug fix de pluralización.
        // Antes usábamos `__()` que NO procesa la regla `{0}|{1}|[2,*]` —
        // dejaba al operador el texto literal `{0}sin plazas|{1}1 plaza|...`.
        // `trans_choice` procesa el plural según el `count` recibido.
        $base = Str::substr($startTime, 0, 5)
            .' — '
            .trans_choice('admin.orders.manage_item.seats_available', $available, ['count' => $available]);

        return $isCurrent ? $base.$marker : $base;
    }

    /**
     * ¿El slot $slot cumple los requisitos del $item para ser ofrecible como
     * opción del modal Gestionar? (zone match implícito en la query; este
     * helper aplica producto+aforo).
     */
    private function slotMeetsItemRequirements(Slot $slot, OrderItem $item, ProductAvailability $productWindow): bool
    {
        $ticketType = $item->ticketType;
        if ($ticketType === null) {
            return false;
        }
        if (! $productWindow->allowsStart($ticketType, $slot->date, $slot->start_time)) {
            return false;
        }
        $seatsNeeded = (int) $item->seats;
        $available = $ticketType->isPack()
            ? app(PackAvailability::class)->availableGuestsFor($slot, $ticketType)
            : app(SlotAvailability::class)->availableFor($slot, $ticketType->duration_min);
        // Sub-fase 7.2e.2bis10 (#164): el slot ACTUAL del item siempre cumple
        // los requisitos (mantenerlo es no-op, no consume aforo nuevo). El
        // resto se valida con las plazas REALES (sin sumar las del item).
        if ($item->slot && $item->slot->id === $slot->id) {
            return true;
        }

        return $available >= $seatsNeeded;
    }

    /**
     * Resuelve un Slot concreto (zone del item + fecha + hora). Devuelve
     * null si no existe (defense in depth — el handler lo trata como
     * "selección inválida").
     */
    private function resolveSlotForItem(OrderItem $item, string $date, string $time): ?Slot
    {
        $ticketType = $item->ticketType;
        if ($ticketType === null || $ticketType->zone_id === null) {
            return null;
        }

        return Slot::query()
            ->where('zone_id', $ticketType->zone_id)
            ->where('date', $date)
            ->where('start_time', $time)
            ->first();
    }

    // ─── Calendario visual del modal Gestionar (7.2e.2bis6, #160) ─────────

    /**
     * Construye la rejilla del calendario para el mes visible: lunes a
     * domingo, 5 o 6 semanas que cubren el mes completo + days vecinos del
     * mes anterior/siguiente. Cada celda incluye metadata para que el blade
     * pueda pintar estado visual sin lógica adicional:
     *
     *  - `date`         : 'YYYY-MM-DD'.
     *  - `day`          : número del día (1-31).
     *  - `in_month`     : bool — true si el día pertenece al mes visible
     *                     (el resto son días del mes anterior/siguiente que
     *                     completan las semanas; se renderizan atenuados).
     *  - `selectable`   : bool — el día tiene al menos un slot operable.
     *  - `is_current`   : bool — es el día del slot ACTUAL del item.
     *  - `is_selected`  : bool — es el día actualmente seleccionado en el
     *                     calendario (puede coincidir con `is_current` o no).
     *  - `is_past`      : bool — el día ya pasó (se renderiza disabled).
     *  - `is_beyond_horizon` : bool — más allá de today+horizon (disabled).
     *
     * @return array<int, array<int, array<string, mixed>>> semanas × días
     */
    private function calendarMatrixForItem(OrderItem $item, Carbon $month): array
    {
        $today = Carbon::today();
        $horizon = $today->copy()->addMonths(PaymentSettings::purchaseHorizonMonths());

        // Pre-cargar todas las fechas selectables del mes (incluyendo días
        // anteriores/siguientes que aparecen en el grid).
        $start = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $end = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $selectableDates = $this->selectableDatesInRange($item, $start, $end);

        $currentSlotDate = $item->slot?->date?->toDateString();
        $selectedDate = $this->calendarSelectedDate;

        $weeks = [];
        $week = [];
        foreach (CarbonPeriod::create($start, $end) as $day) {
            /** @var Carbon $day */
            $ymd = $day->toDateString();
            $week[] = [
                'date' => $ymd,
                'day' => $day->day,
                'in_month' => $day->month === $month->month,
                'selectable' => in_array($ymd, $selectableDates, true),
                'is_current' => $ymd === $currentSlotDate,
                'is_selected' => $ymd === $selectedDate,
                'is_past' => $day->lt($today),
                'is_beyond_horizon' => $day->gt($horizon),
            ];
            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
        }

        return $weeks;
    }

    /**
     * Lista de fechas (Y-m-d) dentro de [$from, $to] que tienen al menos un
     * slot operable para el item (zone match + park open + product window
     * + aforo ≥ seats). Reusa la lógica de `availableDatesForItem` pero
     * acotada a un rango específico (el del mes visible del calendario)
     * para evitar cargar 6 meses de slots cada render.
     *
     * @return array<int, string>
     */
    private function selectableDatesInRange(OrderItem $item, Carbon $from, Carbon $to): array
    {
        $ticketType = $item->ticketType;
        if ($ticketType === null || $ticketType->zone_id === null) {
            return [];
        }

        $today = Carbon::today();
        $horizon = $today->copy()->addMonths(PaymentSettings::purchaseHorizonMonths());
        $effectiveFrom = $from->copy()->max($today);
        $effectiveTo = $to->copy()->min($horizon);

        if ($effectiveFrom->gt($effectiveTo)) {
            return [];
        }

        $schedule = app(ParkSchedule::class);
        $productWindow = app(ProductAvailability::class);

        $slots = Slot::query()
            ->where('zone_id', $ticketType->zone_id)
            ->whereBetween('date', [$effectiveFrom->toDateString(), $effectiveTo->toDateString()])
            ->sellableOnline()
            ->orderBy('date')
            ->orderBy('start_time')
            ->get();

        $valid = [];
        foreach ($slots->groupBy(fn (Slot $s) => $s->date->toDateString()) as $dateStr => $daySlots) {
            $dateCarbon = Carbon::parse($dateStr);
            if (! $schedule->isOpenOn($dateCarbon)) {
                continue;
            }
            $anyOperable = $daySlots->contains(
                fn (Slot $s) => $this->slotMeetsItemRequirements($s, $item, $productWindow)
            );
            if ($anyOperable) {
                $valid[] = $dateStr;
            }
        }

        return $valid;
    }

    /**
     * Lista de horas del día seleccionado en el calendario para el item
     * dado. Cada entrada incluye metadata para el blade (chips):
     *
     *  - `time`         : 'HH:MM:SS'.
     *  - `display`      : 'HH:MM' (lo que ve el operador).
     *  - `available`    : int — plazas libres en ese slot.
     *  - `is_current`   : bool — es la hora del slot ACTUAL del item.
     *  - `is_selected`  : bool — es la hora actualmente elegida.
     *
     * @return array<int, array<string, mixed>>
     */
    private function calendarTimesForItem(OrderItem $item, string $date): array
    {
        if ($date === '') {
            return [];
        }
        $ticketType = $item->ticketType;
        if ($ticketType === null || $ticketType->zone_id === null) {
            return [];
        }

        $dateCarbon = Carbon::parse($date);
        $isParkOpen = app(ParkSchedule::class)->isOpenOn($dateCarbon);
        $productWindow = app(ProductAvailability::class);
        $currentSlot = $item->slot;
        $selectedTime = $this->calendarSelectedTime;

        $slots = Slot::query()
            ->where('zone_id', $ticketType->zone_id)
            ->where('date', $date)
            ->sellableOnline()
            ->orderBy('start_time')
            ->get();

        $isPack = $ticketType->isPack();
        $seatsNeeded = (int) $item->seats;
        $entries = [];

        if ($isParkOpen) {
            foreach ($slots as $slot) {
                if (! $productWindow->allowsStart($ticketType, $dateCarbon, $slot->start_time)) {
                    continue;
                }
                // #164 + #173 (decisión clienta): el cómputo muestra las plazas
                // REALES libres CONTANDO la huella propia del item — FIDEDIGNO con
                // la lógica de reservas: un cumpleaños ocupa su ventana montaje+
                // fiesta+limpieza y las fiestas que solapan comparten cupo (misma
                // lógica que la compra pública). El slot actual SIEMPRE se incluye
                // en las opciones (mantenerlo es no-op). El excluir la huella propia
                // para crecer/recolocar se hará en la gestión de reservas futura.
                $available = $this->displayAvailableFor($slot, $ticketType);
                $isCurrentSlot = $currentSlot !== null && $currentSlot->id === $slot->id;
                if ($available < $seatsNeeded && ! $isCurrentSlot) {
                    continue;
                }
                $entries[] = [
                    'time' => $slot->start_time,
                    'display' => Str::substr($slot->start_time, 0, 5),
                    'available' => $available,
                    'is_current' => $isCurrentSlot,
                    'is_selected' => $slot->start_time === $selectedTime,
                ];
            }
        }

        // Slot actual siempre incluido si la fecha coincide y no estaba en
        // la lista (zone cerrado, etc.).
        if ($currentSlot !== null
            && $currentSlot->date->toDateString() === $date
            && ! collect($entries)->contains(fn (array $e) => $e['time'] === $currentSlot->start_time)
        ) {
            array_unshift($entries, [
                'time' => $currentSlot->start_time,
                'display' => Str::substr($currentSlot->start_time, 0, 5),
                'available' => $seatsNeeded,
                'is_current' => true,
                'is_selected' => $currentSlot->start_time === $selectedTime,
            ]);
        }

        return $entries;
    }

    /**
     * Etiqueta del mes visible para el header del calendario (ej. "Junio 2026").
     */
    private function calendarMonthLabel(?string $monthStr): string
    {
        if ($monthStr === null) {
            return '';
        }

        return Str::ucfirst(
            Carbon::parse($monthStr.'-01')
                ->locale(app()->getLocale())
                ->isoFormat('MMMM YYYY')
        );
    }

    // ─── Wire methods del calendario ─────────────────────────────────────

    /**
     * Lazy-initialization defensiva del estado del calendario desde el
     * Action montado (sub-fase 7.2e.2bis9, #163). Cubre el caso donde
     * `mountUsing` no se invocó (test directo) o donde las properties
     * Livewire se perdieron entre requests. Lee el `item_id` desde
     * `$this->mountedActions[0]['arguments']['item']` que Filament SÍ
     * persiste de forma fiable en el snapshot.
     */
    private function ensureCalendarStateInitialized(): void
    {
        if ($this->calendarMonth !== null) {
            return;
        }
        $itemId = $this->mountedActions[0]['arguments']['item'] ?? null;
        if (! $itemId) {
            return;
        }
        $this->resetCalendarStateForItem(['item' => $itemId]);
    }

    /**
     * Vuelve al mes que contiene el slot ACTUAL del item — atajo rápido para
     * el operador que ha navegado lejos y quiere volver a la "vista por
     * defecto" del item. Sub-fase 7.2e.2bis10 (#164).
     */
    public function calendarGoToItemMonth(): void
    {
        $this->ensureCalendarStateInitialized();
        $itemId = $this->mountedActions[0]['arguments']['item'] ?? null;
        if (! $itemId) {
            return;
        }
        $item = OrderItem::with('slot')->find($itemId);
        if ($item === null) {
            return;
        }
        $slotDate = $item->slot?->date ? Carbon::parse($item->slot->date->toDateString()) : Carbon::today();
        $today = Carbon::today();
        $horizon = $today->copy()->addMonths(PaymentSettings::purchaseHorizonMonths());
        $target = $slotDate->gte($today) && $slotDate->lte($horizon) ? $slotDate : $today;
        $this->calendarMonth = $target->format('Y-m');
    }

    /**
     * Navega al mes anterior. Respeta el límite inferior (today).
     */
    public function calendarPrevMonth(): void
    {
        $this->ensureCalendarStateInitialized();
        if ($this->calendarMonth === null) {
            return;
        }
        $month = Carbon::parse($this->calendarMonth.'-01');
        $today = Carbon::today()->startOfMonth();
        $prev = $month->copy()->subMonth()->startOfMonth();
        if ($prev->lt($today)) {
            return;
        }
        $this->calendarMonth = $prev->format('Y-m');
    }

    /**
     * Navega al mes siguiente. Respeta el límite superior (today+horizon).
     */
    public function calendarNextMonth(): void
    {
        $this->ensureCalendarStateInitialized();
        if ($this->calendarMonth === null) {
            return;
        }
        $month = Carbon::parse($this->calendarMonth.'-01');
        $horizonMonth = Carbon::today()
            ->addMonths(PaymentSettings::purchaseHorizonMonths())
            ->startOfMonth();
        $next = $month->copy()->addMonth()->startOfMonth();
        if ($next->gt($horizonMonth)) {
            return;
        }
        $this->calendarMonth = $next->format('Y-m');
    }

    /**
     * Selecciona un día del calendario. Defense in depth: el wire:click del
     * partial filtra `selectable=true`, este método revalida en backend para
     * proteger contra manipulación JS.
     */
    public function calendarSelectDate(string $date): void
    {
        $this->ensureCalendarStateInitialized();
        if ($this->calendarItemId === null) {
            return;
        }
        $item = OrderItem::with('ticketType', 'slot')->find($this->calendarItemId);
        if ($item === null) {
            return;
        }
        $today = Carbon::today();
        $horizon = $today->copy()->addMonths(PaymentSettings::purchaseHorizonMonths());

        try {
            $candidate = Carbon::parse($date);
        } catch (\Exception) {
            return;
        }
        if ($candidate->lt($today) || $candidate->gt($horizon)) {
            return;
        }

        // El día debe estar en la lista de selectables del mes actual.
        $monthStart = Carbon::parse($this->calendarMonth.'-01')->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $monthEnd = Carbon::parse($this->calendarMonth.'-01')->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $valid = $this->selectableDatesInRange($item, $monthStart, $monthEnd);
        if (! in_array($date, $valid, true)) {
            return;
        }

        $this->calendarSelectedDate = $date;
        // Reset de hora: si la nueva fecha incluye la hora actual, la
        // mantenemos; si no, la limpiamos para forzar al operador a elegir.
        $times = $this->calendarTimesForItem($item, $date);
        if (! collect($times)->contains(fn (array $e) => $e['time'] === $this->calendarSelectedTime)) {
            $this->calendarSelectedTime = null;
        }
    }

    /**
     * Selecciona una hora del calendario. Revalida que pertenezca a las
     * horas ofrecibles del día actualmente seleccionado.
     */
    public function calendarSelectTime(string $time): void
    {
        $this->ensureCalendarStateInitialized();
        if ($this->calendarItemId === null || $this->calendarSelectedDate === null) {
            return;
        }
        $item = OrderItem::with('ticketType', 'slot')->find($this->calendarItemId);
        if ($item === null) {
            return;
        }
        $times = $this->calendarTimesForItem($item, $this->calendarSelectedDate);
        if (! collect($times)->contains(fn (array $e) => $e['time'] === $time)) {
            return;
        }
        $this->calendarSelectedTime = $time;
    }

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
     * documentada (#149) implica que el staff debe poder defender al cliente
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
        $addonEdits = $this->normalizeAddonEdits($data);
        $addonsChanged = $this->addonEditsPresent($item, $addonEdits);

        if ($productChanged || $quantityChanged || $addonsChanged) {
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
     * Handler UNIFICADO de edición de un item (sub-fase 7.2e.3, decisión
     * #167): cantidad + producto (+ slot + event_data si vienen en el mismo
     * guardado). Activa la dimensión financiera real — invoca `applyExtraDue`
     * (subida → cobro en puerta, sin Redsys) o `executePartialRefund`
     * (bajada → refund REST automático, #150) según el SIGNO del diff de
     * precio. Un solo guardado → un email (`OrderItemModified`) → un audit
     * (`orders.item_edited`) → un refund O un adjustment.
     *
     * Defense in depth (paralela a 7.2e.2):
     *  1. Permiso `orders.edit_item`.
     *  2. `editItemBlockedReason` con fila fresca + audit de bloqueos.
     *  3. Optimistic lock vs `item.updated_at`.
     *  4. Validar producto nuevo (mismo tipo + misma zona + vendible, sin
     *     addons huérfanos), cantidad (rango del pack), slot (si cambió) y
     *     precio del día (`RateResolver`).
     *  5. `DB::transaction` lockForUpdate sobre slot + item, revalida aforo
     *     EXCLUYENDO la huella propia del item (`excludeItemId`) y aplica los
     *     campos atómicamente.
     *
     * Atomicidad financiera: la REST de refund no puede ir dentro de la txn
     * de aforo. La mutación de campos se confirma PRIMERO; luego el refund.
     * Si el refund REST falla, el item queda correctamente modificado y el
     * operador reintenta la devolución con la action `refundItem` ya existente
     * (recuperable; nunca se devuelve dinero por un cambio no aplicado). Las
     * subidas usan `applyExtraDue` (sin red), por lo que no tienen esa ventana.
     *
     * Sub-fase 7.2e.4 (#170): absorbe también los cambios de COMPLEMENTOS
     * (Tab 2). Modelo financiero #170: añadir/subir complemento → `applyExtraDue`
     * (cobro en puerta, MOVIMIENTO SEPARADO del diff de Tab 1 — no se netea);
     * quitar (cantidad 0) → solo `markCancelled` (SIN auto-refund; el operador
     * reembolsa aparte con `refundItem`). Complementos neutros al aforo
     * (`seats=0`/`slot_id=null`) → sin re-validación de capacidad. Los huérfanos
     * de un cambio de producto dejan de bloquear si se quitan en este guardado.
     *
     * @param  array{edits: array<int, array{child_id:int, quantity:int}>, adds: array<int, array{ticket_type_id:int, quantity:int}>}  $addonEdits
     */

    /**
     * Bloquea TODAS las franjas de la zona/día de `$slot` (auditoría Fase 1, L3), con el MISMO alcance
     * que `OrderCreator::lockSlots`. Antes las ediciones de panel bloqueaban SOLO la franja de destino:
     * dos ediciones con tramos de entrada SOLAPADOS (franjas distintas) no compartían fila bloqueada y
     * podían sobrellenar una franja intermedia común (el aforo se cuenta por tramo, #60). Bloquear la
     * zona/día entera serializa también panel↔panel (web↔panel ya serializaba porque OrderCreator
     * bloquea este mismo conjunto). `orderBy('id')` → orden estable anti-interbloqueo.
     */
    private function lockZoneDaySlots(Slot $slot): void
    {
        Slot::query()
            ->where('zone_id', $slot->zone_id)
            ->where('date', $slot->date)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

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
        $user = auth()->user();

        // Capa 1: permiso.
        if (! ($user?->hasPermission('orders.edit_item') ?? false)) {
            Notification::make()
                ->title(__('admin.orders.manage_item.permission_denied'))
                ->danger()
                ->send();

            return;
        }

        // Capa 2: bloqueo del item con fila fresca.
        $reason = $order->editItemBlockedReason($item);
        if ($reason !== null) {
            $this->blockEdit($order, $item, $reason);

            return;
        }

        // Capa 3: optimistic.
        $sentToken = (string) ($data['optimistic_token'] ?? '');
        $currentToken = (string) ($item->updated_at?->getTimestamp() ?? '');
        if ($sentToken === '' || $sentToken !== $currentToken) {
            $this->blockEdit($order, $item, 'stale_item_version');

            return;
        }

        $oldType = $item->ticketType;

        // Capa 4a: resolver el producto nuevo.
        $newType = $newProductId === (int) $item->ticket_type_id
            ? $oldType
            : TicketType::find($newProductId);
        if ($newType === null) {
            $this->blockEdit($order, $item, 'invalid_product');

            return;
        }
        $productChanged = (int) $newType->id !== (int) $item->ticket_type_id;

        // Capa 4b: validar producto + cantidad (defense in depth pura —
        // testeable por reflexión, sin notificaciones). El orphan se trata
        // aparte para listar los complementos afectados en el banner.
        $targetReason = $this->validateItemEditTarget($item, $newType, $newQty);
        if ($targetReason !== null) {
            if ($targetReason === 'orphan_addons') {
                // Sub-fase 7.2e.4 (#170): un huérfano deja de bloquear si se
                // QUITA (cantidad 0) en este mismo guardado (resolución en la
                // Tab Complementos). Solo bloquea si quedan huérfanos sin quitar.
                $orphans = $this->orphanAddonsForNewProduct($item, $newType);
                $removingIds = $this->addonChildIdsBeingRemoved($addonEdits);
                $unresolved = array_diff_key($orphans, array_flip($removingIds));
                if ($unresolved !== []) {
                    $this->logManageItemBlocked($order, $item, 'edit', 'orphan_addons', [
                        'orphan_addon_item_ids' => array_keys($unresolved),
                    ]);
                    $this->orphanAddonsBlockedNotification($unresolved);

                    return;
                }
                // Todos los huérfanos se están quitando → continuar.
            } else {
                $this->blockEdit($order, $item, $targetReason);

                return;
            }
        }

        // Capa 4c: resolver slot efectivo (cambiado o el actual).
        if ($slotChanged) {
            $effectiveSlot = $this->resolveSlotForItem($item, $newDate, $newTime);
            $validationReason = $this->validateNewSlot($item, $effectiveSlot, $newType);
            if ($validationReason !== null) {
                $this->blockEdit($order, $item, $validationReason);

                return;
            }
        } else {
            $effectiveSlot = $item->slot;
        }
        if ($effectiveSlot === null) {
            $this->blockEdit($order, $item, 'invalid_slot_selection');

            return;
        }

        // Capa 4d: precio unitario nuevo + diff (helper puro compartido con la
        // preview en vivo). `new === null` ⇒ producto cambiado sin tarifa de
        // catálogo ese día → no editamos a un importe indefinido.
        $pricing = $this->computeEditPricing($item, (int) $newType->id, $newQty, $effectiveSlot->date->toDateString());
        if ($pricing['new'] === null) {
            $this->blockEdit($order, $item, 'product_unavailable_on_date');

            return;
        }
        $newUnit = (int) $pricing['unit'];
        $newSeats = $newQty * (int) ($newType->seats_per_unit ?? 1);

        $oldSlot = $item->slot;
        $oldQty = (int) $item->quantity;
        $oldUnit = (int) $item->unit_price;
        $diff = (int) $pricing['diff'];

        // Capa 4e (7.2e.4, #170): validar + tarificar los cambios de
        // complementos contra el producto NUEVO (defense in depth pura).
        $addonReason = $this->validateAddonEdits($item, $newType, $addonEdits['edits'] ?? [], $addonEdits['adds'] ?? []);
        if ($addonReason !== null) {
            $this->blockEdit($order, $item, $addonReason);

            return;
        }
        $addonPricing = $this->computeAddonPricing(
            $item, $newType, $addonEdits['edits'] ?? [], $addonEdits['adds'] ?? [], $effectiveSlot->date->toDateString(),
        );
        if ($addonPricing['error'] !== null) {
            $this->blockEdit($order, $item, $addonPricing['error']);

            return;
        }
        $addonUpcharge = (int) $addonPricing['upcharge'];
        $addonAddUnitPrices = $addonPricing['add_unit_prices'];
        $addonAddQuantities = $addonPricing['add_quantities'] ?? [];
        $addonAddFreeQuantities = $addonPricing['add_free_quantities'] ?? [];
        $addonCharges = $addonPricing['charges'] ?? [];
        // typeId → id del child creado en la txn (para atar el extra_due del add a SU child).
        $addonAddChildIds = [];

        // Grupo de elección de cada complemento del producto (para el CAMBIO de menú: añadir un
        // miembro de un grupo sustituye al miembro presente de ese mismo grupo).
        $addonGroupByTypeId = [];
        foreach ($newType->addons()->get() as $addonOption) {
            $addonGroup = $addonOption->pivot->choiceGroup();
            if ($addonGroup !== null) {
                $addonGroupByTypeId[(int) $addonOption->id] = $addonGroup;
            }
        }

        // Cambios estructurados para el email + audit.
        $changes = [];
        if ($slotChanged) {
            $changes['slot_change'] = [
                'old' => $this->humanSlotLabel($oldSlot),
                'new' => $this->humanSlotLabel($effectiveSlot),
            ];
        }
        if ($productChanged) {
            $changes['product_change'] = [
                'old' => $oldType?->tr('name') ?? '—',
                'new' => $newType->tr('name'),
            ];
        }
        if ($newQty !== $oldQty) {
            $changes['quantity_change'] = ['old' => $oldQty, 'new' => $newQty];
        }
        if (($addonPricing['changes']['addon_change'] ?? null) !== null) {
            $changes['addon_change'] = $addonPricing['changes']['addon_change'];
        }

        // Capa 5: mutación atómica bajo lock con revalidación de aforo
        // EXCLUYENDO la huella propia del item (si no, un crecimiento en su
        // propia franja se contaría a sí mismo y se bloquearía).
        $committed = false;
        $perGuestRescales = []; // child_id => ['delta'=>cents, 'old_qty'=>n, 'new_qty'=>n, 'name'=>str] (M4)
        DB::transaction(function () use ($item, $effectiveSlot, $newType, $newQty, $oldQty, $newUnit, $newSeats, $addonEdits, $addonAddUnitPrices, $addonAddQuantities, $addonAddFreeQuantities, $addonGroupByTypeId, $user, &$committed, &$addonAddChildIds, &$perGuestRescales): void {
            $this->lockZoneDaySlots($effectiveSlot); // L3: toda la zona/día (no solo la franja destino)
            /** @var OrderItem $locked */
            $locked = OrderItem::query()->lockForUpdate()->findOrFail($item->id);
            if ($locked->isCancelled()) {
                return;
            }

            $available = $newType->isPack()
                ? app(PackAvailability::class)->availableGuestsFor($effectiveSlot, $newType, [], $locked->id)
                : app(SlotAvailability::class)->availableFor($effectiveSlot, $newType->duration_min, [], $locked->id);
            if ($available < $newSeats) {
                return;
            }

            $locked->forceFill([
                'ticket_type_id' => $newType->id,
                'quantity' => $newQty,
                'unit_price' => $newUnit,
                'seats' => $newSeats,
                'slot_id' => $effectiveSlot->id,
            ])->save();

            // Sub-fase 7.2e.4 (#170): complementos (NEUTROS al aforo) en la
            // misma txn. Subir cantidad → forceFill; quitar (0) → markCancelled
            // (sin refund, #170); añadir → nuevo child enlazado al parent.
            foreach ($addonEdits['edits'] ?? [] as $edit) {
                /** @var OrderItem|null $child */
                $child = OrderItem::query()->lockForUpdate()->find((int) $edit['child_id']);
                if ($child === null || (int) $child->parent_item_id !== (int) $locked->id || $child->isCancelled()) {
                    continue;
                }
                $q = (int) $edit['quantity'];
                if ($q === 0) {
                    $child->markCancelled($user);
                } elseif ($q > (int) $child->quantity) {
                    $child->forceFill(['quantity' => $q])->save();
                }
            }
            foreach ($addonEdits['adds'] ?? [] as $add) {
                $typeId = (int) $add['ticket_type_id'];

                // CAMBIO de menú (grupo de elección): añadir un miembro de un grupo SUSTITUYE al que
                // estuviera presente de ese mismo grupo (soft-cancel; si era de pago queda pendiente
                // de reembolso por #170, si era el incluido gratis no hay nada que devolver).
                $group = $addonGroupByTypeId[$typeId] ?? null;
                if ($group !== null) {
                    foreach ($locked->children()->whereNull('cancelled_at')->get() as $present) {
                        if (($addonGroupByTypeId[(int) $present->ticket_type_id] ?? null) === $group) {
                            $present->markCancelled($user);
                        }
                    }
                }

                $created = $locked->children()->create([
                    'order_id' => $locked->order_id,
                    'ticket_type_id' => $typeId,
                    'slot_id' => null,
                    // Cantidad efectiva + unidades gratis las computó computeAddonPricing con la
                    // config del pivote (incluido/por-invitado), no el qty crudo del operador.
                    'quantity' => (int) ($addonAddQuantities[$typeId] ?? $add['quantity']),
                    'free_quantity' => (int) ($addonAddFreeQuantities[$typeId] ?? 0),
                    'unit_price' => (int) ($addonAddUnitPrices[$typeId] ?? 0),
                    'seats' => 0,
                    'event_data' => null,
                ]);
                $addonAddChildIds[$typeId] = (int) $created->id;
            }

            // M4 (auditoría Fase 1): re-escalar los complementos PER-INVITADO al nuevo nº de invitados.
            // Su cantidad efectiva SIGUE al principal (AddonResolver::effectiveQuantity → invitados); si
            // no se re-escalan, un addon per-invitado DE PAGO INFRA-cobra al subir y SOBRE-cobra (de
            // forma invisible) al bajar. Recogemos el delta por child para canalizarlo financieramente
            // FUERA de la txn con el MISMO criterio que el principal (subida → extra_due puerta; bajada
            // → crédito de buckets + marcador para que el sobre-cobro online aflore). Los children
            // per-invitado están bloqueados en la UI → nunca llegan por `addonEdits`, sin doble manejo.
            if ($newQty !== $oldQty) {
                $pivotByAddonId = $newType->addons()->get()->keyBy('id');
                foreach ($locked->children()->whereNull('cancelled_at')->get() as $child) {
                    $pivot = $pivotByAddonId->get($child->ticket_type_id)?->pivot;
                    if ($pivot === null || ! $pivot->isPerGuest()) {
                        continue;
                    }
                    $oldChildQty = (int) $child->quantity;
                    $oldChildFree = (int) $child->free_quantity;
                    $newChildQty = AddonResolver::effectiveQuantity($pivot, 0, $newQty);
                    $newChildFree = AddonResolver::freeUnits($pivot, $newChildQty);
                    if ($newChildQty === $oldChildQty && $newChildFree === $oldChildFree) {
                        continue;
                    }
                    $oldCharged = max(0, $oldChildQty - $oldChildFree) * (int) $child->unit_price;
                    $newCharged = max(0, $newChildQty - $newChildFree) * (int) $child->unit_price;
                    $child->forceFill(['quantity' => $newChildQty, 'free_quantity' => $newChildFree])->save();
                    $perGuestRescales[(int) $child->id] = [
                        'delta' => $newCharged - $oldCharged,
                        'old_qty' => $oldChildQty,
                        'new_qty' => $newChildQty,
                        'name' => $child->ticketType?->tr('name') ?? ('#'.$child->id),
                    ];
                }
            }

            $committed = true;
        });

        if (! $committed) {
            $this->logManageItemBlocked($order, $item, 'edit', 'insufficient_capacity_at_save');
            $this->manageItemBlockedNotification('insufficient_capacity_at_save');

            return;
        }

        // Audit del edit con el desglose de `changes`.
        AuditLogger::log(
            action: 'orders.item_edited',
            target: $order,
            payload: [
                'order_code' => $order->code,
                'order_item_id' => $item->id,
                'from_ticket_type_id' => $oldType?->id,
                'to_ticket_type_id' => $newType->id,
                'from_quantity' => $oldQty,
                'to_quantity' => $newQty,
                'from_unit_price' => $oldUnit,
                'to_unit_price' => $newUnit,
                'from_slot_id' => $oldSlot?->id,
                'to_slot_id' => $effectiveSlot->id,
                'price_diff_cents' => $diff,
                'addon_upcharge_cents' => $addonUpcharge,
                'changes' => array_keys($changes),
                'addon_changes' => $addonPricing['changes']['addon_change'] ?? null,
            ],
        );

        // Lado financiero por SIGNO del diff de Tab 1.
        //  - SUBIDA (#149): el incremento se cobra en puerta (`applyExtraDue`); la señal se congela.
        //  - BAJADA (#225, D8): «bajar cantidad = SOLO cancelar». Acreditamos los DOS buckets de
        //    puerta del item (primero el `extra_due` de ediciones, luego el resto de la señal
        //    `deposit_remainder`) para que «a cobrar en el parque» refleje la reserva menor. NO se
        //    auto-reembolsa (cancelar ≠ reembolsar): si la bajada cae POR DEBAJO de lo cobrado
        //    online, el sobre-cobro aflora como «pendiente de devolución» (#198) y el operador lo
        //    reembolsa APARTE con «Reembolsar». (Evita además el fallo en pedidos manuales: sin
        //    gateway_order el refund REST fallaba siempre.)
        // Context ESTRUCTURADO (no solo claves) para el desglose "A cobrar en el parque" (#171).
        $extraDueCents = null;
        $reducedCents = null;
        $itemEditContext = array_intersect_key($changes, array_flip(['product_change', 'quantity_change']));
        if ($diff > 0) {
            $order->applyExtraDue($item->fresh(), $diff, $user, 'item_edit', ['changes' => $itemEditContext]);
            $extraDueCents = $diff;
        } elseif ($diff < 0) {
            $reduction = -$diff;
            // Crédito firmado contra los dos buckets de puerta del item (el neto nunca baja de 0):
            // primero las ediciones (extra_due), luego el resto de la señal (deposit_remainder).
            $extraCredit = max(0, min($reduction, $order->itemExtraDueCents($item->fresh())));
            if ($extraCredit > 0) {
                $order->applyGateCredit($item->fresh(), $extraCredit, $user, 'item_edit_reduction', ['changes' => $itemEditContext]);
            }
            $depositCredit = max(0, min($reduction - $extraCredit, $order->itemDepositRemainderCents($item->fresh())));
            if ($depositCredit > 0) {
                $order->applyDepositRemainderCredit($item->fresh(), $depositCredit, $user, 'item_edit_reduction', ['changes' => $itemEditContext]);
            }
            // SIN reembolso automático (D8): el remanente por debajo de lo cobrado online queda
            // como «pendiente de devolución», a reembolsar aparte. Si NO se creó ningún crédito de
            // puerta (bajada de un producto pagado íntegro online), dejamos un marcador 0 € que
            // porta el quantity_change para que itemOriginalOnlineCents reconstruya la cantidad
            // original y el sobre-cobro aflore como «pendiente de devolución» (si no, sería invisible).
            if ($extraCredit === 0 && $depositCredit === 0 && isset($itemEditContext['quantity_change'])) {
                $order->recordReductionMarker($item->fresh(), $user, ['changes' => $itemEditContext]);
            }
            $reducedCents = $reduction;
        }

        // Sub-fase 7.2e.4 (#170): el upcharge de COMPLEMENTOS es un MOVIMIENTO
        // SEPARADO (decisión #170, "no netear"): cobro en puerta independiente
        // del diff de Tab 1. Las bajadas de complementos NO mueven dinero aquí
        // (refund manual aparte vía `refundItem`). Sin red → no puede fallar.
        //
        // Cada cargo se ata a SU child (el complemento), no al principal: así, si ese
        // complemento se cancela luego (p. ej. un cambio de menú lo sustituye), su
        // extra_due se ANULA solo (OrderFinancialSummary lo excluye al estar el child
        // cancelado) → no quedan cargos fantasma ni reembolsos pendientes espurios.
        foreach ($addonCharges as $charge) {
            $amount = (int) ($charge['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $childId = $charge['child_id'] ?? ($addonAddChildIds[$charge['type_id']] ?? null);
            $childItem = $childId !== null ? OrderItem::find((int) $childId) : null;
            // Defensa: si por lo que sea no se resuelve el child, se ata al principal
            // (comportamiento previo) en vez de perder el cobro.
            $target = $childItem ?? $item->fresh();
            $order->applyExtraDue($target, $amount, $user, 'addon_edit', $charge['context'] ?? [
                'addon_change' => $addonPricing['changes']['addon_change'] ?? null,
            ]);
            $extraDueCents = ($extraDueCents ?? 0) + $amount;
        }

        // M4 (auditoría Fase 1): canalizar el delta de los complementos PER-INVITADO re-escalados, atado
        // a CADA child (#170) y con el MISMO criterio de signo que el principal. El `quantity_change` en
        // el contexto permite a `itemOriginalOnlineCents` reconstruir el sobre-cobro de una BAJADA como
        // «pendiente de devolución» (igual que el principal). Para un pack con SEÑAL, el crédito recae en
        // el `deposit_remainder` del child (puerta); para uno pagado online, en el marcador (online).
        foreach ($perGuestRescales as $childId => $r) {
            $delta = (int) $r['delta'];
            if ($delta === 0) {
                continue;
            }
            $child = OrderItem::find($childId);
            if ($child === null) {
                continue;
            }
            $ctx = ['changes' => [
                'quantity_change' => ['old' => (int) $r['old_qty'], 'new' => (int) $r['new_qty']],
                'addon_change' => ['added' => [], 'removed' => [], 'updated' => [
                    ['name' => $r['name'], 'old' => (int) $r['old_qty'], 'new' => (int) $r['new_qty']],
                ]],
            ]];

            if ($delta > 0) {
                $order->applyExtraDue($child, $delta, $user, 'addon_per_guest_rescale', $ctx);
                $extraDueCents = ($extraDueCents ?? 0) + $delta;
            } else {
                $reduction = -$delta;
                $extraCredit = max(0, min($reduction, $order->itemExtraDueCents($child)));
                if ($extraCredit > 0) {
                    $order->applyGateCredit($child, $extraCredit, $user, 'addon_per_guest_rescale_reduction', $ctx);
                }
                $depositCredit = max(0, min($reduction - $extraCredit, $order->itemDepositRemainderCents($child)));
                if ($depositCredit > 0) {
                    $order->applyDepositRemainderCredit($child, $depositCredit, $user, 'addon_per_guest_rescale_reduction', $ctx);
                }
                if ($extraCredit === 0 && $depositCredit === 0) {
                    $order->recordReductionMarker($child, $user, $ctx);
                }
            }
        }

        // event_data en el mismo guardado (tras mutar el item; reusa 7.2c).
        // El token se refresca: la mutación bumpeó `updated_at`.
        if ($eventDataCandidate !== null) {
            $fresh = $item->fresh();
            $data['optimistic_token'] = (string) ($fresh?->updated_at?->getTimestamp() ?? '');
            if ($this->saveItemEventDataReturningDiffPresence($order, $item->fresh(), $data)) {
                $changes['event_data_change'] = true;
            }
        }

        // Un solo email consolidado. Una bajada (D8) ya NO auto-reembolsa → `refundedCents` null.
        $order->notifyCustomer(new OrderItemModified(
            order: $order->fresh(),
            item: $item->fresh(),
            changes: $changes,
            extraDueCents: $extraDueCents,
            refundedCents: null,
        ));

        $this->editSuccessNotification($extraDueCents, $reducedCents);
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
     * Complementos del item que el producto NUEVO no admite (no están en su
     * pivote `product_addons`). Solo cuentan los children ACTIVOS (no
     * cancelados). Devuelve [order_item_id => nombre] para listarlos en el
     * banner (sub-fase 7.2e.3, #167).
     *
     * @return array<int, string>
     */
    private function orphanAddonsForNewProduct(OrderItem $item, TicketType $newType): array
    {
        $allowedAddonIds = $newType->addons()->pluck('ticket_types.id')->all();

        $orphans = [];
        foreach ($item->children as $child) {
            if ($child->isCancelled()) {
                continue;
            }
            if (! in_array((int) $child->ticket_type_id, array_map('intval', $allowedAddonIds), true)) {
                $orphans[(int) $child->id] = $child->ticketType?->tr('name') ?? ('#'.$child->id);
            }
        }

        return $orphans;
    }

    /**
     * Notificación de bloqueo por addons huérfanos con la lista de
     * complementos afectados (sub-fase 7.2e.3, #167).
     *
     * @param  array<int, string>  $orphans
     */
    private function orphanAddonsBlockedNotification(array $orphans): void
    {
        Notification::make()
            ->title(__('admin.orders.manage_item.orphan_addons_warning', [
                'list' => implode(', ', array_values($orphans)),
            ]))
            ->danger()
            ->persistent()
            ->send();
    }

    /**
     * Notificación de éxito del edit según el movimiento de dinero (#225, D8). Una BAJADA ya no
     * auto-reembolsa: solo cancela unidades → el mensaje avisa de que NO se ha reembolsado y de
     * que el reembolso, si procede, se hace aparte con «Reembolsar» (cancelar ≠ reembolsar).
     */
    private function editSuccessNotification(?int $extraDueCents, ?int $reducedCents): void
    {
        $hasExtra = $extraDueCents !== null && $extraDueCents > 0;
        $hasReduced = $reducedCents !== null && $reducedCents > 0;

        // Subida (cobro en puerta) + bajada (cancelación) en la misma edición: un mensaje que cita
        // el cobro y recuerda que la bajada no se reembolsó.
        if ($hasExtra && $hasReduced) {
            Notification::make()
                ->title(__('admin.orders.manage_item.success_edited_mixed_reduced', [
                    'extra' => $this->eurosFromCents($extraDueCents),
                ]))
                ->warning()
                ->send();

            return;
        }

        // Bajada (cancelación de unidades): NO se reembolsa automáticamente.
        if ($hasReduced) {
            Notification::make()
                ->title(__('admin.orders.manage_item.success_edited_reduced'))
                ->warning()
                ->send();

            return;
        }

        // Subida: cobro en puerta.
        if ($hasExtra) {
            Notification::make()
                ->title(__('admin.orders.manage_item.success_edited_extra_due', [
                    'amount' => $this->eurosFromCents($extraDueCents),
                ]))
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('admin.orders.manage_item.success_edited'))
            ->success()
            ->send();
    }

    /**
     * Ejecuta el cambio de slot del item con defense in depth 5 capas
     * (sub-fase 7.2e.2, decisión #159).
     *
     * Capas:
     *  1. Permission `orders.edit_item` (capa 1 desplazada del handler
     *     porque mountUsing no la valida — el modal puede ser viewer).
     *  2. Re-check `editItemBlockedReason` con fila fresca + audit log
     *     de bloqueos.
     *  3. Optimistic lock vs `item.updated_at`.
     *  4. Validar new_slot: existe + zone match + futuro + park open +
     *     product window OK + aforo suficiente con la SEMÁNTICA SIN
     *     CONTAR EL ITEM ACTUAL (ya está fuera de su slot conceptual).
     *  5. `DB::transaction` lockForUpdate sobre NEW slot + revalidar
     *     aforo + update `slot_id` atómico. Si en paralelo otro Order
     *     consume el aforo, el lock serializa y vemos el aforo real
     *     dentro de la txn.
     *
     * Sub-fase 7.2e.2 NO actualiza event_data en la misma txn aunque
     * `$eventDataCandidate` sea non-null — lo procesa tras la txn con
     * la lógica de 7.2c. La integración atómica multi-campo llega en
     * 7.2e.3+ cuando el handler maneje quantity/product en la misma txn.
     * Para 7.2e.2 ambos cambios disparan UN solo email consolidado.
     */
    private function executeItemSlotChange(
        Order $order,
        OrderItem $item,
        array $data,
        string $newDate,
        string $newTime,
        ?array $eventDataCandidate,
    ): void {
        $user = auth()->user();

        // Capa 1: permission.
        if (! ($user?->hasPermission('orders.edit_item') ?? false)) {
            Notification::make()
                ->title(__('admin.orders.manage_item.permission_denied'))
                ->danger()
                ->send();

            return;
        }

        // Capa 2: revalidar bloqueo del item con fila fresca.
        $reason = $order->editItemBlockedReason($item);
        if ($reason !== null) {
            $this->logManageItemBlocked($order, $item, 'edit', $reason);
            $this->manageItemBlockedNotification($reason);

            return;
        }

        // Capa 3: optimistic.
        $sentToken = (string) ($data['optimistic_token'] ?? '');
        $currentToken = (string) ($item->updated_at?->getTimestamp() ?? '');
        if ($sentToken === '' || $sentToken !== $currentToken) {
            $this->logManageItemBlocked($order, $item, 'edit', 'stale_item_version');
            $this->manageItemBlockedNotification('stale_item_version');

            return;
        }

        // Capa 4: validar el slot elegido.
        $newSlot = $this->resolveSlotForItem($item, $newDate, $newTime);
        $validationReason = $this->validateNewSlot($item, $newSlot);
        if ($validationReason !== null) {
            $this->logManageItemBlocked($order, $item, 'edit', $validationReason);
            $this->manageItemBlockedNotification($validationReason);

            return;
        }

        $oldSlot = $item->slot;
        $oldLabel = $this->humanSlotLabel($oldSlot);
        $newLabel = $this->humanSlotLabel($newSlot);

        // Capa 5: txn con lockForUpdate sobre el NEW slot + revalidate aforo.
        $committed = false;
        DB::transaction(function () use ($item, $newSlot, &$committed): void {
            // Lock sobre TODA la zona/día de destino (L3) — serializa contra compras y otras
            // ediciones de panel concurrentes que pudieran agotar plazas mientras procesamos.
            $this->lockZoneDaySlots($newSlot);
            /** @var OrderItem $locked */
            $locked = OrderItem::query()->lockForUpdate()->findOrFail($item->id);

            // Re-check defensivo: el item podría haber sido cancelado
            // entre capa 2 y este lock. `markCancelled` es terminal.
            if ($locked->isCancelled()) {
                return;
            }

            // Sub-fase 7.2e.2bis10 (#164): si el item YA está en este slot
            // (caso defensivo — el handler debería filtrar este caso antes
            // de entrar), mantenerlo es no-op y no consume aforo nuevo:
            // commit sin validación de capacity.
            if ($locked->slot_id === $newSlot->id) {
                $committed = true;

                return;
            }

            // Aforo revalidado DENTRO del lock: si entre capa 4 y aquí otro
            // Order consumió plazas, vemos el aforo REAL y abortamos si no hay
            // capacity. EXCLUYE la huella propia del item (auditoría Fase 1 · P3):
            // un pack/entrada multi-franja cuya ventana de cupo SOLAPA con la del
            // slot destino (recolocar media hora/una hora) se contaría a sí mismo
            // y se bloquearía siempre (fail-closed). Mismo `excludeItemId` que el
            // path unificado `executeItemEdit` (de ahí su divergencia previa).
            $available = $locked->ticketType?->isPack()
                ? app(PackAvailability::class)->availableGuestsFor($newSlot, $locked->ticketType, [], $locked->id)
                : app(SlotAvailability::class)->availableFor($newSlot, $locked->ticketType?->duration_min, [], $locked->id);
            if ($available < (int) $locked->seats) {
                return; // committed se queda false → fallo silencioso post-txn.
            }

            $locked->forceFill(['slot_id' => $newSlot->id])->save();
            $committed = true;
        });

        if (! $committed) {
            $this->logManageItemBlocked($order, $item, 'edit', 'insufficient_capacity_at_save');
            $this->manageItemBlockedNotification('insufficient_capacity_at_save');

            return;
        }

        // Audit log del cambio de slot.
        AuditLogger::log(
            action: 'orders.item_slot_changed',
            target: $order,
            payload: [
                'order_code' => $order->code,
                'order_item_id' => $item->id,
                'ticket_type_id' => $item->ticket_type_id,
                'from_slot_id' => $oldSlot?->id,
                'from_date' => $oldSlot?->date?->toDateString(),
                'from_time' => $oldSlot?->start_time,
                'to_slot_id' => $newSlot->id,
                'to_date' => $newDate,
                'to_time' => $newTime,
            ],
        );

        // Si el form también traía event_data y aplica al item, procesar a
        // continuación con la lógica de 7.2c (su propio audit + email).
        // Sub-fase 7.2e.2 mantiene los dos paths separados; la integración
        // unificada se hará en 7.2e.3+ junto a quantity/product.
        $eventDataEmailFlag = false;
        if ($eventDataCandidate !== null) {
            // Refrescar el optimistic_token: el save del slot bumpeó el
            // updated_at del item, el siguiente save necesita el nuevo token.
            $fresh = $item->fresh();
            $data['optimistic_token'] = (string) ($fresh?->updated_at?->getTimestamp() ?? '');
            $eventDataEmailFlag = $this->saveItemEventDataReturningDiffPresence(
                $order,
                $item->fresh(),
                $data,
            );
        }

        // Email consolidado de cambio de slot (+ event_data si aplica).
        $changes = ['slot_change' => ['old' => $oldLabel, 'new' => $newLabel]];
        if ($eventDataEmailFlag) {
            $changes['event_data_change'] = true;
        }

        $order->notifyCustomer(new OrderItemModified(
            order: $order->fresh(),
            item: $item->fresh(),
            changes: $changes,
        ));

        Notification::make()
            ->title(__('admin.orders.manage_item.success_slot_changed'))
            ->success()
            ->send();
    }

    /**
     * Valida el slot elegido en capa 4 del cambio. Devuelve la razón
     * estructurada para audit log o null si pasa.
     */
    private function validateNewSlot(OrderItem $item, ?Slot $newSlot, ?TicketType $forType = null): ?string
    {
        if ($newSlot === null) {
            return 'invalid_slot_selection';
        }
        // Sub-fase 7.2e.3 (#167): cuando se valida un cambio de producto, el
        // slot debe encajar con el producto NUEVO (zona + ventana). Por
        // defecto valida contra el producto actual del item (path 7.2e.2).
        $ticketType = $forType ?? $item->ticketType;
        if ($ticketType === null || $ticketType->zone_id !== $newSlot->zone_id) {
            return 'cross_zone_change_forbidden';
        }
        if ($newSlot->online_sales_open === false || $newSlot->status === Slot::STATUS_CLOSED) {
            // Excepción: el slot ACTUAL del item podría estar cerrado por admin;
            // mantener el mismo slot no es un "cambio" (lo cubre el no-op del
            // handler). Si llegamos aquí con cerrado, es porque el operador
            // eligió el cerrado como destino — bloqueamos.
            return 'slot_closed';
        }
        $newDateCarbon = $newSlot->date;
        if ($newDateCarbon->isPast() && ! $newDateCarbon->isToday()) {
            return 'slot_in_past';
        }
        // Capa defense in depth — horizonte de compra (sub-fase 7.2e.2bis6, #160):
        // si el slot está más allá del límite global (default 6 meses), rechazo.
        // El selector UI ya filtra esto en `availableDatesForItem`; este check
        // protege contra atacante autenticado que manipule el form.
        $horizon = Carbon::today()->addMonths(PaymentSettings::purchaseHorizonMonths());
        if ($newDateCarbon->gt($horizon)) {
            return 'beyond_horizon';
        }
        if (! app(ParkSchedule::class)->isOpenOn($newDateCarbon)) {
            return 'park_closed';
        }
        if (! app(ProductAvailability::class)->allowsStart($ticketType, $newDateCarbon, $newSlot->start_time)) {
            return 'product_window';
        }

        return null;
    }

    /**
     * Etiqueta humana de un slot para mostrar en email/audit ("dd/mm/YYYY HH:MM").
     * Devuelve "—" si el slot es null (defensivo: items sin slot).
     */
    private function humanSlotLabel(?Slot $slot): string
    {
        if ($slot === null) {
            return '—';
        }

        return $slot->date->format('d/m/Y').' '.Str::substr($slot->start_time, 0, 5);
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
     * Notification de bloqueo al operador con texto específico por razón.
     */
    private function manageItemBlockedNotification(string $reason): void
    {
        $reasonText = __('admin.orders.item_actions.reasons.'.$reason);
        if ($reasonText === 'admin.orders.item_actions.reasons.'.$reason) {
            $reasonText = $reason;
        }

        Notification::make()
            ->title(__('admin.orders.manage_item.blocked', ['reason' => $reasonText]))
            ->danger()
            ->send();
    }

    /**
     * Variante de `saveItemEventData` que devuelve si hubo diff (para
     * decidir si el email consolidado debe citar `event_data_change`).
     * Reusa la misma defense in depth pero NO envía Notification ni el
     * email de 7.2c (esos los maneja el caller con su mensaje
     * consolidado).
     */
    private function saveItemEventDataReturningDiffPresence(Order $order, OrderItem $item, array $data): bool
    {
        $user = auth()->user();
        if (! ($user?->hasPermission('orders.edit_event_data') ?? false)) {
            return false;
        }

        $ticketType = $item->ticketType;
        if ($ticketType === null || ! $ticketType->isPack()) {
            return false;
        }
        $eventFields = $ticketType->eventFields();
        if ($eventFields === []) {
            return false;
        }

        $sentToken = (string) ($data['optimistic_token'] ?? '');
        $currentToken = (string) ($item->updated_at?->getTimestamp() ?? '');
        if ($sentToken === '' || $sentToken !== $currentToken) {
            $this->logItemEventDataBlocked($order, $item, reason: 'stale_version');

            return false;
        }

        $raw = (array) ($data['event_data'] ?? []);
        $sanitized = $ticketType->sanitizeEventData($raw);
        $missing = $ticketType->missingRequiredEventFields($raw);
        if ($missing !== []) {
            $this->logItemEventDataBlocked(
                $order,
                $item,
                reason: 'required_missing',
                extra: ['missing_keys' => $missing],
            );

            return false;
        }
        $beforeData = is_array($item->event_data) ? $item->event_data : [];
        $schemaKeys = array_column($eventFields, 'key');
        $legacyPreserved = collect($beforeData)
            ->reject(fn ($v, string $k): bool => in_array($k, $schemaKeys, true))
            ->all();
        $sanitized = array_merge($legacyPreserved, $sanitized);

        $diff = null;
        DB::transaction(function () use ($item, $sanitized, &$diff): void {
            $locked = OrderItem::query()->lockForUpdate()->findOrFail($item->id);
            $current = is_array($locked->event_data) ? $locked->event_data : [];
            $diff = self::computeEventDataDiff($current, $sanitized);
            if (! self::eventDataDiffIsEmpty($diff)) {
                $locked->forceFill(['event_data' => $sanitized])->save();
            }
        });

        if (self::eventDataDiffIsEmpty($diff)) {
            return false;
        }

        AuditLogger::log(
            action: 'order_items.event_data_updated',
            target: $item->fresh(),
            payload: [
                'order_code' => $order->code,
                'ticket_type_id' => $item->ticket_type_id,
                // Auditoría Fase 1 · P2 (RGPD art.9): SOLO las CLAVES cambiadas, NUNCA los valores —
                // `event_data` lleva el nombre del homenajeado (PII de menor) y `AuditLogger::log`
                // es para acciones SIN dato personal (su contrato). El detalle vive en el pedido.
                'diff' => self::eventDataDiffKeys($diff),
            ],
        );

        return true;
    }

    /**
     * Handler del save de `event_data` desde la action. Misma defense in
     * depth que el controller HTTP previo (#147), pero con Filament
     * Notification en lugar de flash session y con `mountAction` en lugar
     * de HTTP POST.
     *
     * Capas:
     *  1. Permiso `orders.edit_event_data` (403).
     *  2. Ownership: el item pertenece al $this->record (404).
     *  3. Semántica: el item es pack con eventFields no vacío (422).
     *  4. Optimistic lock vs `updated_at` (rechazo con notificación + audit).
     *  Final: lockForUpdate + sanitize + missing required + diff + audit log.
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

        $ticketType = $item->ticketType;
        if ($ticketType === null || ! $ticketType->isPack()) {
            $this->logItemEventDataBlocked($order, $item, reason: 'not_pack');
            $this->itemEventDataBlockedNotification('not_pack');

            return;
        }

        $eventFields = $ticketType->eventFields();
        if ($eventFields === []) {
            $this->logItemEventDataBlocked($order, $item, reason: 'no_event_fields');
            $this->itemEventDataBlockedNotification('no_event_fields');

            return;
        }

        // Optimistic lock: el `updated_at` que enviamos al fillForm debe
        // coincidir con el actual; si otro operador editó entre el render del
        // modal y el submit, rechazamos sin modificar.
        $sentToken = (string) ($data['optimistic_token'] ?? '');
        $currentToken = (string) ($item->updated_at?->getTimestamp() ?? '');
        if ($sentToken === '' || $sentToken !== $currentToken) {
            $this->logItemEventDataBlocked($order, $item, reason: 'stale_version');
            $this->itemEventDataBlockedNotification('stale_version');

            return;
        }

        $raw = (array) ($data['event_data'] ?? []);
        $sanitized = $ticketType->sanitizeEventData($raw);
        $missing = $ticketType->missingRequiredEventFields($raw);

        // Preservar claves legacy (mismo razonamiento que en #147): si el pack
        // se editó tras la compra y el item tiene claves que ya no están en
        // `eventFields()` actual, el form NO las envía pero las queremos
        // conservar en lugar de borrarlas silenciosamente.
        $beforeData = is_array($item->event_data) ? $item->event_data : [];
        $schemaKeys = array_column($eventFields, 'key');
        $legacyPreserved = collect($beforeData)
            ->reject(fn ($v, string $k): bool => in_array($k, $schemaKeys, true))
            ->all();
        $sanitized = array_merge($legacyPreserved, $sanitized);

        if ($missing !== []) {
            $this->logItemEventDataBlocked(
                $order,
                $item,
                reason: 'required_missing',
                extra: ['missing_keys' => $missing],
            );
            Notification::make()
                ->title(__('admin.orders.item_detail.flash_required_missing', [
                    'missing' => implode(', ', $missing),
                ]))
                ->danger()
                ->send();

            return;
        }

        $diff = null;
        DB::transaction(function () use ($item, $sanitized, &$diff) {
            $locked = OrderItem::query()->lockForUpdate()->findOrFail($item->id);
            $current = is_array($locked->event_data) ? $locked->event_data : [];

            // Re-comparar contra el estado bloqueado, NO contra el snapshot
            // anterior — entre el optimistic_token y este lock pudo haber
            // pasado algo (improbable pero correcto a nivel teórico).
            $diff = self::computeEventDataDiff($current, $sanitized);

            if (! self::eventDataDiffIsEmpty($diff)) {
                $locked->forceFill(['event_data' => $sanitized])->save();
            }
        });

        if (self::eventDataDiffIsEmpty($diff)) {
            Notification::make()
                ->title(__('admin.orders.item_detail.flash_no_changes'))
                ->info()
                ->send();

            return;
        }

        AuditLogger::log(
            action: 'order_items.event_data_updated',
            target: $item->fresh(),
            payload: [
                'order_code' => $order->code,
                'ticket_type_id' => $item->ticket_type_id,
                // Auditoría Fase 1 · P2 (RGPD art.9): SOLO las CLAVES cambiadas, NUNCA los valores —
                // `event_data` lleva el nombre del homenajeado (PII de menor) y `AuditLogger::log`
                // es para acciones SIN dato personal (su contrato). El detalle vive en el pedido.
                'diff' => self::eventDataDiffKeys($diff),
            ],
        );

        Notification::make()
            ->title(__('admin.orders.item_detail.flash_saved'))
            ->success()
            ->send();
    }

    /**
     * @param  array<string,scalar>  $before
     * @param  array<string,scalar>  $after
     * @return array{changed:array<string,array{0:string,1:string}>,added:array<string,string>,removed:array<string,string>}
     */
    private static function computeEventDataDiff(array $before, array $after): array
    {
        $changed = [];
        $added = [];
        $removed = [];

        foreach ($after as $key => $newValue) {
            $newStr = (string) $newValue;
            if (! array_key_exists($key, $before)) {
                $added[$key] = $newStr;

                continue;
            }
            $oldStr = (string) $before[$key];
            if ($oldStr !== $newStr) {
                $changed[$key] = [$oldStr, $newStr];
            }
        }

        foreach ($before as $key => $oldValue) {
            if (! array_key_exists($key, $after)) {
                $removed[$key] = (string) $oldValue;
            }
        }

        return [
            'changed' => $changed,
            'added' => $added,
            'removed' => $removed,
        ];
    }

    /**
     * Proyección del diff a SOLO las CLAVES cambiadas (auditoría Fase 1 · P2): el audit log de
     * `event_data_updated` registra QUÉ campos cambió el operador, NUNCA sus valores — `event_data`
     * porta el nombre del homenajeado (PII de menor, art. 9) y `AuditLogger::log` es para acciones
     * sin dato personal; además `User::anonymize()` no podría purgar PII enterrada en el payload.
     *
     * @param  array{changed:array<string,mixed>,added:array<string,mixed>,removed:array<string,mixed>}  $diff
     * @return array{changed:list<string>,added:list<string>,removed:list<string>}
     */
    private static function eventDataDiffKeys(array $diff): array
    {
        return [
            'changed' => array_keys($diff['changed'] ?? []),
            'added' => array_keys($diff['added'] ?? []),
            'removed' => array_keys($diff['removed'] ?? []),
        ];
    }

    /**
     * @param  array{changed:array<string,mixed>,added:array<string,mixed>,removed:array<string,mixed>}|null  $diff
     */
    private static function eventDataDiffIsEmpty(?array $diff): bool
    {
        if ($diff === null) {
            return true;
        }

        return $diff['changed'] === []
            && $diff['added'] === []
            && $diff['removed'] === [];
    }

    /**
     * @param  array<string,mixed>  $extra
     */
    private function logItemEventDataBlocked(Order $order, OrderItem $item, string $reason, array $extra = []): void
    {
        AuditLogger::log(
            action: 'order_items.event_data_blocked',
            target: $item,
            payload: array_merge([
                'order_code' => $order->code,
                'order_status' => $order->displayStatus(),
                'ticket_type_id' => $item->ticket_type_id,
                'reason' => $reason,
            ], $extra),
        );
    }

    private function itemEventDataBlockedNotification(string $reason): void
    {
        $key = match ($reason) {
            'stale_version' => 'admin.orders.item_detail.flash_stale',
            default => 'admin.orders.item_detail.flash_blocked.'.$reason,
        };

        $title = __($key);
        // Fallback si la clave específica no existe.
        if ($title === $key) {
            $title = __('admin.orders.item_detail.flash_blocked.generic');
        }

        Notification::make()
            ->title($title)
            ->danger()
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
     * Cada item marcado → `executePartialRefund` con `alsoCancelItem=true`
     * (devolver = el operador entiende que ese item ya no se entrega).
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

                return [
                    'item_id' => (int) ($arguments['item'] ?? 0),
                    'optimistic_token' => (string) ($item?->updated_at?->getTimestamp() ?? ''),
                    'expected_capacity_cents' => (int) $order->refundableCapacityCents(),
                    'mode' => PaymentRefund::MODE_REST,
                    'items_to_refund' => $defaultSelection,
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
                ];
            })
            ->action(function (array $data): void {
                $this->executeItemRefundBatch($data);
            });
    }

    /**
     * Construye las opciones del CheckboxList del modal Reembolsar.
     *
     * Incluye el item principal + sus complementos (children) que aún tengan
     * importe refundable restante. Items completamente refundados (sea el
     * principal o un complemento) NO aparecen — no hay nada que devolver y
     * mostrarlos confundiría al operador.
     *
     * Labels: "Producto X — Y,YY €" para el principal; "  ↳ Complemento Z —
     * Y,YY €" para children (indentado visual mínimo dentro del label, dado
     * que CheckboxList renderiza items planos).
     *
     * @return array<int, string> [item_id => label]
     */
    private function buildRefundItemsOptions(array $arguments): array
    {
        $item = $this->resolveItem($arguments);
        if ($item === null) {
            return [];
        }
        /** @var Order $order */
        $order = $this->record;
        $options = [];

        // #172: solo se ofrecen items cuyo importe refundable NO supere la
        // capacidad reembolsable restante del pedido. Un item que la excede
        // (p. ej. un pack que creció con cargos de puerta `extra_due` no
        // cobrados online) NO puede reembolsarse entero → no debe poder
        // seleccionarse (antes se marcaba y saltaba `exceeds_refundable_capacity`
        // al enviar). La validación al submit se mantiene como red de seguridad.
        $capacity = $order->refundableCapacityCents();
        $isOfferable = function (OrderItem $i) use ($order, $capacity): bool {
            $remainder = $order->itemRefundableRemainderCents($i);

            return $remainder > 0 && $remainder <= $capacity;
        };

        // Item principal.
        if ($isOfferable($item)) {
            $options[$item->id] = $this->refundItemOptionLabel($item, isAddon: false);
        }

        // Children (addons) del pack.
        foreach ($item->children as $child) {
            if ($isOfferable($child)) {
                $options[$child->id] = $this->refundItemOptionLabel($child, isAddon: true);
            }
        }

        return $options;
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
        $user = auth()->user();

        $item = OrderItem::with('ticketType')->find($itemId);
        if ($item === null) {
            $this->itemActionBlockedNotification('cancel', 'not_found');

            return;
        }

        // Capa 4: revalidar bloqueos con fila fresca.
        $reason = $order->cancelItemBlockedReason($item);
        if ($reason !== null) {
            $this->logItemActionBlocked($order, $item, 'cancel', $reason);
            $this->itemActionBlockedNotification('cancel', $reason);

            return;
        }

        // Capa 3: optimistic lock.
        $sentToken = (string) ($data['optimistic_token'] ?? '');
        $currentToken = (string) ($item->updated_at?->getTimestamp() ?? '');
        if ($sentToken === '' || $sentToken !== $currentToken) {
            $this->logItemActionBlocked($order, $item, 'cancel', 'stale_item_version');
            $this->itemActionBlockedNotification('cancel', 'stale_item_version');

            return;
        }

        // Capa 5: cancelación atómica + audit. NO Redsys.
        // Sub-fase 7.2e.1bis4 (decisión #157): cancela también los CHILDREN
        // en cascada. Cancelar el producto principal implica que sus
        // complementos van con él (operativamente: no tiene sentido
        // "cancelar la entrada de cumple pero mantener la tarta"). La
        // gestión per-complemento individual vendrá en 7.2e.4 (Tab
        // Complementos del modal Gestionar).
        $previousStatus = $item->displayStatusForCustomer();
        $cascadedChildIds = [];
        DB::transaction(function () use ($item, $user, $order, $previousStatus, &$cascadedChildIds): void {
            // lockForUpdate sobre Order Y item — serializa contra ediciones
            // concurrentes (otro operador cancelando el mismo item).
            Order::query()->lockForUpdate()->find($order->id);
            /** @var OrderItem $itemLocked */
            $itemLocked = OrderItem::query()->with('children')->lockForUpdate()->findOrFail($item->id);

            // Re-check dentro del lock: el bloqueo de capa 4 pasó con fresh,
            // pero entre el fresh y el lock alguien podría haber cancelado
            // el item. `markCancelled` es idempotente (preserva timestamp
            // original) — no rompe, pero evitamos audit duplicado.
            if ($itemLocked->isCancelled()) {
                return;
            }
            $itemLocked->markCancelled($user);

            // Cascada a children: cada complemento vivo se marca cancelled
            // por el mismo user. Los ya cancelled (caso teórico — alguien
            // los canceló antes individualmente) NO se tocan (idempotente).
            foreach ($itemLocked->children as $child) {
                if (! $child->isCancelled()) {
                    /** @var OrderItem $childLocked */
                    $childLocked = OrderItem::query()->lockForUpdate()->findOrFail($child->id);
                    if (! $childLocked->isCancelled()) {
                        $childLocked->markCancelled($user);
                        $cascadedChildIds[] = $childLocked->id;
                    }
                }
            }

            AuditLogger::log(
                action: 'orders.item_cancelled',
                target: $order,
                payload: [
                    'order_code' => $order->code,
                    'order_item_id' => $itemLocked->id,
                    'ticket_type_id' => $itemLocked->ticket_type_id,
                    'previous_status' => $previousStatus,
                    'cascaded_child_ids' => $cascadedChildIds,
                ],
            );
        });

        // Post-commit: notify al cliente. Pasamos contador de complementos
        // cascaded para que el email los mencione explícitamente si los hay.
        $order->notifyCustomer(new OrderItemCancelled(
            order: $order->fresh(),
            item: $item->fresh(),
            cascadedChildrenCount: count($cascadedChildIds),
        ));

        Notification::make()
            ->title(__('admin.orders.cancel_item.success'))
            ->success()
            ->send();

    }

    /**
     * Handler de `refundItemAction` (sub-fase 7.2e.1bis): refund por
     * checkboxes. Itera la selección y aplica `executePartialRefund` a cada
     * uno con `alsoCancelItem=true`.
     *
     * Atomicidad parcial: cada item es una transacción independiente. Si
     * uno falla por REST/banco, los siguientes NO se intentan (evita el
     * caso "ya devolví 3 de 5, ahora el banco está caído y los próximos
     * fallan" → operador se queda con resultado mixto sin reintentos
     * automáticos sobre lo que ya tuvo éxito).
     */
    private function executeItemRefundBatch(array $data): void
    {
        /** @var Order $order */
        $order = $this->record->fresh();
        $user = auth()->user();
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

        // Capa 4: revalidar bloqueos del item PRINCIPAL con fila fresca.
        $reason = $order->refundItemBlockedReason($primaryItem);
        if ($reason !== null) {
            $this->logItemActionBlocked($order, $primaryItem, 'refund', $reason);
            $this->itemActionBlockedNotification('refund', $reason);

            return;
        }

        // Capa 3: optimistic lock vs item.updated_at.
        $sentToken = (string) ($data['optimistic_token'] ?? '');
        $currentToken = (string) ($primaryItem->updated_at?->getTimestamp() ?? '');
        if ($sentToken === '' || $sentToken !== $currentToken) {
            $this->logItemActionBlocked($order, $primaryItem, 'refund', 'stale_item_version');
            $this->itemActionBlockedNotification('refund', 'stale_item_version');

            return;
        }

        // Capa 3 bis: capacity sentinel.
        $expectedCapacity = (int) ($data['expected_capacity_cents'] ?? -1);
        $currentCapacity = $order->refundableCapacityCents();
        if ($expectedCapacity < 0 || $currentCapacity < $expectedCapacity) {
            $this->logItemActionBlocked($order, $primaryItem, 'refund', 'capacity_changed', [
                'expected_capacity_cents' => $expectedCapacity,
                'current_capacity_cents' => $currentCapacity,
            ]);
            $this->itemActionBlockedNotification('refund', 'capacity_changed');

            return;
        }

        // Selección del operador: lista de item_ids marcados en el CheckboxList.
        $selectedIds = array_values(array_unique(array_map(
            'intval',
            (array) ($data['items_to_refund'] ?? []),
        )));

        if ($selectedIds === []) {
            $this->itemActionBlockedNotification('refund', 'no_items_selected');

            return;
        }

        // "Marcar el pack auto-marca complementos": si el principal está
        // seleccionado, añadir TODOS sus children (idempotente vía array_unique).
        if (in_array($primaryItem->id, $selectedIds, true)) {
            foreach ($primaryItem->children as $child) {
                if (! in_array($child->id, $selectedIds, true)
                    && $order->itemRefundableRemainderCents($child) > 0) {
                    $selectedIds[] = $child->id;
                }
            }
        }

        // Validación de pertenencia: cada selected_id debe ser el principal
        // o uno de sus children. Defensa anti-IDOR ante manipulación del form.
        $allowedIds = array_merge([$primaryItem->id], $primaryItem->children->pluck('id')->all());
        foreach ($selectedIds as $sid) {
            if (! in_array($sid, $allowedIds, true)) {
                $this->logItemActionBlocked($order, $primaryItem, 'refund', 'invalid_item_selection', [
                    'submitted_item_id' => $sid,
                ]);
                $this->itemActionBlockedNotification('refund', 'invalid_item_selection');

                return;
            }
        }

        // Ejecutar refund batch: orquestador devuelve array de resultados
        // por item. Fallos parciales abortan los pendientes (atomicidad
        // parcial — ver docstring de executePartialRefundBatch).
        //
        // Sub-fase 7.2e.1bis4 (decisión #157): `alsoCancelItems=false`
        // explícito. Refund y cancellation son dimensiones independientes
        // (feedback empírico 2026-05-30). El operador refunda dinero sin
        // que se cancele el servicio; si quiere cancelar, usa el botón 🗑️
        // del sub-card por separado (que ahora cancela el producto entero
        // con cascada a sus complementos).
        $batchResult = $order->executePartialRefundBatch(
            itemIds: $selectedIds,
            by: $user,
            mode: $mode,
            alsoCancelItems: false,
        );

        $this->renderBatchResult($batchResult, $order, $mode);

    }

    /**
     * Renderiza notification + dispatch de emails tras un batch refund.
     * Si hubo éxitos parciales: notify success al operador con resumen +
     * email único al cliente con TODOS los items refundados (UX coherente:
     * un solo correo aunque haya N items).
     *
     * Si hubo fallos: notify danger con detalle del primer fallo + cita
     * cuántos quedaron pendientes.
     *
     * @param  array{succeeded:list<array{item:OrderItem,amount_cents:int}>, failed:list<array{item:OrderItem,reason:string,gateway_response_code:?string,failure_message:?string}>, aborted:list<int>}  $batchResult
     */
    private function renderBatchResult(array $batchResult, Order $order, string $mode): void
    {
        $succeeded = $batchResult['succeeded'] ?? [];
        $failed = $batchResult['failed'] ?? [];

        // Emails: uno por item refundado con éxito. Coherente con el patrón
        // de notification 1:1 — el cliente recibe N emails si se refundaron
        // N items. Sub-fase 7.2e.1bis4 (decisión #157): leemos el flag REAL
        // del resultado (`also_cancelled_item`) en vez de hardcodear `true`.
        // Como el handler invoca `executePartialRefundBatch(alsoCancelItems:
        // false)`, el cliente recibe "te hemos devuelto X €" sin línea de
        // cancellation; el item sigue activo en su pedido.
        foreach ($succeeded as $row) {
            $alsoCancelled = (bool) ($row['refund']->order_item_id !== null
                && $row['item']->fresh()->isCancelled());
            $order->notifyCustomer(new OrderItemRefunded(
                order: $order->fresh(),
                item: $row['item']->fresh(),
                refundedAmountCents: $row['amount_cents'],
                alsoCancelledItem: $alsoCancelled,
            ));
        }

        if ($succeeded === [] && $failed !== []) {
            // Todo falló — notify danger con el primer error.
            $first = $failed[0];
            $this->renderItemRefundBatchFailure($first);

            return;
        }

        if ($failed !== []) {
            // Éxito parcial — success con avisos.
            $totalAmount = array_sum(array_column($succeeded, 'amount_cents'));
            Notification::make()
                ->title(__('admin.orders.refund_item.success_partial_'.$mode, [
                    'count_ok' => count($succeeded),
                    'count_failed' => count($failed),
                    'amount' => number_format($totalAmount / 100, 2, ',', '.'),
                ]))
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        // Todos éxito.
        $totalAmount = array_sum(array_column($succeeded, 'amount_cents'));
        Notification::make()
            ->title(__('admin.orders.refund_item.success_all_'.$mode, [
                'count' => count($succeeded),
                'amount' => number_format($totalAmount / 100, 2, ',', '.'),
            ]))
            ->success()
            ->send();
    }

    /**
     * Render del fallo individual del primer item del batch.
     *
     * @param  array{item:OrderItem,reason:string,gateway_response_code:?string,failure_message:?string}  $failure
     */
    private function renderItemRefundBatchFailure(array $failure): void
    {
        $reason = $failure['reason'];

        if ($reason === 'gateway_failed') {
            $isTransport = (string) ($failure['failure_message'] ?? '') !== ''
                && str_contains((string) $failure['failure_message'], 'Network');
            $msg = $isTransport
                ? __('admin.orders.refund_item.transport_error')
                : __('admin.orders.refund_item.gateway_denied', [
                    'code' => $failure['gateway_response_code'] ?? '—',
                ]);
            Notification::make()
                ->title(__('admin.orders.refund_item.failed_title'))
                ->body($msg)
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if ($reason === 'inflight_refund') {
            Notification::make()
                ->title(__('admin.orders.refund_item.inflight_title'))
                ->body(__('admin.orders.refund_item.inflight_body'))
                ->warning()
                ->send();

            return;
        }

        $this->itemActionBlockedNotification('refund', $reason);
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
     * Notification de bloqueo al operador con texto específico por razón.
     *
     * @param  'cancel'|'refund'  $actionKey
     */
    private function itemActionBlockedNotification(string $actionKey, string $reason): void
    {
        $reasonText = __('admin.orders.item_actions.reasons.'.$reason);
        // Fallback: si la clave específica no existe, dejamos la razón cruda
        // en lugar del texto técnico (mejor que nada para el operador).
        if ($reasonText === 'admin.orders.item_actions.reasons.'.$reason) {
            $reasonText = $reason;
        }

        Notification::make()
            ->title(__('admin.orders.'.$actionKey.'_item.blocked', ['reason' => $reasonText]))
            ->danger()
            ->send();
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
