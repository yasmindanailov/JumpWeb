<?php

namespace App\Filament\Auth;

use App\Domain\Identity\Services\PasswordPolicy;
use Filament\Actions\Action;
use Filament\Auth\Pages\PasswordReset\ResetPassword;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;

/**
 * **Donde el personal crea su contraseña del panel** (A5a de `docs/specs/acceso-con-codigo.md` §4.12, `DECISIONES #870`).
 * Se llega por el enlace que le envía un administrador desde su ficha (`Identity\Services\PanelPasswordLinks`); vive bajo
 * la dirección del panel y sin sesión (`->routes()` de `AdminPanelProvider`), con el enlace firmado (`signed`).
 *
 * Es la página de Filament, que ya hace lo que importa: se NIEGA a quien no entra al panel —un token de un cliente no le
 * pone contraseña: `canAccessPanel()` en el mismo `reset()`—, limita por IP y por correo, rota el `remember_token` (los
 * dispositivos recordados de la web salen) y lleva al login del panel. Lo nuestro: la política de `PasswordPolicy` (la
 * fuente única, `PasswordPolicySingleSourceTest`) y los rótulos.
 *
 * ⚠️ Sin `User::revokeAllAccess()`, a propósito: es la palanca de «me han entrado», y revocaría el carné QR y desvincularía
 * Google de alguien que solo está poniendo su contraseña del panel.
 */
class PanelPassword extends ResetPassword
{
    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('filament-panels::auth/pages/password-reset/reset-password.form.password.label'))
            ->password()
            ->autocomplete('new-password')
            ->revealable(filament()->arePasswordsRevealable())
            ->required()
            ->rules(PasswordPolicy::rules())
            ->same('passwordConfirmation')
            ->validationAttribute(__('filament-panels::auth/pages/password-reset/reset-password.form.password.validation_attribute'));
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.users.panel_password.title');
    }

    public function getHeading(): string|Htmlable|null
    {
        return __('admin.users.panel_password.title');
    }

    public function getResetPasswordFormAction(): Action
    {
        return parent::getResetPasswordFormAction()->label(__('admin.users.panel_password.submit'));
    }
}
