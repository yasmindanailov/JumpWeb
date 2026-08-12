<?php

namespace App\Livewire\Auth;

use App\Livewire\Concerns\ResetsOnModalClose;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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

    public function sendLink()
    {
        $this->validate(['email' => ['required', 'string', 'email']]);

        $key = 'forgot:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }
        RateLimiter::hit($key, 60);

        // Genérico siempre: enviamos el enlace si la cuenta existe, pero mostramos
        // el mismo resultado en todos los casos (anti-enumeración, SEGURIDAD §2).
        Password::sendResetLink(['email' => Str::lower(trim($this->email))]);
        Log::info('auth.password_reset_requested', ['ip' => request()->ip()]);

        $this->sent = true;
    }

    public function render()
    {
        return view('livewire.auth.forgot-password');
    }
}
