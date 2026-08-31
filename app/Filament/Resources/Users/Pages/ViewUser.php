<?php

namespace App\Filament\Resources\Users\Pages;

use App\Domain\Booking\Contracts\CustomerReservations;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CustomerCards;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\HtmlString;

/**
 * Fase 7.5 — Ficha del usuario con acciones RGPD (decisión #180).
 *
 * Dos acciones de cabecera, ambas con defensa en profundidad (patrón #128 reusado
 * de `ViewOrder`): `visible()` (permiso + estado) → re-check con `fresh()` → audit
 * del bloqueo o del éxito → `Notification`.
 *
 *  - **Enviar enlace de contraseña** (`users.manage`): `Password::sendResetLink`
 *    (notificación nativa `ResetPassword`, broker `users`). Audit `logSensitive`
 *    (hashea el email, sin PII en claro).
 *  - **Anonimizar** (`users.anonymize`): `User::anonymize()` (idempotente, borra
 *    consents + roles + neutraliza PII). Audit `log` con `email_hash` + motivo +
 *    conteos (sin PII en claro). Redirige al listado al terminar.
 *
 * Ambas acciones solo aplican sobre **cuentas de cliente** (no admin/staff), nunca
 * sobre uno mismo, nunca sobre cuentas ya anonimizadas — ver `isSensitiveActionAllowed`.
 */
