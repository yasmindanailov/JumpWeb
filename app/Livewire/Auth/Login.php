<?php

namespace App\Livewire\Auth;

use App\Livewire\Concerns\ResetsOnModalClose;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Fase 4.3 — Inicio de sesión (modal). Seguridad "Reforzado":
 * rate limiting + bloqueo temporal por email+IP, mensaje genérico (no revela si
 * el email existe), regeneración de sesión al entrar y registro de `last_login_at`.
 */
class Login extends Component
{
    use ResetsOnModalClose;

    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    /** Embebido en el sidebar de compra (#69): al entrar avisa con un evento en vez de redirigir. */
    public bool $embedded = false;

    /** Nº de fallos seguidos antes del bloqueo temporal (por email+IP, caso single-account). */
    private const MAX_ATTEMPTS = 5;

    /**
     * Tope de fallos por IP-sola (auditoría Fase 1, A5): la clave compuesta `email|ip` NO frena el
     * credential-stuffing / password-spraying / fuerza bruta distribuida —1 intento por (cuenta,IP)
     * nunca acumula 5 en ninguna clave—. Un 2.º limitador SOLO por IP corta el barrido (un atacante
     * que prueba muchas cuentas desde una IP). Generoso para no penalizar NAT compartido legítimo.
     */
    private const MAX_ATTEMPTS_PER_IP = 30;

    public function login()
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited();

        $credentials = ['email' => Str::lower(trim($this->email)), 'password' => $this->password];

        if (! Auth::attempt($credentials, $this->remember)) {
            RateLimiter::hit($this->throttleKey());
            RateLimiter::hit($this->ipThrottleKey()); // A5: cuenta el fallo también por IP-sola
            Log::info('auth.login_failed', ['ip' => request()->ip()]);

            // Mensaje genérico: no revela si el email existe (anti-enumeración).
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        // Anti-cesta-cruzada (auditoría 2026-05-26, hallazgo D): si la sesión ya tenía un
        // marcador `purchase.user_id` distinto del usuario que acaba de iniciar sesión, la
        // cesta pertenece a OTRO cliente — la descartamos antes de continuar para que Bob no
        // herede la cesta de Alice en un dispositivo compartido. Hacemos esto ANTES del
        // regenerate para no perder el marcador.
        $previousCartOwner = session('purchase.user_id');
        $newId = Auth::id();
        if ($previousCartOwner !== null && (int) $previousCartOwner !== (int) $newId) {
            session()->forget(['purchase.cart', 'purchase.confirmed_code']);
        }
        session(['purchase.user_id' => $newId]);

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $user = Auth::user();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        Log::info('auth.login', ['user_id' => $user->id, 'ip' => request()->ip()]);

        // Embebido en la compra: no redirige; avisa al sidebar para que continúe la reserva.
        if ($this->embedded) {
            $this->dispatch('logged-in');

            return null;
        }

        return $this->redirectIntended('/');
    }

    /**
     * Bloqueo temporal: tras N fallos seguidos (por email+IP) se corta con un
     * mensaje de espera, hasta que pase la ventana del limitador.
     *
     * El mensaje va a la clave `_global` (no al campo email) para distinguirlo de "credenciales
     * incorrectas" en la UI (L-02, auditoría 2026-05-26): así nunca se ven los dos mensajes
     * mezclados bajo el mismo input.
     */
    protected function ensureIsNotRateLimited(): void
    {
        $okComposite = ! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS);
        $okPerIp = ! RateLimiter::tooManyAttempts($this->ipThrottleKey(), self::MAX_ATTEMPTS_PER_IP);
        if ($okComposite && $okPerIp) {
            return;
        }

        event(new Lockout(request()));
        Log::info('auth.login_lockout', ['ip' => request()->ip()]);

        // La espera la marca la clave que esté bloqueada (la otra devuelve 0).
        $seconds = max(
            RateLimiter::availableIn($this->throttleKey()),
            RateLimiter::availableIn($this->ipThrottleKey()),
        );

        throw ValidationException::withMessages([
            '_global' => __('auth.throttle', ['seconds' => $seconds]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower(trim($this->email)).'|'.request()->ip());
    }

    /** Clave del 2.º limitador, SOLO por IP (A5): no se limpia al login correcto (es compartida). */
    protected function ipThrottleKey(): string
    {
        return 'login-ip|'.request()->ip();
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
