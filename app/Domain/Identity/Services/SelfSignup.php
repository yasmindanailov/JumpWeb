<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\SignupResult;
use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Platform\Services\Analytics\Recorder;
use App\Domain\Platform\Services\Turnstile;
use App\Notifications\AccountAlreadyExists;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * Alta PÚBLICA de un cliente: la que hace el propio interesado desde la web o desde la app
 * (Fase 3 · paso 3c, `docs/specs/api-v1.md` §4.6.3).
 *
 * No confundir con {@see CustomerRegistrar}, que es el alta que hace un OPERADOR desde el
 * back-office con el cliente delante: aquella crea la cuenta ya verificada y con contraseña
 * aleatoria, y no necesita defenderse de bots porque detrás hay una persona autenticada.
 *
 * Aquí, en cambio, casi todo el código es defensa, y esa es la razón de extraerlo: son cuatro
 * capas —honeypot, límite por IP, límite por correo y anti-bot— que una API tendría que
 * reimplementar entera para no ser la puerta floja. Se comprobó una por una al escribirlo; ninguna
 * es decorativa:
 *  - **honeypot**: campo oculto; si llega relleno, no se crea nada y se responde como si sí;
 *  - **límite por IP** (5/min): frena el alta masiva desde un origen;
 *  - **límite por CORREO** (3/hora, con el correo hasheado para no dejarlo en claves de caché):
 *    frena que alguien con IPs rotativas bombardee el buzón de una víctima con verificaciones o
 *    con avisos de «ya tienes cuenta». Es también lo que acota la enumeración;
 *  - **Turnstile**: data-driven y no-op sin claves configuradas.
 *
 * **Qué NO hace, a propósito**: iniciar sesión. En la compra («pay-first») el alta va seguida de
 * un `Auth::login()`, pero eso es un efecto de la sesión del llamante — spec §4.6.3, mismo criterio
 * que en `PasswordLogin`. El servicio devuelve el usuario y quien atiende decide.
 */
class SelfSignup
{
    /** Altas por minuto y por IP. */
    public const MAX_PER_IP = 5;

    /** Altas por hora y por CORREO destinatario. Es el que acota la enumeración. */
    public const MAX_PER_EMAIL = 3;

    /**
     * Segundos entre dos reenvíos de verificación **desde la misma IP**.
     *
     * Frena el reenvío en ráfaga desde un origen. **No es el que ata a quien acaba de darse de
     * alta**: ése es el de abajo. Ver el aviso de {@see self::RESEND_EMAIL_COOLDOWN_SECONDS}.
     */
    public const RESEND_IP_COOLDOWN_SECONDS = 30;

    /**
     * Segundos entre dos reenvíos de verificación **al mismo CORREO**.
     *
     * Protege el buzón de alguien que no ha pedido nada: sin él, con IPs rotativas se puede
     * bombardear una dirección ajena a verificaciones.
     *
     * ⚠️⚠️ **Es el cooldown que ATA de verdad, y una pantalla que ofrezca reenviar antes de esto
     * miente** (2026-08-23). El endpoint responde **202 mande o no mande** —`SEC-06`, la misma
     * anti-enumeración del alta—, así que el cliente no puede enterarse de que su reenvío se
     * descartó: ve «reenviado», gasta uno de sus intentos y no le llega nada.
     * ▶ **Medido en navegador, dos veces**: con la cuenta atrás del cajón en 30 s, cuatro reenvíos
     * produjeron **dos correos** —los pulsados a los 32 s y 93 s salieron; los de 63 s y 123 s los
     * tiró este limitador—.
     * ▶ Por eso `resources/js/sidebar/account/verify.js` espeja **ESTE** número y no el de IP, y hay
     * una paridad que lo vigila (`VerifyResendCooldownParityTest`): bajarlo allí por su cuenta
     * devuelve el botón que no hace nada.
     */
    public const RESEND_EMAIL_COOLDOWN_SECONDS = 60;

