<?php

namespace App\Livewire\Auth;

use App\Domain\Identity\Contracts\PasswordResetResult;
use App\Domain\Identity\Services\PasswordRecovery;
use App\Livewire\Concerns\ResetsOnModalClose;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Fase 4.4 — Solicitud de recuperación de contraseña (modal).
 * Envía el enlace con el broker de Laravel. Seguridad "Reforzado":
 * rate limiting + **mensaje genérico** (no revela si el email existe).
 */
class ForgotPassword extends Component
{
    use ResetsOnModalClose;

    public string $email = '';

    public bool $sent = false;

    public function sendLink(PasswordRecovery $recovery)
    {
        $this->validate(['email' => ['required', 'string', 'email']]);

        $result = $recovery->requestLink($this->email, (string) request()->ip());

        if ($result->outcome === PasswordResetResult::RATE_LIMITED) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => $result->retryAfter]),
            ]);
        }

        // Genérico SIEMPRE: el enlace se envía si la cuenta existe, pero la pantalla es la misma en
        // todos los casos (anti-enumeración, `SEGURIDAD` §2). Lo decide el servicio; aquí solo se
        // pinta.
        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
