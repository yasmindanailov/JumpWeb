<?php

namespace App\Livewire\Auth;

use App\Domain\Identity\Contracts\SignupResult;
use App\Domain\Identity\Services\SelfSignup;
use App\Domain\Platform\Services\Turnstile;
use App\Livewire\Concerns\ResetsOnModalClose;
use Illuminate\Support\Facades\Auth;
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

    public function register(SelfSignup $signup)
    {
        // El honeypot y el correo se pasan tal cual al servicio: es él quien decide qué hacer con
        // ellos. Validar ANTES de llamarlo sería adelantar la respuesta a un bot —le diríamos qué
        // campos están mal antes de que el señuelo actúe—, así que el orden importa: señuelo,
        // límites y anti-bot primero (dentro del servicio), y aquí solo la validación de forma
        // cuando ya sabemos que hay una persona detrás.
        if ($this->website === '') {
            $this->email = Str::lower(trim($this->email));
            $this->validate();
        }

        $result = $signup->register(
            [
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'password' => $this->password,
                'marketing' => $this->marketing,
            ],
            (string) request()->ip(),
            // Pay-first (decisión clienta 2026-06-14): en la COMPRA no se manda verificación ni se
            // retiene la reserva. El correo se verifica solo al pagar en Redsys (auto-verify: un bot
            // no paga). Si abandona sin pagar, queda sin verificar y puede pedir el reenvío desde la
            // página de aviso al intentar entrar en «mi cuenta».
            notifyByEmail: ! $this->embedded,
            honeypot: $this->website,
            turnstileToken: $this->turnstileToken,
        );

        return $this->reportSignup($result);
    }

    /**
     * Traduce el veredicto del dominio a esta interfaz: qué se pinta y qué se le dice al sidebar.
     * Los efectos de SESIÓN —iniciar sesión en la compra, el marcador anti-cesta-cruzada— viven
     * aquí y no en el servicio, igual que en el login (spec §4.6.3).
     */
    private function reportSignup(SignupResult $result)
    {
        if ($result->outcome === SignupResult::RATE_LIMITED) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => $result->retryAfter]),
            ]);
        }

        if ($result->outcome === SignupResult::BOT_CHECK_FAILED) {
            throw ValidationException::withMessages([
                'email' => __('account.register.bot_check_failed'),
            ]);
        }

        // [decisión clienta] Feedback CLARO en vez de anti-enumeración: para el parque prima la
        // UX/conversión sobre ocultar qué correos existen. Se avisa en pantalla y se guía al login.
        if ($result->outcome === SignupResult::ALREADY_REGISTERED) {
            throw ValidationException::withMessages([
                'email' => __('account.register.already_exists'),
            ]);
        }

        if ($result->outcome === SignupResult::PENDING_VERIFICATION) {
            throw ValidationException::withMessages([
                'email' => __('account.register.exists_unverified'),
            ]);
        }

        // Alta dentro de la compra: se inicia sesión y se avisa al sidebar para que siga al pago.
        if ($this->embedded && $result->user !== null) {
            Auth::login($result->user);
            session(['purchase.user_id' => $result->user->getKey()]); // anti-cesta-cruzada (espejo de Login)
            $this->dispatch('logged-in'); // Purchase::onAuthenticated → proceed() → paso de pago

            return null;
        }

        $this->finishGeneric();

        return null;
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
    public function resend(SelfSignup $signup): void
    {
        if (! $this->sent || $this->resendsLeft <= 0) {
            return;
        }

        // Los dos cooldowns (por IP y por correo víctima) los aplica el servicio; aquí solo queda
        // el tope de reenvíos de ESTA pantalla, que es estado de la UI y no una regla de servidor.
        // Se descuenta pase lo que pase: si no, un cliente forzado podría reintentar sin límite.
        $signup->resendVerification($this->email, (string) request()->ip());
        $this->resendsLeft--;
    }

    public function render()
    {
        return view('livewire.auth.register', [
            'turnstileEnabled' => Turnstile::enabled(),
            'turnstileSiteKey' => Turnstile::siteKey(),
        ]);
    }
}