    /**
     * @param  array{name:string,email:string,phone:string,password:string}  $data  ya validado por el llamante
     * @param  bool  $notifyByEmail  `false` en la compra: pay-first no manda verificación (`DECISIONES` del 2026-06-14 —
     *                               el pago la sustituye, y un bot no paga)
     * @param  string  $honeypot  campo señuelo; si llega con algo, es un bot
     */
    public function register(array $data, string $ip, bool $notifyByEmail = true, string $honeypot = '', string $turnstileToken = ''): SignupResult
    {
        // 1) Honeypot. No se crea nada, pero el desenlace se parece a un éxito: si el bot pudiera
        //    distinguirlo, el señuelo dejaría de servir. Y si fuera un falso positivo del
        //    autocompletar del navegador, la persona tampoco se queda sin respuesta.
        if ($honeypot !== '') {
            Log::info('auth.register_honeypot', ['ip' => $ip]);

            return SignupResult::pretended();
        }

        // 2) Límite por IP: cuenta TODOS los intentos, válidos o no.
        $ipKey = 'register:'.$ip;
        if (RateLimiter::tooManyAttempts($ipKey, self::MAX_PER_IP)) {
            return SignupResult::rateLimited(RateLimiter::availableIn($ipKey));
        }
        RateLimiter::hit($ipKey, 60);

        $email = Str::lower(trim($data['email']));

        // 3) Límite por CORREO víctima. Al superarlo se responde como un éxito y NO se envía nada:
        //    quien bombardea un buzón ajeno no debe poder distinguir cuándo deja de funcionar.
        $emailKey = 'register-email:'.self::emailHash($email);
        if (RateLimiter::tooManyAttempts($emailKey, self::MAX_PER_EMAIL)) {
            Log::info('auth.register_email_throttled', ['ip' => $ip]);

            return SignupResult::pretended();
        }
        RateLimiter::hit($emailKey, 3600);

        // 4) Anti-bot, solo si hay claves configuradas.
        if (! Turnstile::verify($turnstileToken, $ip)) {
            return SignupResult::botCheckFailed();
        }

        // 5) Cuenta ya existente. **Se le dice al usuario** (decisión de producto de la clienta:
        //    conversión sobre ocultación) y el aviso al titular real va por correo, no en pantalla.
        if ($existing = User::where('email', $email)->first()) {
            return $this->handleExisting($existing, $ip);
        }

        // 6) Cuenta + rol + consentimiento de privacidad, en UNA transacción. Sin ella, un fallo a
        //    mitad dejaría al usuario sin rol o sin la constancia que exige el art. 5.2 del RGPD.
        //    El correo se manda FUERA: un fallo de SMTP no puede revertir un alta ya válida.
        $user = $this->createAccount($data, $email, $ip);

        if ($notifyByEmail) {
            $this->sendVerification($user, $ip);
        }

        Log::info('auth.registered', ['user_id' => $user->id, 'ip' => $ip, 'purchase' => ! $notifyByEmail]);

        // El libro de eventos (`specs/analitica.md` §4.1, `#678`): el alta es un hecho del servidor.
        app(Recorder::class)->fact('user_registered', ['method' => 'password'], ['user_id' => (int) $user->id]);
        // Y el régimen identificado (§4.3, T3a·3): con la categoría `analytics`, la navegación que trajo el alta
        // se ata a la cuenta recién creada. Desde el panel no hay visitante y no ata nada.
        app(AccountAnalytics::class)->linkIfConsented($user, $ip);

        return SignupResult::created($user);
    }

    /**
     * Reenvía la verificación a un correo dado. Sin sesión a propósito: quien acaba de darse de
     * alta todavía no la tiene, y es justo cuando más falta le hace.
     *
     * Dos cooldowns, y el segundo es el que importa: sin el límite por CORREO, cualquiera con IPs
     * rotativas podría llenar el buzón de un tercero. Responde siempre lo mismo —haya enviado o
     * no— para no delatar si esa cuenta existe o ya está verificada.
     */
    public function resendVerification(string $email, string $ip): bool
    {
        $ipKey = 'verify-resend:'.$ip;
        if (RateLimiter::tooManyAttempts($ipKey, 1)) {
            return false;
        }
        RateLimiter::hit($ipKey, self::RESEND_IP_COOLDOWN_SECONDS);

        $emailKey = 'verify-resend-email:'.self::emailHash($email);
        if (RateLimiter::tooManyAttempts($emailKey, 1)) {
            Log::info('auth.verification_resend_email_throttled', ['ip' => $ip]);

            return false;
        }
        RateLimiter::hit($emailKey, self::RESEND_EMAIL_COOLDOWN_SECONDS);

        $user = User::where('email', Str::lower(trim($email)))->first();
        if ($user && ! $user->hasVerifiedEmail()) {
            $this->sendVerification($user, $ip);
        }

        Log::info('auth.verification_resent', ['ip' => $ip]);

        return true;
    }

    /**
     * Hash corto del correo para usarlo como clave de limitador sin dejarlo en claro en la caché.
     * No es reversible y va truncado.
     */
    public static function emailHash(string $email): string
    {
        return substr(hash('sha256', Str::lower(trim($email))), 0, 16);
    }

    /**
     * Cuenta existente: verificada → aviso al titular real y se le dice a quien lo intenta; sin
     * verificar → se le reenvía la verificación para que pueda completar su alta.
     *
     * Los dos envíos son NO bloqueantes: la respuesta al usuario no depende de que el SMTP conteste.
     */
    private function handleExisting(User $existing, string $ip): SignupResult
    {
        Log::info('auth.register_existing_email', ['ip' => $ip, 'verified' => $existing->hasVerifiedEmail()]);

        if ($existing->hasVerifiedEmail()) {
            $this->notifySafely($existing, fn () => $existing->notify(new AccountAlreadyExists));

            return SignupResult::alreadyRegistered();
        }

        $this->sendVerification($existing, $ip);

        return SignupResult::pendingVerification();
    }

