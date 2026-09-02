<?php

namespace App\Livewire\Auth;

use App\Domain\Identity\Contracts\PasswordResetResult;
use App\Domain\Identity\Services\PasswordPolicy;
use App\Domain\Identity\Services\PasswordRecovery;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Fase 4.4 — Fijar nueva contraseña desde el enlace del correo (página).
 * Usa el broker de Laravel (valida token + caducidad, 60 min). La nueva contraseña pasa por
 * `PasswordPolicy::rules()` y por la confirmación del formulario.
 * ⚠️ El control anti-filtración se RETIRÓ el 2026-09-02 (`[DECIDIDO owner]`, `#351`): esta línea
 * decía que lo aplicaba, y era la única pista que quedaba de él en esta pantalla.
 */
class ResetPassword extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token, string $email = ''): void
    {
        $this->token = $token;
        $this->email = $email;
    }

    public function resetPassword(PasswordRecovery $recovery)
    {
        $this->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => [...PasswordPolicy::rules(), 'confirmed'],
        ]);

        // El limitador por IP, la rotación del `remember_token`, la invalidación de TODAS las
        // credenciales (paso 3a) y la no-enumeración viven en `Identity\Services\PasswordRecovery`
        // desde Fase 3 · paso 3c: la API los consume sin reescribirlos.
        $result = $recovery->reset($this->token, $this->email, $this->password, (string) request()->ip());

        if (! $result->succeeded()) {
            throw ValidationException::withMessages(['email' => $this->messageFor($result)]);
        }

        session()->flash('status', 'password-reset');

        return $this->redirectRoute('login');
    }

    /**
     * Veredicto → mensaje del modal. `INVALID` es genérico a propósito: un correo inexistente y un
     * token caducado dicen lo mismo, o el formulario sería un oráculo de qué correos existen.
     */
    private function messageFor(PasswordResetResult $result): string
    {
        return match ($result->outcome) {
            PasswordResetResult::RATE_LIMITED => __('auth.throttle', ['seconds' => $result->retryAfter]),
            PasswordResetResult::BROKER_THROTTLED => __(Password::RESET_THROTTLED),
            default => __(Password::INVALID_TOKEN),
        };
    }

    public function render()
    {
        return view('livewire.auth.reset-password');
    }
}