class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var User $record */
        $record = $this->record;

        // Título de la pestaña del navegador (sin HTML).
        return __('admin.users.heading').' · '.$record->name;
    }

    /**
     * H1 enriquecida: nombre del cliente + badge(s) de rol + (si aplica) "Anonimizada"
     * inline (decisión #181). Pone el rol al lado del título → la ficha respira y se
     * identifica de un vistazo sin gastar una card en ello.
     */
    public function getHeading(): string|Htmlable
    {
        return new HtmlString(view('filament.users.view-heading', [
            'record' => $this->record,
        ])->render());
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->manageRolesAction(),
            $this->waiverProofAction(),
            $this->sendPasswordResetAction(),
            $this->rotateCardAction(),
            $this->anonymizeUserAction(),
        ];
    }

    /**
     * Fase 6 · subsistema A (`specs/identidad-qr-puerta.md` §4.5, §9.6 B·5) — **rotar el carné QR** del
     * cliente desde el mostrador: un carné perdido, fotografiado o que circula por donde no debe deja
     * de valer EN EL ACTO y el cliente recibe otro. Es la MISMA transacción bajo el lock del titular
     * que usa `POST /me/card/rotate`; lo que cambia es el ACTOR: el `cards.rotated` que escribe
     * `CustomerCards::rotate()` lleva al operador como `user_id` y al titular como target, y eso es lo
     * que distingue en la auditoría una rotación del mostrador de una del propio cliente.
     *
     * Mismo patrón de defensa que sus hermanas: `visible()` (permiso + solo clientes, nunca uno
     * mismo, nunca anonimizada) → `fresh()` + re-check → auditoría del bloqueo o del éxito → aviso.
     * ⚠️ NO revoca el acceso (`revokeAllAccess()` cierra además sesiones y tokens): rotar el carné es
     * lo que un operador puede hacer sin echar al cliente de su cuenta — el hueco que `DEUDA` anotaba.
     */
    private function rotateCardAction(): Action
    {
        return Action::make('rotateCard')
            ->label(__('admin.users.actions.rotate_card.label'))
            ->icon(Heroicon::OutlinedQrCode)
            ->color('gray')
            ->visible(fn (User $record): bool => (auth()->user()?->hasPermission('users.manage') ?? false)
                && $this->isSensitiveActionAllowed($record))
            ->requiresConfirmation()
            ->modalHeading(__('admin.users.actions.rotate_card.modal_heading'))
            ->modalDescription(function (User $record): string {
                // Le dice al operador si HAY carné activo y desde cuándo: rotar «nada» también emite uno.
                $active = app(CustomerCards::class)->activeFor($record);

                return $active === null
                    ? __('admin.users.actions.rotate_card.modal_description_none')
                    : __('admin.users.actions.rotate_card.modal_description_active', [
                        'date' => $active->issued_at->setTimezone(config('app.timezone'))->format('d/m/Y'),
                    ]);
            })
            ->modalSubmitActionLabel(__('admin.users.actions.rotate_card.submit'))
            ->action(function (User $record): void {
                $record = $record->fresh();

                if ($record === null || ! $this->isSensitiveActionAllowed($record)) {
                    if ($record !== null) {
                        AuditLogger::log('users.rotate_card_blocked', $record, ['reason' => 'not_allowed']);
                    }
                    Notification::make()
                        ->title(__('admin.users.actions.rotate_card.blocked'))
                        ->danger()
                        ->send();

                    return;
                }

                // `rotate()` audita `cards.rotated` por cada carné que mata (con este operador de actor) y
                // `cards.issued` por el nuevo. Nunca el token (`RGPD-02`).
                app(CustomerCards::class)->rotate($record);

                Notification::make()
                    ->title(__('admin.users.actions.rotate_card.success'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Fase 6 · waiver (`specs/waiver-probatorio.md` §4.6) — el REGISTRO probatorio del titular, en
     * régimen restringido: NO es una sección de la ficha, es una acción con permiso PROPIO
     * (`waiver.view`) cuya apertura ES la consulta y queda AUDITADA (`waiver.proof_viewed`). Visible
     * también sobre cuentas anonimizadas: es exactamente entonces cuando la prueba hace falta.
     */
    private function waiverProofAction(): Action
    {
        return Action::make('waiverProof')
            ->label(__('admin.waiver.proof.action'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->hasPermission('waiver.view') ?? false)
            ->modalHeading(fn (User $record): string => __('admin.waiver.proof.heading', ['name' => $record->name]))
            ->modalDescription(__('admin.waiver.proof.description'))
            ->modalContent(fn (User $record) => view('filament.users.partials.waiver-proof', ['record' => $record]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('admin.waiver.proof.close'))
            ->mountUsing(function (User $record): void {
                // «Cada consulta auditada» (§4.6): abrir el registro es la consulta. Sin PII.
                AuditLogger::log('waiver.proof_viewed', $record, [
                    'user_id' => $record->getKey(),
                    'signatures' => $record->waiverSignatures()->count(),
                ]);
            });
    }

    /**
     * ¿Se permiten acciones sensibles (anonimizar / reset) sobre esta cuenta?
     * Solo clientes: no admin, no staff, nunca uno mismo, nunca ya anonimizada.
     * Lee la colección `roles` eager-loaded (`UserResource::getEloquentQuery`) para
     * no lanzar una query por cada render de `visible()`.
     */
    private function isSensitiveActionAllowed(User $record): bool
    {
        $roleNames = $record->roles->pluck('name');

        return ! $record->isAnonymized()
            && ! $roleNames->contains('admin')
            && ! $roleNames->contains('staff')
            && $record->getKey() !== auth()->id();
    }

    /**
     * Fase 7.11 — Asignar/quitar roles a este usuario (`role_user`). Gateada por `access.manage`
     * (en la práctica solo admin, vía Gate::before). Cierra el diferido #180 (la edición de roles
     * de usuario se aplazó a "la futura sub-fase de roles" = esta).
     *
     * Defensa en profundidad (patrón de las acciones RGPD): `visible()` (permiso + no anonimizada)
     * → `action()` con `fresh()` + re-check + guardas anti-bloqueo + audit.
     *
     * Guardas anti-bloqueo:
     *  1. No puedes quitarte a TI MISMO el rol admin (evita auto-lockout accidental; otro admin
     *     puede degradarte).
     *  2. No puedes quitar el ÚLTIMO admin del sistema.
     */
    private function manageRolesAction(): Action
    {
        return Action::make('manageRoles')
            ->label(__('admin.access.user_roles.label'))
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('gray')
            ->visible(fn (User $record): bool => (auth()->user()?->hasPermission('access.manage') ?? false)
                && ! $record->isAnonymized())
            ->modalHeading(__('admin.access.user_roles.modal_heading'))
            ->modalDescription(__('admin.access.user_roles.modal_description'))
            ->modalSubmitActionLabel(__('admin.access.user_roles.submit'))
            ->fillForm(fn (User $record): array => [
                'roles' => $record->roles()->pluck('roles.id')->all(),
            ])
            ->schema([
                CheckboxList::make('roles')
                    ->label(__('admin.access.user_roles.field'))
                    ->options(fn (): array => Role::query()
                        ->orderBy('id')
                        ->get()
                        ->mapWithKeys(fn (Role $role): array => [$role->id => __('admin.users.roles.'.$role->name)])
                        ->all())
                    ->columns(1)
                    ->bulkToggleable(false),
            ])
            ->action(function (User $record, array $data): void {
                $record = $record->fresh();
                $actor = auth()->user();

                // Re-check del permiso (capa 2) + cuenta no anonimizada entre render y submit.
                if ($record === null
                    || ! ($actor?->hasPermission('access.manage') ?? false)
                    || $record->isAnonymized()) {
                    if ($record !== null) {
                        AuditLogger::log('access.user_roles_update_blocked', $record, ['reason' => 'forbidden']);
                    }
                    Notification::make()->title(__('admin.access.user_roles.blocked'))->danger()->send();

                    return;
                }

                $before = $record->roles()->pluck('name')->all();

                $targetIds = collect($data['roles'] ?? [])
                    ->map(fn ($value): int => (int) $value)
                    ->all();
                $targetNames = Role::whereIn('id', $targetIds)->pluck('name')->all();

                $hadAdmin = in_array('admin', $before, true);
                $keepsAdmin = in_array('admin', $targetNames, true);

                // Guarda 1: no quitarte a ti mismo el rol admin.
                if ($record->getKey() === $actor->getKey() && $hadAdmin && ! $keepsAdmin) {
                    AuditLogger::log('access.user_roles_update_blocked', $record, ['reason' => 'self_admin_demotion']);
                    Notification::make()->title(__('admin.access.user_roles.blocked_self'))->danger()->send();

                    return;
                }

                // Guarda 2 (anti-bloqueo): no quitar el último admin del sistema. El recuento y el
                // sync van DENTRO de una transacción con `lockForUpdate` sobre el conjunto de admins
                // → serializa degradaciones concurrentes: dos admins quitándose admin a la vez no
                // pueden dejar el sistema con 0 admins (TOCTOU). En SQLite el lock es no-op pero la
                // lógica sigue siendo correcta (los tests no son concurrentes).
                $blockedLastAdmin = false;
                DB::transaction(function () use ($record, $targetIds, $hadAdmin, $keepsAdmin, &$blockedLastAdmin): void {
                    if ($hadAdmin && ! $keepsAdmin) {
                        $admins = User::whereHas('roles', fn ($query) => $query->where('name', 'admin'))
                            ->lockForUpdate()
                            ->count();

                        if ($admins <= 1) {
                            $blockedLastAdmin = true;

                            return;
                        }
                    }

                    $record->roles()->sync($targetIds);
                });

                if ($blockedLastAdmin) {
                    AuditLogger::log('access.user_roles_update_blocked', $record, ['reason' => 'last_admin']);
                    Notification::make()->title(__('admin.access.user_roles.blocked_last_admin'))->danger()->send();

                    return;
                }

                $after = $record->fresh()?->roles()->pluck('name')->all() ?? [];
                $added = array_values(array_diff($after, $before));
                $removed = array_values(array_diff($before, $after));

                AuditLogger::log('access.user_roles_updated', $record, [
                    'added' => $added,
                    'removed' => $removed,
                ]);

                Notification::make()->title(__('admin.access.user_roles.success'))->success()->send();
            });
    }

    private function sendPasswordResetAction(): Action
    {
        return Action::make('sendPasswordReset')
            ->label(__('admin.users.actions.send_reset.label'))
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->visible(fn (User $record): bool => (auth()->user()?->hasPermission('users.manage') ?? false)
                && $this->isSensitiveActionAllowed($record))
            ->requiresConfirmation()
            ->modalHeading(__('admin.users.actions.send_reset.modal_heading'))
            ->modalDescription(fn (User $record): string => __('admin.users.actions.send_reset.modal_description', [
                'email' => $record->email,
            ]))
            ->modalSubmitActionLabel(__('admin.users.actions.send_reset.submit'))
            ->action(function (User $record): void {
                $record = $record->fresh();

                if (! $this->isSensitiveActionAllowed($record)) {
                    AuditLogger::log('users.send_reset_blocked', $record, ['reason' => 'not_allowed']);
                    Notification::make()
                        ->title(__('admin.users.actions.send_reset.blocked'))
                        ->danger()
                        ->send();

                    return;
                }

                $status = Password::sendResetLink(['email' => $record->email]);

                // Audit sensible: el identificador (email) se guarda solo como sha256.
                AuditLogger::logSensitive('users.password_reset_sent', $record->email, $record);

                if ($status === Password::RESET_LINK_SENT) {
                    Notification::make()
                        ->title(__('admin.users.actions.send_reset.success', ['email' => $record->email]))
                        ->success()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('admin.users.actions.send_reset.throttled'))
                    ->warning()
                    ->send();
            });
    }

    private function anonymizeUserAction(): Action
    {
        return Action::make('anonymizeUser')
            ->label(__('admin.users.actions.anonymize.label'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (User $record): bool => (auth()->user()?->hasPermission('users.anonymize') ?? false)
                && $this->isSensitiveActionAllowed($record))
            ->requiresConfirmation()
            ->modalHeading(__('admin.users.actions.anonymize.modal_heading'))
            ->modalDescription(__('admin.users.actions.anonymize.modal_description'))
            ->modalSubmitActionLabel(__('admin.users.actions.anonymize.submit'))
            ->schema([
                Textarea::make('reason')
                    ->label(__('admin.users.actions.anonymize.reason'))
                    ->required()
                    ->maxLength(500),
            ])
            ->action(function (User $record, array $data): void {
                $record = $record->fresh();

                if (! $this->isSensitiveActionAllowed($record)) {
                    AuditLogger::log('users.anonymize_blocked', $record, ['reason' => 'not_allowed']);
                    Notification::make()
                        ->title(__('admin.users.actions.anonymize.blocked'))
                        ->danger()
                        ->send();

                    return;
                }

                // T5 · D8 (`cumple-mixto.md` §25.4, `[DECIDIDO owner]` §25.9 Q2: las TRES vías):
                // con una reserva POR CELEBRAR la cuenta no se anonimiza tampoco desde el panel —
                // si el operador pudiera, una reserva viva quedaría anónima y su importe congelado
                // (la ficha del «techo tras anonimizar» se cierra POR esta puerta). Su escape ya
                // existe: cancelar la reserva primero. ⚠️ La condición va aquí y NO en
                // `isSensitiveActionAllowed()`: ésa la comparten «rotar carné» y «enviar reset»,
                // que no deben endurecerse.
                if (app(CustomerReservations::class)->hasUpcomingFor((int) $record->getKey())) {
                    AuditLogger::log('users.anonymize_blocked', $record, ['reason' => 'upcoming_reservations']);
                    Notification::make()
                        ->title(__('admin.users.actions.anonymize.blocked_upcoming'))
                        ->danger()
                        ->send();

                    return;
                }

                // Captura del pre-estado ANTES de mutar: `anonymize()` borra el email,
                // los consents y los roles. El audit guarda el HASH del email (nunca en
                // claro) + el motivo del operador + conteos para trazabilidad RGPD.
                $emailHash = hash('sha256', (string) $record->email);
                $consentsDeleted = $record->consents()->count();
                $rolesDetached = $record->roles->pluck('name')->all();

                $record->anonymize();

                AuditLogger::log('users.anonymized', $record, [
                    'email_hash' => $emailHash,
                    'reason' => (string) ($data['reason'] ?? ''),
                    'consents_deleted' => $consentsDeleted,
                    'roles_detached' => $rolesDetached,
                ]);

                Notification::make()
                    ->title(__('admin.users.actions.anonymize.success'))
                    ->success()
                    ->send();

                // La ficha ahora apunta a una cuenta neutralizada → volver al listado.
                $this->redirect(UserResource::getUrl('index'));
            });
    }
}