    /**
     * @param  array{name:string,email:string,phone:string,password:string}  $data
     */
    private function createAccount(array $data, string $email, string $ip): User
    {
        $now = now();

        return DB::transaction(function () use ($data, $email, $ip, $now): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $email,
                'phone' => $data['phone'],
                'password' => $data['password'],
                'locale' => app()->getLocale(),
                // ⚠️ **El marketing NO se pide en el alta** (`[DECIDIDO owner, 2026-09-02]`, T8·c): se
                // ofrece con su interruptor en «Mi cuenta → Privacidad», donde además se puede retirar
                // (art. 7.3). Aquí nace apagado, igual que en el alta con Google y en la de mostrador.
                'marketing_opt_in' => false,
                // ⚠️⚠️ **Privacidad sí, condiciones NO, y la asimetría es la tanda entera.** La
                // privacidad se INFORMA (art. 13) y su rastro es la prueba del art. 5.2; las
                // condiciones se ACEPTAN, y desde la T8 eso ocurre en el momento del contrato —el
                // checkout—, no al crear la cuenta.
                'privacy_accepted_at' => $now,
                // #216: el waiver salió del flujo de alta (lo gestiona el sistema externo de la
                // clienta). La columna y el tipo de consent se conservan para datos históricos.
            ]);

            if ($role = Role::where('name', 'customer')->first()) {
                $user->roles()->attach($role);
            }

            // ⚠️⚠️ **NO se escribe una fila `terms` aquí, y no es una simplificación: escribirla
            // INDULTA a la cuenta.** Medido antes de tocar nada: la regla de gracia de
            // {@see TermsAcceptance::statusFor()} da por aceptada la v1 a quien tenga una aceptación
            // anterior al versionado, y `Consent::CURRENT_VERSION` es una fecha, no un `vN·xx`. Una
            // cuenta recién creada salía con `pendingFor() === false` y **el checkout no le pedía
            // nada** — el hueco que la T8·b existe para cerrar, reabierto por su propio alta.
            $user->consents()->create([
                'type' => Consent::TYPE_PRIVACY,
                'accepted_at' => $now,
                'ip' => $ip,
                'version' => Consent::CURRENT_VERSION,
            ]);

            // Fase 6 · waiver (`specs/waiver-probatorio.md` §4.4): la casilla SEPARADA y desmarcada del
            // alta. Solo si el llamante ya comprobó que el identificador es el de la versión vigente
            // (`WaiverAcceptance::currentDocument`) y lo pasa aquí resuelto: esta transacción no
            // puede fallar por un texto caducado después de haber creado la cuenta. Mismo `WaiverSigner`
            // que las demás puertas — una firma es una firma, entre por donde entre.
            // `[DECIDIDO owner, 2026-08-26]` (spec §7·5, `#179`): el alta YA NO FIRMA — se firma con el
            // correo verificado. Aquí queda la aceptación PENDIENTE (qué texto, por qué canal), y la
            // convierte en firma `SignPendingWaiverOnVerification` al verificar, si el texto sigue
            // vigente. Hasta `#179` la firma nacía con `email_verified_at = null` (revisión `#169`
            // §10.2·3): cualquiera podía aceptar «en nombre» del correo de un tercero.
            $waiver = $data['waiver'] ?? null;
            if (is_array($waiver) && ($waiver['document'] ?? null) instanceof LegalDocumentVersion) {
                $user->forceFill([
                    'waiver_pending_document_id' => (int) $waiver['document']->getKey(),
                    'waiver_pending_channel' => (string) ($waiver['channel'] ?? WaiverSignature::CHANNEL_WEB),
                    // S-1 (`#181`): la firma llevará la IP y el navegador de ESTE momento —cuando la persona marcó
                    // la casilla—, no los de la petición que verifique (que en pay-first puede ser Redsys).
                    'waiver_pending_ip' => $ip !== '' ? mb_substr($ip, 0, 45) : null,
                    'waiver_pending_user_agent' => isset($waiver['user_agent']) ? mb_substr((string) $waiver['user_agent'], 0, 512) : null,
                ])->save();
            }

            return $user;
        });
    }

    private function sendVerification(User $user, string $ip): void
    {
        $this->notifySafely($user, fn () => $user->sendEmailVerificationNotification());
    }

    /**
     * Envía sin dejar que un fallo del transporte tumbe la operación: la cuenta ya existe y el
     * usuario puede pedir el reenvío. Se registra para que el fallo no sea invisible.
     */
    private function notifySafely(User $user, callable $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            Log::warning('auth.register_email_failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}
