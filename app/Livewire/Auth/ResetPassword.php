<?php

namespace App\Livewire\Auth;

use App\Domain\Identity\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Fase 4.4 — Fijar nueva contraseña desde el enlace del correo (página).
 * Usa el broker de Laravel (valida token + caducidad, 60 min). La nueva
 * contraseña pasa el control anti-filtración (`uncompromised`) y confirmación.
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

    public function resetPassword()
    {
        $this->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', PasswordRule::min(8)->uncompromised()],
        ]);

        // Rate-limit por IP (auditoría Fase 1, A3): la acción Livewire corre por `/livewire/update`, NO
        // por el throttle:6,1 de la ruta GET → sin esto se podría martillar el broker. Mismo patrón que Login.
        $key = 'reset-password|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 6)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }
        RateLimiter::hit($key);

        $status = Password::reset(
            [
                'email' => Str::lower(trim($this->email)),
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function (User $user, string $password) {
                // El cast `password => 'hashed'` (User) hashea al asignar y detecta dobles hashes
                // (isHashed), por eso no llamamos a Hash::make aquí — sería redundante.
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                // Caso de uso central de «olvidé mi contraseña»: si la víctima resetea por sospecha
                // de robo, NO podemos dejar viva ninguna credencial suya. Aquí no estamos
                // autenticados (el reset no autentica), así que no hay ninguna que preservar: se
                // van TODAS, sesiones y tokens de API (Fase 3 · paso 3a) — un Bearer del atacante
                // sobrevivía al reset—. Las cookies «remember me» las invalida la rotación del
                // `remember_token` de arriba.
                $user->revokeAllAccess();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            // Anti-enumeración (auditoría Fase 1, A3): un email INEXISTENTE (`INVALID_USER`) y un token
            // inválido/caducado (`INVALID_TOKEN`) deben dar el MISMO mensaje genérico; si difieren, un
            // atacante distingue qué correos están registrados. El throttling del broker conserva su
            // propio mensaje (no es enumeración, es un límite de tasa legítimo).
            $message = $status === Password::RESET_THROTTLED ? __($status) : __(Password::INVALID_TOKEN);
            throw ValidationException::withMessages(['email' => $message]);
        }

        RateLimiter::clear($key);
        Log::info('auth.password_reset', ['ip' => request()->ip()]);
        session()->flash('status', 'password-reset');

        return $this->redirectRoute('login');
    }

    public function render()
    {
        return view('livewire.auth.reset-password');
    }
}
