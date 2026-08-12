<?php

namespace App\Livewire\Auth;

use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\Turnstile;
use App\Livewire\Concerns\ResetsOnModalClose;
use App\Notifications\AccountAlreadyExists;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Fase 4.2 — Registro de usuario (modal). Primer componente Livewire del proyecto.
 * Seguridad "Reforzado": contraseña no filtrada, casillas legales obligatorias,
 * rate limiting, honeypot, Turnstile por clave y anti-enumeración de emails.
 * No inicia sesión: la cuenta se activa al verificar el email (Flujo 1).
 */
class Register extends Component
{
    use ResetsOnModalClose;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $password = '';

    public bool $accept_privacy = false;

    public bool $accept_terms = false;

    public bool $marketing = false;

    /** Token del widget Turnstile (solo si está activado por clave). */
    public string $turnstileToken = '';

    /** Honeypot: campo oculto; si llega relleno es un bot. */
    public string $website = '';

    /** Embebido en el sidebar de compra (#69): oculta los conmutadores del modal. */
    public bool $embedded = false;

    /** Tras un registro válido pasamos a la pantalla "te hemos enviado un correo". */
    public bool $sent = false;

    /** Reenvíos de verificación que quedan desde el modal (límite anti-abuso). */
    public int $resendsLeft = 4;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Sin `unique` a propósito: la existencia se gestiona sin revelarla (anti-enumeración).
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', Password::min(8)->uncompromised()],
            'accept_privacy' => ['accepted'],
            'accept_terms' => ['accepted'],
            'marketing' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'accept_privacy.accepted' => __('account.register.must_accept'),
            'accept_terms.accepted' => __('account.register.must_accept'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'name' => __('account.register.name'),
            'email' => __('account.register.email'),
            'phone' => __('account.register.phone'),
            'password' => __('account.register.password'),
        ];
    }

    public function register()
    {
        // 1) Honeypot: si llega relleno (bot), NO creamos cuenta pero mostramos el
        //    mismo "te hemos enviado un correo" (el bot no nota nada y, si fuese un
        //    falso positivo de autocompletar, el usuario no se queda sin feedback).
        if ($this->website !== '') {
            Log::info('auth.register_honeypot', ['ip' => request()->ip()]);
            $this->sent = true;

            return;
        }

        // 2) Rate limiting por IP (cuenta todos los intentos, válidos o no).
        $ip = request()->ip();
        $key = 'register:'.$ip;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }
        RateLimiter::hit($key, 60);

        // 3) Normalizar y validar.
        $this->email = Str::lower(trim($this->email));
        $data = $this->validate();

        // 3.bis) Rate limit por EMAIL víctima (no solo IP) — anti-DoS distribuido: un atacante con
        //        IPs rotativas no puede bombardear el buzón de un usuario con re-envíos de verificación
        //        o avisos "AccountAlreadyExists". El email se hashea para no exponerlo en cache keys.
        //        Cuando se supera, mostramos la pantalla genérica "sent" SIN enviar nada (anti-enumeración).
        $emailKey = 'register-email:'.self::emailHash($data['email']);
        if (RateLimiter::tooManyAttempts($emailKey, 3)) {
            Log::info('auth.register_email_throttled', ['ip' => $ip]);

            return $this->finishGeneric();
        }
        RateLimiter::hit($emailKey, 3600); // 3 por hora

        // 4) Anti-bot Turnstile (solo si hay clave configurada).
        if (! Turnstile::verify($this->turnstileToken, $ip)) {
            throw ValidationException::withMessages([
                'email' => __('account.register.bot_check_failed'),
            ]);
        }

        // 5) Anti-enumeración: si el email ya existe, NO lo revelamos. Mostramos el
        //    mismo resultado genérico; si no está verificado, le reenviamos la
        //    verificación (para que pueda completar el alta); si ya lo está, le avisamos.
        if ($existing = User::where('email', $data['email'])->first()) {
            Log::info('auth.register_existing_email', ['ip' => $ip, 'verified' => $existing->hasVerifiedEmail()]);

            // [decisión clienta] Feedback CLARO en vez de anti-enumeración: para el parque prima la
            // UX/conversión sobre ocultar qué emails existen. Se avisa en pantalla y se guía al login.
            if ($existing->hasVerifiedEmail()) {
                // Aviso por email al titular real (con enlace de login), no bloqueante.
                try {
                    $existing->notify(new AccountAlreadyExists);
                } catch (\Throwable $e) {
                    Log::warning('auth.register_email_failed', ['user_id' => $existing->id, 'error' => $e->getMessage()]);
                }
                throw ValidationException::withMessages([
                    'email' => __('account.register.already_exists'),
                ]);
            }

            // Existe pero SIN verificar: reenviar la verificación (no bloqueante) + mensaje claro,
            // para que pueda completar el alta desde el correo.
            try {
                $existing->sendEmailVerificationNotification();
            } catch (\Throwable $e) {
                Log::warning('auth.register_email_failed', ['user_id' => $existing->id, 'error' => $e->getMessage()]);
            }
            throw ValidationException::withMessages([
                'email' => __('account.register.exists_unverified'),
            ]);
        }

        // 6) Crear cuenta + consentimientos + rol en una sola TRANSACCIÓN (atomicidad).
        //    Sin esto, si falla a mitad el usuario quedaría sin rol o sin consents (RGPD: prueba
        //    de aceptación). Los envíos de email se hacen FUERA de la transacción (no son atómicos
        //    con la BD y un fallo de SMTP no debe revertir la cuenta).
        $now = now();
        $user = DB::transaction(function () use ($data, $now, $ip) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'locale' => app()->getLocale(),
                'marketing_opt_in' => $this->marketing,
                'privacy_accepted_at' => $now,
                'terms_accepted_at' => $now,
                // #216: el waiver sale del flujo de registro (lo gestiona el sistema externo de la
                // clienta). NO se fija `waiver_accepted_at` ni se crea el consent 'waiver' aquí; la
                // columna/tipo de consent se conservan para datos históricos y futuros usos.
            ]);

            if ($role = Role::where('name', 'customer')->first()) {
                $user->roles()->attach($role);
            }

            $types = ['privacy', 'terms'];
            if ($this->marketing) {
                $types[] = 'marketing';
            }
            foreach ($types as $type) {
                $user->consents()->create([
                    'type' => $type,
                    'accepted_at' => $now,
                    'ip' => $ip,
                    'version' => Consent::CURRENT_VERSION,
                ]);
            }

            return $user;
        });

        // Pay-first (decisión clienta 2026-06-14):
        //  · En la COMPRA (embebido): NO se manda email de verificación ni se retiene reserva.
        //    Iniciamos sesión y avisamos al sidebar para CONTINUAR al paso de PAGO; el email se
        //    verifica solo al completar el pago en Redsys (auto-verify, un bot no paga). Si abandona
        //    sin pagar, queda sin verificar y puede reenviar el correo desde la página de aviso al
        //    intentar entrar a «mi cuenta». Anti-bot intacto: rate limits + Turnstile arriba.
        //  · En el registro SUELTO (modal de la home, sin compra): verificación por email clásica.
        if ($this->embedded) {
            Auth::login($user);
            session(['purchase.user_id' => $user->id]); // anti-cesta-cruzada (espejo de Login)
            Log::info('auth.registered', ['user_id' => $user->id, 'ip' => $ip, 'purchase' => true]);
            $this->dispatch('logged-in'); // Purchase::onAuthenticated → proceed() → paso de pago

            return;
        }

        // Email NO bloqueante (#robustez): la cuenta ya existe; un fallo del transporte de correo no
        // debe romper el registro. Se registra y el usuario puede reenviar la verificación.
        try {
            $user->sendEmailVerificationNotification();
            Log::info('auth.registered', ['user_id' => $user->id, 'ip' => $ip]);
        } catch (\Throwable $e) {
            Log::warning('auth.register_email_failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }

        $this->finishGeneric();
    }

    /**
     * Cierre común (cuenta nueva o email ya existente): pasa a la pantalla de
     * "te hemos enviado un correo". Idéntico en ambos casos → no revela si el
     * email ya existía (anti-enumeración).
     */
    protected function finishGeneric(): void
    {
        $this->sent = true;

        // Embebido en la compra: avisa al sidebar para que muestre "revisa tu correo" (genérico,
        // sin código → no revela si el email ya existía, #46). La reserva provisional, si la cuenta
        // es nueva, se ha creado en segundo plano.
        if ($this->embedded) {
            $this->dispatch('registration-submitted');
        }
    }

    /**
     * Escape "¿ya tienes cuenta? Inicia sesión" desde la pantalla 'verifica tu correo'
     * cuando este componente está EMBEBIDO en el sidebar de compra (#69, decisión #112).
     *
     * En modo no-embedded (modal independiente) el switch se hace con `$store.auth.open('login')`
     * desde Alpine — no toca al servidor. En modo embedded, sin embargo, hay que avisar al
     * `Purchase` Livewire del sidebar para que cambie su `authMode` y resetee el estado
     * del Register (para no dejar el `sent=true` colgado si el usuario vuelve a registro).
     *
     * Anti-enumeración (#46) intacta: este método se invoca igual si el email era nuevo o
     * ya existía — el flujo a la pantalla `sent` es idéntico en ambos casos, y este link
     * sale exactamente igual también para registros legítimos que quieran volver a login.
     */
    public function requestSwitchToLogin(): void
    {
        if (! $this->embedded) {
            return; // en modal independiente lo gestiona Alpine; no nos llaman desde ahí.
        }

        $this->reset(['sent', 'name', 'email', 'phone', 'password', 'accept_privacy', 'accept_terms', 'marketing', 'turnstileToken', 'website']);
        $this->resetValidation();
        $this->dispatch('purchase:switch-to-login');
    }

    /**
     * Reenvía el correo de verificación al email registrado (desde el modal).
     * Cooldown en servidor por IP (30 s) Y por email víctima (1/min); mensaje genérico siempre.
     * El cooldown por email evita que un atacante con IPs rotativas bombardee el buzón de un usuario.
     */
    public function resend(): void
    {
        if (! $this->sent || $this->resendsLeft <= 0) {
            return;
        }

        $key = 'verify-resend:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 1)) {
            return; // respeta el cooldown aunque se fuerce desde el cliente
        }
        RateLimiter::hit($key, 30);

        // Cooldown por EMAIL víctima (anti-DoS distribuido al buzón ajeno).
        $emailKey = 'verify-resend-email:'.self::emailHash($this->email);
        if (RateLimiter::tooManyAttempts($emailKey, 1)) {
            $this->resendsLeft--;            // sí cuenta el intento (límite total del modal)
            Log::info('auth.verification_resend_email_throttled', ['ip' => request()->ip()]);

            return;
        }
        RateLimiter::hit($emailKey, 60);

        $user = User::where('email', $this->email)->first();
        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        $this->resendsLeft--;
        Log::info('auth.verification_resent', ['ip' => request()->ip()]);
    }

    /**
     * Hash corto del email para usar como clave de rate limiter sin exponer el email en cache.
     * No es PII (no se puede invertir y trunca el SHA-256). Compartido por register/resend.
     */
    public static function emailHash(string $email): string
    {
        return substr(hash('sha256', Str::lower(trim($email))), 0, 16);
    }

    public function render()
    {
        return view('livewire.auth.register', [
            'turnstileEnabled' => Turnstile::enabled(),
            'turnstileSiteKey' => Turnstile::siteKey(),
        ]);
    }
}
